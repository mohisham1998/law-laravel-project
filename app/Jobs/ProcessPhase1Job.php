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
use App\Services\OpenRouter\OpenRouterService;
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

    public function __construct(
        public LegalCase $case
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
            $events->emit($case->id, 0, 'تحليل القضية', 'agent.started', []);

            $promptBuilder = app(PromptBuilder::class);
            $openRouter = app(OpenRouterService::class);
            $agent = new Phase1AnalysisAgent($promptBuilder, $gateValidator, $openRouter);

            $result = $agent->execute($case);

            $events->emit($case->id, 0, 'تحليل القضية', 'agent.completed', [
                'metrics' => [
                    'tokens_used' => ($result['prompt_tokens'] ?? 0) + ($result['completion_tokens'] ?? 0),
                    'duration_ms' => $result['duration_ms'] ?? 0,
                ],
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
        } catch (\Throwable $e) {
            Log::error("Phase 1 failed for case {$case->id}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            $case->update(['status' => CaseStatus::Failed]);
            throw $e;
        }
    }
}
