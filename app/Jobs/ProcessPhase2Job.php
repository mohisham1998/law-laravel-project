<?php

namespace App\Jobs;

use App\Enums\CaseStatus;
use App\Models\LegalCase;
use App\Services\LLM\LLMServiceFactory;
use App\Services\LLM\LLMServiceInterface;
use App\Services\Orchestration\LegalOrchestrator;
use App\Services\CaseEventService;
use App\Services\Puter\PuterException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPhase2Job implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    /**
     * The number of times the job may be attempted.
     * Allow 3 attempts so transient errors (API blips, connection resets) can be retried.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * Keep unique lock for a reasonable window — short enough that a retry after failure
     * can dispatch a new job without waiting hours, but long enough to prevent duplicates
     * while a normal Phase2 run is in progress.
     */
    public int $uniqueFor = 3600;
    
    /**
     * Indicate if the job should be marked as failed when the max exceptions is exceeded.
     */
    public bool $failOnTimeout = true;

    public function __construct(
        public LegalCase $case,
        public string $puterToken = '',
        public string $openrouterApiKey = '',
    ) {
        $pipelineTimeoutMinutes = (int) config('legal.pipeline_timeout_minutes', 30);
        $this->timeout  = max(600, ($pipelineTimeoutMinutes * 60) + 120);
        $this->uniqueFor = max(7200, $this->timeout + 600);
        $this->onQueue('default');
    }

    /**
     * Ensure only one queued Phase2 job exists per case at a time.
     */
    public function uniqueId(): string
    {
        return 'phase2:' . $this->case->id;
    }

    public function handle(): void
    {
        // Bind correct LLM service before the orchestrator (and its Phase2 agents) are constructed
        $llmService = LLMServiceFactory::make($this->puterToken ?: null, $this->openrouterApiKey ?: null);
        app()->bind(LLMServiceInterface::class, fn () => $llmService);

        $orchestrator = app(LegalOrchestrator::class);

        $case = $this->case;

        try {
            $oldStatus = $case->status->value ?? $case->status;
            $case->update([
                'status' => CaseStatus::Phase2Processing,
                'phase' => 2,
                'started_at' => now(),
                'pipeline_started_at' => now(),
                'retry_budget_max' => config('legal.retry_budget_per_case', 5),
                'retry_budget_used' => 0,
            ]);

            app(CaseEventService::class)->emitStatusChanged($case->id, (string) $oldStatus, CaseStatus::Phase2Processing->value);

            $orchestrator->runPhase2($case, $case->resume_from_agent, $this->openrouterApiKey);
        } catch (\Throwable $e) {
            // Store the actual error message for user display BEFORE throwing
            // (queue retries would overwrite this with generic message)
            $actualError = $this->getUserFriendlyError($e, $case);
            $oldStatus = $case->status->value ?? $case->status;
            $case->update([
                'status' => CaseStatus::Failed,
                'last_error_message' => $actualError,
            ]);
            app(CaseEventService::class)->emitFailed($case->id, (int) ($case->current_agent ?? 0), 'المرحلة الثانية', $actualError);
            app(CaseEventService::class)->emitStatusChanged($case->id, (string) $oldStatus, CaseStatus::Failed->value);
            Log::error("Phase 2 failed for case {$case->id}: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }
    
    /**
     * Convert technical exceptions to user-friendly messages
     */
    private function getUserFriendlyError(\Throwable $e, LegalCase $case): string
    {
        if ($e instanceof PuterException) {
            return $e->getMessage();
        }

        $message = $e->getMessage();
        $currentAgent = $case->current_agent;
        
        // Check for specific error types and make them user-friendly
        if (str_contains($message, 'Insufficient credits') || str_contains($message, 'insufficient credits')) {
            return 'رصد غير كافٍ في OpenRouter. يرجى إضافة رصيد ومحاولة снова.';
        }
        
        if (str_contains($message, 'API error') || str_contains($message, 'api error')) {
            return "خطأ في الاتصال بخدمة الذكاء الاصطناعي (API). يرجى المحاولة مرة أخرى.";
        }
        
        if (str_contains($message, 'timeout') || str_contains($message, 'Timeout')) {
            return "استغرقت المعالجة وقتاً طويلاً (timeout). يرجى المحاولة مرة أخرى.";
        }
        
        if (str_contains($message, 'connection') || str_contains($message, 'Connection')) {
            return "حدث مشكلة في الاتصال بالإنترنت. يرجى التحقق من الاتصال وحاول مرة أخرى.";
        }
        
        // If we know which agent failed, include it
        if ($currentAgent) {
            $agentNames = [
                1 => 'القائد القانوني',
                2 => 'مدير الأدلة',
                3 => 'سلسلة الحفظ',
                4 => 'الجدول الزمني',
                5 => 'مدير القانون',
                6 => 'مطابق الأنظمة',
                7 => 'الاستراتيجي',
                8 => 'الصائغ القانوني',
                9 => 'ضبط الجودة',
            ];
            $agentName = $agentNames[$currentAgent] ?? "الوكيل {$currentAgent}";
            return "فشل الوكيل: {$agentName}. {$message}";
        }
        
        return $message;
    }
    
    /**
     * Handle a job failure after all retries.
     */
    public function failed(\Throwable $exception): void
    {
        $case = $this->case;
        
        // Get the original error that caused the failure (not the MaxAttemptsExceededException)
        $originalError = $this->getOriginalError($exception);
        
        Log::error("ProcessPhase2Job permanently failed for case {$case->id}", [
            'original_error' => $originalError,
            'exception_message' => $exception->getMessage(),
            'case_id' => $case->id,
        ]);
        
        // Update case status to failed with user-friendly message
        $userMessage = $this->getUserFriendlyError($exception, $case);
        $oldStatus = $case->status->value ?? $case->status;
        $case->update([
            'status' => CaseStatus::Failed,
            'last_error_message' => $userMessage,
        ]);
        app(CaseEventService::class)->emitFailed($case->id, (int) ($case->current_agent ?? 0), 'المرحلة الثانية', $userMessage);
        app(CaseEventService::class)->emitStatusChanged($case->id, (string) $oldStatus, CaseStatus::Failed->value);
    }
    
    /**
     * Extract the original error that caused the failure (not the MaxAttemptsExceededException)
     */
    private function getOriginalError(\Throwable $exception): string
    {
        $current = $exception;
        while ($current) {
            // Look for the actual error, not MaxAttemptsExceededException
            if (!$current instanceof \Illuminate\Queue\MaxAttemptsExceededException) {
                $msg = $current->getMessage();
                if ($msg && !str_contains($msg, 'has been attempted too many times')) {
                    return $msg;
                }
            }
            $current = $current->getPrevious();
        }
        return $exception->getMessage();
    }
}
