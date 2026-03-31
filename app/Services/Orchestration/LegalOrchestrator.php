<?php

namespace App\Services\Orchestration;

use App\Enums\AgentStatus;
use App\Enums\CaseStatus;
use App\Models\AgentExecution;
use App\Models\CaseMetrics;
use App\Models\ErrorLog;
use App\Models\LegalCase;
use App\Services\Agents\Phase2\ChainOfCustodyAgent;
use App\Services\Agents\Phase2\DefenseStrategistAgent;
use App\Services\Agents\Phase2\EvidenceManagerAgent;
use App\Services\Agents\Phase2\LawManagerAgent;
use App\Services\Agents\Phase2\LeadCounselAgent;
use App\Services\Agents\Phase2\LegalDrafterAgent;
use App\Services\Agents\Phase2\QualityAssuranceAgent;
use App\Services\Agents\Phase2\StatuteMatcherAgent;
use App\Services\Agents\Phase2\TimelineExtractorAgent;
use App\Services\CaseEventService;
use App\Services\Cost\CostCalculator;
use App\Services\Cost\TokenTracker;
use App\Services\Output\BriefPostProcessor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LegalOrchestrator
{
    /** @var array<int, \App\Services\Agents\BaseAgent> */
    protected array $phase2Agents = [];

    /** Default timeout per agent in seconds */
    protected const DEFAULT_TIMEOUT_SECONDS = 180; // 3 minutes

    /** Timeout per agent in seconds (configurable) */
    protected int $agentTimeoutSeconds;

    /** Pipeline timeout in minutes */
    protected int $pipelineTimeoutMinutes;

    /** Retry budget per case */
    protected int $retryBudgetMax;

    public function __construct(
        protected PromptBuilder $promptBuilder,
        protected GateValidator $gateValidator,
        protected TokenTracker $tokenTracker,
        protected CostCalculator $costCalculator,
        protected CaseEventService $events,
    ) {
        $this->phase2Agents = [
            1 => app(LeadCounselAgent::class),
            2 => app(EvidenceManagerAgent::class),
            3 => app(ChainOfCustodyAgent::class),
            4 => app(TimelineExtractorAgent::class),
            5 => app(LawManagerAgent::class),
            6 => app(StatuteMatcherAgent::class),
            7 => app(DefenseStrategistAgent::class),
            8 => app(LegalDrafterAgent::class),
            9 => app(QualityAssuranceAgent::class),
        ];
        
        // Get timeout from config or use default
        $this->agentTimeoutSeconds = config('legal.agent_timeout_seconds', self::DEFAULT_TIMEOUT_SECONDS);
        $this->pipelineTimeoutMinutes = config('legal.pipeline_timeout_minutes', 30);
        $this->retryBudgetMax = config('legal.retry_budget_per_case', 5);
    }

    /**
     * Execute an agent with timeout protection.
     * Returns ['success' => true, 'result' => ...] or ['success' => false, 'error' => ...]
     *
     * NOTE: pcntl_fork() is intentionally NOT used here. Forking a PHP process that holds
     * open database connections (PDO/PostgreSQL) causes the child's exit() to close the
     * shared TCP socket, leaving the parent's DB connection in a broken state. All agents
     * are executed directly in the current process; the queue job's own timeout (set via
     * $this->timeout) acts as the outer safety net.
     */
    protected function executeWithTimeout(\App\Services\Agents\BaseAgent $agent, LegalCase $case): array
    {
        $hasTimedOut = false;

        try {
            $startTime = microtime(true);
            $result = $agent->execute($case);
            $executionTime = microtime(true) - $startTime;

            if ($executionTime > $this->agentTimeoutSeconds) {
                Log::warning("Agent execution exceeded soft timeout: {$executionTime}s > {$this->agentTimeoutSeconds}s", [
                    'case_id' => $case->id,
                    'agent_number' => $agent->agentNumber(),
                    'agent_name' => $agent->agentName(),
                ]);
                $hasTimedOut = true;
            }

            return ['success' => true, 'result' => $result, 'timed_out' => $hasTimedOut];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if pipeline has exceeded timeout.
     * Returns ['timed_out' => bool, 'elapsed_minutes' => int, 'remaining_minutes' => int, 'warning_emitted' => bool]
     */
    protected function checkPipelineTimeout(LegalCase $case): array
    {
        if (!$case->pipeline_started_at) {
            return ['timed_out' => false, 'elapsed_minutes' => 0, 'remaining_minutes' => $this->pipelineTimeoutMinutes, 'warning_emitted' => false];
        }

        $elapsed = now()->diffInMinutes($case->pipeline_started_at);
        $remaining = $this->pipelineTimeoutMinutes - $elapsed;
        $warningThreshold = $this->pipelineTimeoutMinutes * 0.8; // 80% of timeout

        $warningEmitted = false;
        // Check if we've crossed 80% threshold and haven't emitted warning yet
        if ($elapsed >= $warningThreshold && $remaining > 0) {
            $this->events->emitTimeoutWarning(
                $case->id,
                $elapsed,
                $this->pipelineTimeoutMinutes,
                $remaining,
                $case->current_agent
            );
            $warningEmitted = true;
        }

        return [
            'timed_out' => $remaining <= 0,
            'elapsed_minutes' => $elapsed,
            'remaining_minutes' => max(0, $remaining),
            'warning_emitted' => $warningEmitted,
        ];
    }

    /**
     * Check if retry budget is exhausted.
     */
    protected function canUseRetryBudget(LegalCase $case): bool
    {
        $retryBudgetUsed = $case->retry_budget_used ?? 0;
        $retryBudgetMax = $case->retry_budget_max ?? $this->retryBudgetMax;
        return $retryBudgetUsed < $retryBudgetMax;
    }

    /**
     * Consume one unit of retry budget.
     */
    protected function consumeRetryBudget(LegalCase $case): void
    {
        $case->increment('retry_budget_used');
    }

    /**
     * Halt the pipeline due to agent failure.
     */
    protected function haltPipeline(LegalCase $case, int $failedAgentNumber, string $haltReason, array $completedAgents = []): void
    {
        $oldStatus = $case->status->value ?? $case->status;
        $skippedAgents = [];
        for ($i = $failedAgentNumber + 1; $i <= 9; $i++) {
            $skippedAgents[] = $i;
            // Create skipped execution records
            AgentExecution::create([
                'case_id' => $case->id,
                'agent_number' => $i,
                'agent_name' => $this->phase2Agents[$i]->agentName(),
                'status' => AgentStatus::Skipped,
                'started_at' => now(),
                'completed_at' => now(),
            ]);
        }

        $case->update([
            'status' => CaseStatus::Halted,
            'halted_at' => now(),
            'halted_at_agent' => $failedAgentNumber,
            'halt_reason' => $haltReason,
            'current_agent' => $failedAgentNumber,
            'progress_percentage' => (int) round(($failedAgentNumber - 1) / 9 * 100),
        ]);

        $this->events->emitStatusChanged($case->id, (string) $oldStatus, CaseStatus::Halted->value);

        $this->events->emitPipelineHalted(
            $case->id,
            $failedAgentNumber,
            $haltReason,
            $completedAgents,
            $skippedAgents
        );

        Log::info("Pipeline halted at agent {$failedAgentNumber}", [
            'case_id' => $case->id,
            'halt_reason' => $haltReason,
            'skipped_agents' => $skippedAgents,
        ]);
    }

    /**
     * Halt the pipeline due to timeout.
     */
    protected function haltPipelineTimeout(LegalCase $case, int $currentAgent, array $completedAgents = []): void
    {
        $oldStatus = $case->status->value ?? $case->status;
        $skippedAgents = [];
        for ($i = $currentAgent + 1; $i <= 9; $i++) {
            $skippedAgents[] = $i;
            // Create skipped execution records
            AgentExecution::create([
                'case_id' => $case->id,
                'agent_number' => $i,
                'agent_name' => $this->phase2Agents[$i]->agentName(),
                'status' => AgentStatus::Skipped,
                'started_at' => now(),
                'completed_at' => now(),
            ]);
        }

        $case->update([
            'status' => CaseStatus::TimedOut,
            'halted_at' => now(),
            'halted_at_agent' => $currentAgent,
            'halt_reason' => 'pipeline_timeout',
            'current_agent' => $currentAgent,
            'progress_percentage' => (int) round(($currentAgent - 1) / 9 * 100),
        ]);

        $this->events->emitStatusChanged($case->id, (string) $oldStatus, CaseStatus::TimedOut->value);

        $this->events->emitPipelineHalted(
            $case->id,
            $currentAgent,
            'pipeline_timeout',
            $completedAgents,
            $skippedAgents
        );

        Log::info("Pipeline timed out at agent {$currentAgent}", [
            'case_id' => $case->id,
            'skipped_agents' => $skippedAgents,
        ]);
    }

    public function runPhase2(LegalCase $case, ?int $startFromAgent = null, string $openrouterApiKey = ''): void
    {
        $case->load(['documents', 'laws', 'requiredLaws', 'outputs']);
        $totalTokens = 0;
        $totalCost = 0.0;

        // Initialize retry budget max from config if not set
        if (!$case->retry_budget_max) {
            $case->update(['retry_budget_max' => $this->retryBudgetMax]);
        }

        // Determine starting agent: explicit param > case field > 1
        $resumeFrom = $startFromAgent ?? $case->resume_from_agent ?? 1;

        // Delete outputs from the resuming agent onward so stale data doesn't confuse the UI
        $this->deleteOutputsFrom($case, $resumeFrom);
        $case->refresh();

        $completedAgents = [];

        for ($i = 1; $i <= 9; $i++) {
            // Check pipeline timeout before starting each agent
            $timeoutCheck = $this->checkPipelineTimeout($case);
            if ($timeoutCheck['timed_out']) {
                $this->haltPipelineTimeout($case, $i - 1, $completedAgents);
                return;
            }

            // Skip completed agents when resuming
            if ($i < $resumeFrom) {
                $existingOutput = $case->outputs()->where('agent_number', $i)->exists();
                if ($existingOutput) {
                    Log::info("Skipping agent {$i} (resume mode — output exists)", ['case_id' => $case->id]);
                    $completedAgents[] = $i;
                    continue;
                }
            }

            $agent = $this->phase2Agents[$i];
            $case->update(['current_agent' => $i, 'progress_percentage' => (int) round(($i - 1) / 9 * 100)]);

            $exec = AgentExecution::create([
                'case_id' => $case->id,
                'agent_number' => $i,
                'agent_name' => $agent->agentName(),
                'status' => AgentStatus::InProgress,
                'started_at' => now(),
                'retry_count' => 0,
            ]);

            $this->events->emit($case->id, $i, $agent->agentName(), 'agent.started', []);

            $maxRetries = max(1, (int) config('legal.agent_max_retries', 3));
            $lastException = null;
            $agentSucceeded = false;
            $confidenceScore = null;
            $belowThreshold = false;
            $selfCorrectionExhausted = false;

            for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
                // Check retry budget before attempting retry (not for first attempt)
                if ($attempt > 0 && !$this->canUseRetryBudget($case)) {
                    Log::warning("Retry budget exhausted, halting pipeline", ['case_id' => $case->id]);
                    $this->haltPipeline($case, $i, 'retry_budget_exhausted', $completedAgents);
                    return;
                }

                try {
                    if ($attempt > 0) {
                        $exec->update(['retry_count' => $attempt, 'status' => AgentStatus::Retrying]);
                        // Consume retry budget for retries
                        $this->consumeRetryBudget($case);
                    }
                    if (!$agent->validateGate($case)) {
                        $missing = $this->gateValidator->validateGateForAgent($case, $i);
                        throw new \RuntimeException('Gate validation failed. Missing: ' . implode(', ', $missing));
                    }

                    $agentStart = microtime(true);
                    
                    // Execute with timeout protection
                    $executionResult = $this->executeWithTimeout($agent, $case);
                    
                    // Check if execution failed
                    if (!$executionResult['success']) {
                        $timeoutMsg = $executionResult['timed_out'] ?? false 
                            ? " (timeout after {$this->agentTimeoutSeconds}s)" 
                            : '';
                        throw new \RuntimeException($executionResult['error'] . $timeoutMsg);
                    }
                    
                    $result = $executionResult['result'];
                    $durationMs = (int) round((microtime(true) - $agentStart) * 1000);

                    $promptTokens = $result['prompt_tokens'] ?? 0;
                    $completionTokens = $result['completion_tokens'] ?? 0;
                    $tokens = $promptTokens + $completionTokens;
                    $cost = $this->costCalculator->calculateUsd($promptTokens, $completionTokens);

                    $totalTokens += $tokens;
                    $totalCost += $cost;

                    // Extract confidence score from result if available
                    $confidenceScore = $result['confidence_score'] ?? null;
                    $belowThreshold = $result['below_threshold'] ?? false;
                    $selfCorrectionExhausted = $result['self_correction_exhausted'] ?? false;

                    $exec->update([
                        'status' => AgentStatus::Completed,
                        'prompt_tokens' => $promptTokens,
                        'completion_tokens' => $completionTokens,
                        'total_tokens' => $tokens,
                        'cost_usd' => $cost,
                        'duration_ms' => $durationMs,
                        'completed_at' => now(),
                        'retry_count' => $attempt,
                        'confidence_score' => $confidenceScore,
                        'below_threshold' => $belowThreshold,
                        'self_correction_exhausted' => $selfCorrectionExhausted,
                    ]);

                    $correctionsCount = $result['corrections_count'] ?? 0;
                    $correctionDetails = $result['correction_details'] ?? [];

                    $exec->update([
                        'corrections_count' => $correctionsCount,
                        'correction_details' => !empty($correctionDetails) ? $correctionDetails : null,
                    ]);

                    $outputFiles = $case->outputs()->where('agent_number', $i)->pluck('filename')->toArray();

                    $this->events->emit($case->id, $i, $agent->agentName(), 'agent.completed', [
                        'metrics' => ['tokens_used' => $tokens, 'duration_ms' => $durationMs],
                        'corrections_count' => $correctionsCount,
                        'output_files' => $outputFiles,
                        'confidence_score' => $confidenceScore,
                        'below_threshold' => $belowThreshold,
                        'self_correction_exhausted' => $selfCorrectionExhausted,
                    ]);

                    // Emit low confidence warning if applicable
                    if ($belowThreshold) {
                        $threshold = config('legal.confidence_threshold', 0.70);
                        $this->events->emitLowConfidence($case->id, $i, $agent->agentName(), $confidenceScore, $threshold);
                    }

                    // Critical agent halt: agents 6, 8, 9 halt on self-correction exhaustion
                    if ($selfCorrectionExhausted && in_array($i, [6, 8, 9])) {
                        Log::warning("Critical agent {$i} exhausted self-correction — halting pipeline", [
                            'case_id' => $case->id,
                            'agent_number' => $i,
                        ]);
                        $exec->update([
                            'status' => AgentStatus::Completed,
                            'completed_at' => now(),
                        ]);
                        $this->haltPipeline($case, $i, 'critical_agent_self_correction_exhausted', array_merge($completedAgents, [$i]));
                        return;
                    }

                    // After Agent 9: post-process the final brief v2
                    if ($i === 9) {
                        $this->postProcessBriefV2($case);
                    }

                    $lastException = null;
                    $agentSucceeded = true;
                    $completedAgents[] = $i;
                    break;
                } catch (\Throwable $e) {
                    $lastException = $e;
                    Log::warning("Phase 2 agent {$i} attempt " . ($attempt + 1) . "/{$maxRetries} failed: " . $e->getMessage(), ['case_id' => $case->id]);
                }
            }

            if (!$agentSucceeded && $lastException !== null) {
                $errorMessage = $lastException->getMessage();
                $isTimeout = str_contains($errorMessage, 'timeout');

                Log::error("Phase 2 agent {$i} failed after {$maxRetries} retries, halting pipeline: " . $errorMessage, [
                    'case_id' => $case->id,
                    'agent_number' => $i,
                    'is_timeout' => $isTimeout,
                ]);

                // Update execution record with detailed error
                $exec->update([
                    'status' => AgentStatus::Failed,
                    'error_message' => $errorMessage,
                    'completed_at' => now(),
                    'retry_count' => $maxRetries - 1,
                ]);

                // Log detailed error to error_logs table
                ErrorLog::create([
                    'case_id' => $case->id,
                    'agent_execution_id' => $exec->id,
                    'agent_number' => $i,
                    'error_type' => $isTimeout ? 'agent_timeout' : 'agent_failed',
                    'error_details' => $errorMessage,
                    'fix_applied' => 'pending',
                ]);

                // Emit failure event to SSE
                $this->events->emitFailed($case->id, $i, $agent->agentName(), $errorMessage);

                $this->events->emit($case->id, $i, $agent->agentName(), 'agent.failed', [
                    'error' => $errorMessage,
                    'retry_count' => $maxRetries - 1,
                    'is_timeout' => $isTimeout,
                    'halted' => true,
                ]);

                // HALT THE PIPELINE - do not continue to next agent
                $this->haltPipeline($case, $i, $isTimeout ? 'agent_timeout' : 'agent_failure', $completedAgents);
                return;
            }
        }

        // Run quality gate on final brief v2
        $qualityGatePassed = $this->runQualityGate($case);

        // Check if any agent had below_threshold output
        $hasLowConfidence = $case->agentExecutions()->where('below_threshold', true)->exists();

        $finalStatus = (!$qualityGatePassed || $hasLowConfidence)
            ? CaseStatus::CompletedWithWarnings
            : CaseStatus::Phase2Completed;

        $oldStatus = $case->status->value ?? $case->status;
        $case->update([
            'current_agent' => 9,
            'progress_percentage' => 100,
            'total_tokens' => $case->total_tokens + $totalTokens,
            'total_cost_usd' => $case->total_cost_usd + $totalCost,
            'status' => $finalStatus,
            'completed_at' => now(),
        ]);

        $this->events->emitStatusChanged($case->id, (string) $oldStatus, $finalStatus->value);

        CaseMetrics::upsertForCase($case);

        $user = $case->user;
        $user->increment('total_tokens_consumed', $totalTokens);
        $user->update(['total_cost_usd' => $user->total_cost_usd + $totalCost]);

        // Auto-launch Phase 3 immediately — no manual approval or browser needed.
        $oldStatus = $case->status->value ?? $case->status;
        $case->update(['status' => CaseStatus::Phase3Pending, 'phase' => 3]);
        $this->events->emitStatusChanged($case->id, (string) $oldStatus, CaseStatus::Phase3Pending->value);
        \App\Jobs\ProcessPhase3Job::dispatch($case, $case->getPuterToken(), $openrouterApiKey);
    }

    /**
     * Delete all output files and records for agents >= $fromAgent.
     * Used when re-running the pipeline from a specific agent.
     */
    protected function deleteOutputsFrom(LegalCase $case, int $fromAgent): void
    {
        $outputs = $case->outputs()->where('agent_number', '>=', $fromAgent)->get();
        foreach ($outputs as $output) {
            if ($output->file_path && Storage::disk('local')->exists($output->file_path)) {
                Storage::disk('local')->delete($output->file_path);
            }
            $output->delete();
        }

        // Also delete agent execution records for these agents
        $case->agentExecutions()->where('agent_number', '>=', $fromAgent)->delete();

        // Clear resume field
        $case->update(['resume_from_agent' => null]);

        Log::info("Deleted outputs for agents {$fromAgent}-9", ['case_id' => $case->id]);
    }

    /**
     * Reset retry budget for case retry.
     */
    public function resetRetryBudget(LegalCase $case): void
    {
        $case->update([
            'retry_budget_used' => 0,
            'retry_budget_max' => $this->retryBudgetMax,
        ]);
    }

    /**
     * Post-process the Agent 9 brief (v2) using BriefPostProcessor.
     * Updates the saved output record with the cleaned content.
     */
    protected function postProcessBriefV2(LegalCase $case): void
    {
        $output = $case->outputs()->where('filename', '09_final_brief_v2.md')->first();
        if (!$output) {
            return;
        }

        $content = $output->content;
        if (empty(trim((string) $content)) && $output->file_path) {
            $full = Storage::disk('local')->path($output->file_path);
            if (file_exists($full)) {
                $content = file_get_contents($full);
            }
        }

        if (empty(trim((string) $content))) {
            return;
        }

        $cleaned = BriefPostProcessor::process((string) $content);

        // Update DB record and file
        $output->update(['content' => $cleaned]);
        if ($output->file_path) {
            Storage::disk('local')->put($output->file_path, $cleaned);
        }

        Log::info('BriefPostProcessor applied to 09_final_brief_v2.md', ['case_id' => $case->id]);
    }

    /**
     * Run quality gate on the final brief.
     * Returns true if quality gate passes, false if brief has issues.
     */
    protected function runQualityGate(LegalCase $case): bool
    {
        $output = $case->outputs()->where('filename', '09_final_brief_v2.md')->first();
        if (!$output) {
            Log::info('Quality gate: no brief v2 found, skipping', ['case_id' => $case->id]);
            return true; // No brief to gate — let it pass (will be gated in Phase 3)
        }

        $brief = (string) $output->content;
        if (empty(trim($brief)) && $output->file_path) {
            $full = Storage::disk('local')->path($output->file_path);
            if (file_exists($full)) {
                $brief = file_get_contents($full);
            }
        }

        if (empty(trim($brief))) {
            return true;
        }

        $allViolations = array_merge(
            OutputValidator::validateBriefStructure($brief),
            OutputValidator::validateArabicFinalBrief($brief),
            OutputValidator::validateNoEnglishLeak($brief),
        );

        if (!empty($allViolations)) {
            $summary = implode('; ', array_map(fn ($v) => "[{$v['type']}] {$v['detail']}", $allViolations));
            Log::warning('Quality gate FAILED for Phase 2 brief', [
                'case_id' => $case->id,
                'violations' => $summary,
            ]);
            return false;
        }

        Log::info('Quality gate PASSED for Phase 2 brief', ['case_id' => $case->id]);
        return true;
    }
}
