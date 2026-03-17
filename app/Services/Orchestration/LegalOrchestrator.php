<?php

namespace App\Services\Orchestration;

use App\Models\AgentExecution;
use App\Models\CaseMetrics;
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
use Illuminate\Support\Facades\Log;

class LegalOrchestrator
{
    /** @var array<int, \App\Services\Agents\BaseAgent> */
    protected array $phase2Agents = [];

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
    }

    public function runPhase2(LegalCase $case): void
    {
        $case->load(['documents', 'laws', 'requiredLaws', 'outputs']);
        $totalTokens = 0;
        $totalCost = 0.0;

        for ($i = 1; $i <= 9; $i++) {
            $agent = $this->phase2Agents[$i];
            $case->update(['current_agent' => $i, 'progress_percentage' => (int) round(($i - 1) / 9 * 100)]);

            $exec = AgentExecution::create([
                'case_id' => $case->id,
                'agent_number' => $i,
                'agent_name' => $agent->agentName(),
                'status' => \App\Enums\AgentStatus::InProgress,
                'started_at' => now(),
                'retry_count' => 0,
            ]);

            $this->events->emit($case->id, $i, $agent->agentName(), 'agent.started', []);

            $maxRetries = 3;
            $lastException = null;
            for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
                try {
                    if ($attempt > 0) {
                        $exec->update(['retry_count' => $attempt, 'status' => \App\Enums\AgentStatus::Retrying]);
                    }
                    if (!$agent->validateGate($case)) {
                        $missing = $this->gateValidator->validateGateForAgent($case, $i);
                        throw new \RuntimeException('Gate validation failed. Missing: ' . implode(', ', $missing));
                    }

                    $agentStart = microtime(true);
                    $result = $agent->execute($case);
                    $durationMs = (int) round((microtime(true) - $agentStart) * 1000);

                    $promptTokens = $result['prompt_tokens'] ?? 0;
                    $completionTokens = $result['completion_tokens'] ?? 0;
                    $tokens = $promptTokens + $completionTokens;
                    $cost = $this->costCalculator->calculateUsd($promptTokens, $completionTokens);

                    $totalTokens += $tokens;
                    $totalCost += $cost;

                    $exec->update([
                        'status' => \App\Enums\AgentStatus::Completed,
                        'prompt_tokens' => $promptTokens,
                        'completion_tokens' => $completionTokens,
                        'total_tokens' => $tokens,
                        'cost_usd' => $cost,
                        'duration_ms' => $durationMs,
                        'completed_at' => now(),
                        'retry_count' => $attempt,
                    ]);

                    $this->events->emit($case->id, $i, $agent->agentName(), 'agent.completed', [
                        'metrics' => ['tokens_used' => $tokens, 'duration_ms' => $durationMs],
                    ]);

                    $lastException = null;
                    break;
                } catch (\Throwable $e) {
                    $lastException = $e;
                    Log::warning("Phase 2 agent {$i} attempt " . ($attempt + 1) . "/{$maxRetries} failed: " . $e->getMessage(), ['case_id' => $case->id]);
                }
            }

            if ($lastException !== null) {
                Log::error("Phase 2 agent {$i} failed after {$maxRetries} retries: " . $lastException->getMessage(), ['case_id' => $case->id]);
                $exec->update([
                    'status' => \App\Enums\AgentStatus::Failed,
                    'error_message' => $lastException->getMessage(),
                    'completed_at' => now(),
                    'retry_count' => $maxRetries - 1,
                ]);
                $this->events->emit($case->id, $i, $agent->agentName(), 'agent.failed', ['content' => $lastException->getMessage()]);
                $case->update(['status' => \App\Enums\CaseStatus::Paused]);

                return;
            }
        }

        $case->update([
            'current_agent' => 9,
            'progress_percentage' => 100,
            'total_tokens' => $case->total_tokens + $totalTokens,
            'total_cost_usd' => $case->total_cost_usd + $totalCost,
            'status' => \App\Enums\CaseStatus::Phase2Completed,
            'completed_at' => now(),
        ]);

        CaseMetrics::upsertForCase($case);

        $user = $case->user;
        $user->increment('total_tokens_consumed', $totalTokens);
        $user->update(['total_cost_usd' => $user->total_cost_usd + $totalCost]);
    }

}
