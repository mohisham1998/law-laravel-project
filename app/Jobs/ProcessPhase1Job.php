<?php

namespace App\Jobs;

use App\Enums\CaseStatus;
use App\Models\LegalCase;
use App\Services\Agents\Phase1AnalysisAgent;
use App\Services\CaseEventService;
use App\Services\Cost\CostCalculator;
use App\Services\Cost\TokenTracker;
use App\Services\Orchestration\GateValidator;
use App\Services\Orchestration\PromptBuilder;
use App\Services\LLM\LLMServiceFactory;
use App\Services\Puter\PuterException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPhase1Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;
    
    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;
    
    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes
    
    /**
     * Indicate if the job should be marked as failed when the max exceptions is exceeded.
     */
    public bool $failOnTimeout = true;

    public function __construct(
        public LegalCase $case,
        public string $puterToken = '',
        public string $openrouterApiKey = '',
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $case = $this->case;

        try {
            DB::transaction(function () use ($case) {
                $case->update([
                    'status' => CaseStatus::Phase1Processing,
                    'phase' => 1,
                ]);
            });

            $gateValidator = app(GateValidator::class);
            if (!$gateValidator->validatePhase1Gate($case)) {
                $case->update(['status' => CaseStatus::Failed]);
                Log::warning("Phase 1 gate failed for case {$case->id}");
                return;
            }

            $events = app(CaseEventService::class);
            $events->emitStarted($case->id, 0, 'تحليل القضية');

            $startTime = microtime(true);
            
            $promptBuilder = app(PromptBuilder::class);
            $llmService = LLMServiceFactory::make($this->puterToken ?: null, $this->openrouterApiKey ?: null);
            $agent = new Phase1AnalysisAgent($promptBuilder, $gateValidator, $llmService, $events);

            $result = $agent->execute($case);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            
            $events->emitCompleted($case->id, 0, 'تحليل القضية', [
                'tokens_used' => ($result['prompt_tokens'] ?? 0) + ($result['completion_tokens'] ?? 0),
                'duration_ms' => $durationMs,
            ]);

            $tokenTracker = app(TokenTracker::class);
            $costCalc = app(CostCalculator::class);
            $costUsd = $costCalc->calculateUsd($result['prompt_tokens'], $result['completion_tokens']);

            $tokenTracker->addToCase($case, $result['prompt_tokens'], $result['completion_tokens']);
            $tokenTracker->addToUser($case->user, $result['prompt_tokens'], $result['completion_tokens']);

            $case->increment('total_cost_usd', $costUsd);
            $case->user()->increment('total_cost_usd', $costUsd);

            $case->update([
                'status' => CaseStatus::AwaitingLaws,
                'phase' => 1,
                'progress_percentage' => 100,
            ]);

            // Emit status change event for real-time modal display
            app(CaseEventService::class)->emitStatusChanged($case->id, 'phase1_completed', 'awaiting_laws');
        } catch (\Throwable $e) {
            Log::error("Phase 1 failed for case {$case->id}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            
            // Convert to user-friendly error message BEFORE storing
            $userMessage = $this->getUserFriendlyError($e);
            
            $events = app(CaseEventService::class);
            $events->emitFailed($case->id, 0, 'تحليل القضية', $userMessage);
            
            $case->update([
                'status' => CaseStatus::Failed,
                'last_failed_phase' => 'phase1',
                'last_error_message' => $userMessage,
            ]);
            throw $e;
        }
    }
    
    /**
     * Convert technical exceptions to user-friendly messages
     */
    private function getUserFriendlyError(\Throwable $e): string
    {
        if ($e instanceof PuterException) {
            return $e->getMessage();
        }

        $message = $e->getMessage();
        
        if (str_contains($message, 'Insufficient credits') || str_contains($message, 'insufficient credits')) {
            return 'رصد غير كافٍ في OpenRouter. يرجى إضافة رصيد ومحاولة مرة أخرى.';
        }
        
        if (str_contains($message, 'API error') || str_contains($message, 'api error')) {
            return 'خطأ في الاتصال بخدمة الذكاء الاصطناعي. يرجى المحاولة مرة أخرى.';
        }
        
        if (str_contains($message, 'timeout') || str_contains($message, 'Timeout')) {
            return 'استغرقت المعالجة وقتاً طويلاً. يرجى المحاولة مرة أخرى.';
        }
        
        if (str_contains($message, 'connection') || str_contains($message, 'Connection')) {
            return 'حدث مشكلة في الاتصال بالإنترنت. يرجى التحقق من الاتصال وحاول مرة أخرى.';
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
        
        Log::error("ProcessPhase1Job permanently failed for case {$case->id}", [
            'original_error' => $originalError,
            'exception_message' => $exception->getMessage(),
            'case_id' => $case->id,
        ]);
        
        // Update case with user-friendly error message
        $userMessage = $this->getUserFriendlyError($exception);
        $case->update([
            'status' => CaseStatus::Failed,
            'last_failed_phase' => 'phase1',
            'last_error_message' => $userMessage,
        ]);
    }
    
    /**
     * Extract the original error that caused the failure
     */
    private function getOriginalError(\Throwable $exception): string
    {
        $current = $exception;
        while ($current) {
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
