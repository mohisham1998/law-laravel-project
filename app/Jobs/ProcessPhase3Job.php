<?php

namespace App\Jobs;

use App\Enums\AgentStatus;
use App\Enums\CaseStatus;
use App\Models\AgentExecution;
use App\Models\CaseMetrics;
use App\Models\LegalCase;
use App\Services\Agents\Phase3\ArabicPolisherAgent;
use App\Services\Agents\Phase3\BriefEnricherAgent;
use App\Services\Agents\Phase3\DevilsAdvocateAgent;
use App\Services\Agents\Phase3\FortificationAgent;
use App\Services\Agents\Phase3\JudgeAgent;
use App\Services\CaseEventService;
use App\Services\LLM\LLMServiceFactory;
use App\Services\LLM\LLMServiceInterface;
use App\Services\Orchestration\OutputValidator;
use App\Services\Output\BriefPostProcessor;
use App\Services\Output\FinalArabicBriefComposer;
use App\Services\Puter\PuterException;
use App\Services\Cost\CostCalculator;
use App\Services\Cost\TokenTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPhase3Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;
    
    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;
    
    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 1800;
    
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
            $oldStatus = $case->status->value ?? $case->status;
            $case->update([
                'status' => CaseStatus::Phase3Processing,
                'phase' => 3,
                'pipeline_started_at' => now(),
            ]);

            app(CaseEventService::class)->emitStatusChanged($case->id, (string) $oldStatus, CaseStatus::Phase3Processing->value);

            $events = app(CaseEventService::class);
            $tokenTracker = app(TokenTracker::class);
            $costCalc = app(CostCalculator::class);

            // Bind correct LLM service for Phase3 agents resolved via app()
            $llmService = LLMServiceFactory::make($this->puterToken ?: null, $this->openrouterApiKey ?: null);
            app()->bind(LLMServiceInterface::class, fn () => $llmService);

            $agents = [
                10 => app(JudgeAgent::class),
                11 => app(DevilsAdvocateAgent::class),
                12 => app(FortificationAgent::class),
                13 => app(ArabicPolisherAgent::class),
                14 => app(BriefEnricherAgent::class),
            ];

            foreach ($agents as $agentNum => $agent) {
                // Skip agents that already completed successfully (resume support)
                $existingExec = AgentExecution::where('case_id', $case->id)
                    ->where('agent_number', $agentNum)
                    ->where('status', AgentStatus::Completed)
                    ->first();
                if ($existingExec) {
                    Log::info("Phase 3 agent {$agentNum} already completed, skipping", ['case_id' => $case->id]);
                    continue;
                }

                $case->update(['current_agent' => $agentNum]);

                $exec = AgentExecution::create([
                    'case_id' => $case->id,
                    'agent_number' => $agentNum,
                    'agent_name' => $agent->agentName(),
                    'status' => AgentStatus::InProgress,
                    'started_at' => now(),
                    'retry_count' => 0,
                ]);

                $events->emitStarted($case->id, $agentNum, $agent->agentName());

                try {
                    $agentStart = microtime(true);
                    $result = $agent->execute($case);
                    $durationMs = (int) round((microtime(true) - $agentStart) * 1000);

                    $promptTokens = $result['prompt_tokens'] ?? 0;
                    $completionTokens = $result['completion_tokens'] ?? 0;
                    $tokens = $promptTokens + $completionTokens;
                    $costUsd = $costCalc->calculateUsd($promptTokens, $completionTokens);

                    $tokenTracker->addToCase($case, $promptTokens, $completionTokens);
                    $tokenTracker->addToUser($case->user, $promptTokens, $completionTokens);
                    $case->increment('total_cost_usd', $costUsd);
                    $case->user()->increment('total_cost_usd', $costUsd);

                    $exec->update([
                        'status' => AgentStatus::Completed,
                        'prompt_tokens' => $promptTokens,
                        'completion_tokens' => $completionTokens,
                        'total_tokens' => $tokens,
                        'cost_usd' => $costUsd,
                        'duration_ms' => $durationMs,
                        'completed_at' => now(),
                    ]);

                    $events->emitCompleted($case->id, $agentNum, $agent->agentName(), [
                        'tokens_used' => $tokens,
                        'duration_ms' => $durationMs,
                    ]);
                } catch (\Throwable $e) {
                    // Convert to user-friendly error
                    $userMessage = $this->getUserFriendlyError($e);
                    
                    Log::error("Phase 3 agent {$agentNum} failed, continuing pipeline: " . $e->getMessage(), ['case_id' => $case->id]);
                    $exec->update([
                        'status' => AgentStatus::Failed,
                        'error_message' => $userMessage,
                        'completed_at' => now(),
                    ]);
                    $events->emitFailed($case->id, $agentNum, $agent->agentName(), $userMessage);
                    // Do NOT throw — continue to next agent
                }
            }

            // Post-process brief v3 from Agent 12
            $this->postProcessBriefV3($case);

            // Run quality gate on final composed brief
            $qualityGatePassed = $this->runQualityGate($case);

            $finalStatus = $qualityGatePassed
                ? CaseStatus::Phase3Completed
                : CaseStatus::CompletedWithWarnings;

            $oldStatus = $case->status->value ?? $case->status;
            $case->update([
                'status' => $finalStatus,
                'phase' => 3,
                'progress_percentage' => 100,
                'current_agent' => null,
                'completed_at' => now(),
            ]);
            app(CaseEventService::class)->emitStatusChanged($case->id, (string) $oldStatus, $finalStatus->value);

            Log::info("Phase 3 complete — quality gate " . ($qualityGatePassed ? 'PASSED' : 'FAILED'), ['case_id' => $case->id]);

            CaseMetrics::upsertForCase($case->fresh(['agentExecutions']));
        } catch (\Throwable $e) {
            Log::error("Phase 3 failed for case {$case->id}: " . $e->getMessage(), ['exception' => $e]);
            
            // Convert to user-friendly error
            $userMessage = $this->getUserFriendlyError($e);
            $oldStatus = $case->status->value ?? $case->status;
            $case->update([
                'status' => CaseStatus::Failed,
                'last_error_message' => $userMessage,
            ]);
            app(CaseEventService::class)->emitFailed($case->id, (int) ($case->current_agent ?? 0), 'المرحلة الثالثة', $userMessage);
            app(CaseEventService::class)->emitStatusChanged($case->id, (string) $oldStatus, CaseStatus::Failed->value);
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
     * Post-process the Agent 12 brief (v3) and the Agent 13 polished brief using BriefPostProcessor.
     */
    private function postProcessBriefV3(LegalCase $case): void
    {
        // Process 13_final_brief_v3.md (from Agent 12)
        $output = $case->outputs()->where('filename', '13_final_brief_v3.md')->first();
        if ($output) {
            $content = (string) ($output->content ?? '');
            if (empty(trim($content)) && $output->file_path) {
                $full = Storage::disk('local')->path($output->file_path);
                if (file_exists($full)) {
                    $content = file_get_contents($full);
                }
            }

            // If Agent 12's FINAL_BRIEF_V3 section is truncated (< 800 chars) OR
            // significantly shorter than the QA-checked v2 brief (fortification degraded the brief),
            // fall back to the v2 brief from Agent 9 which is complete and well-formed.
            $v2output = $case->outputs()->where('filename', '09_final_brief_v2.md')->first();
            $v2content = '';
            if ($v2output) {
                $v2content = (string) ($v2output->content ?? '');
                if (empty(trim($v2content)) && $v2output->file_path) {
                    $full = Storage::disk('local')->path($v2output->file_path);
                    if (file_exists($full)) {
                        $v2content = file_get_contents($full);
                    }
                }
            }
            $v3Len = mb_strlen(trim($content));
            $v2Len = mb_strlen(trim($v2content));
            if ($v3Len < 800 || ($v2Len > 800 && $v2Len > $v3Len * 1.15)) {
                Log::warning('13_final_brief_v3.md degraded or too short — falling back to 09_final_brief_v2.md', [
                    'case_id'   => $case->id,
                    'v3_length' => $v3Len,
                    'v2_length' => $v2Len,
                ]);
                if ($v2Len >= 800) {
                    $content = $v2content;
                }
            }

            if (!empty(trim($content))) {
                $cleaned = BriefPostProcessor::process($content);
                $output->update(['content' => $cleaned]);
                if ($output->file_path) {
                    Storage::disk('local')->put($output->file_path, $cleaned);
                }
                Log::info('BriefPostProcessor applied to 13_final_brief_v3.md', ['case_id' => $case->id]);
            }
        }

        // Process 14_final_brief_polished.md (from Agent 13) if it exists
        $polishedOutput = $case->outputs()->where('filename', '14_final_brief_polished.md')->latest('id')->first();
        if ($polishedOutput) {
            $polishedContent = (string) ($polishedOutput->content ?? '');
            if (empty(trim($polishedContent)) && $polishedOutput->file_path) {
                $full = Storage::disk('local')->path($polishedOutput->file_path);
                if (file_exists($full)) {
                    $polishedContent = file_get_contents($full);
                }
            }

            if (!empty(trim($polishedContent))) {
                $cleaned = BriefPostProcessor::process($polishedContent);
                $polishedOutput->update(['content' => $cleaned]);
                if ($polishedOutput->file_path) {
                    Storage::disk('local')->put($polishedOutput->file_path, $cleaned);
                }
                Log::info('BriefPostProcessor applied to 14_final_brief_polished.md', ['case_id' => $case->id]);
            }
        }
    }

    /**
     * Run quality gate on the best available final brief.
     */
    private function runQualityGate(LegalCase $case): bool
    {
        $brief = FinalArabicBriefComposer::compose($case);
        if (empty($brief)) {
            Log::info('Quality gate: no final brief found', ['case_id' => $case->id]);
            return true;
        }

        $violations = array_merge(
            OutputValidator::validateBriefStructure($brief),
            OutputValidator::validateArabicFinalBrief($brief),
            OutputValidator::validateNoEnglishLeak($brief),
        );

        if (!empty($violations)) {
            $summary = implode('; ', array_map(fn ($v) => "[{$v['type']}] {$v['detail']}", $violations));
            Log::warning('Quality gate FAILED for Phase 3 brief', [
                'case_id' => $case->id,
                'violations' => $summary,
            ]);
            return false;
        }

        Log::info('Quality gate PASSED for Phase 3 brief', ['case_id' => $case->id]);
        return true;
    }

    /**
     * Handle a job failure after all retries.
     */
    public function failed(\Throwable $exception): void
    {
        $case = $this->case;
        
        // Get the original error that caused the failure
        $originalError = $this->getOriginalError($exception);
        
        Log::error("ProcessPhase3Job permanently failed for case {$case->id}", [
            'original_error' => $originalError,
            'exception_message' => $exception->getMessage(),
            'case_id' => $case->id,
        ]);
        
        // Update case with user-friendly error message
        $userMessage = $this->getUserFriendlyError($exception);
        $oldStatus = $case->status->value ?? $case->status;
        $case->update([
            'status' => CaseStatus::Failed,
            'last_error_message' => $userMessage,
        ]);
        app(CaseEventService::class)->emitFailed($case->id, (int) ($case->current_agent ?? 0), 'المرحلة الثالثة', $userMessage);
        app(CaseEventService::class)->emitStatusChanged($case->id, (string) $oldStatus, CaseStatus::Failed->value);
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
