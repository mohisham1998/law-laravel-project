<?php

namespace App\Jobs;

use App\Enums\CaseStatus;
use App\Models\CaseMetrics;
use App\Models\CaseOutput;
use App\Models\LegalCase;
use App\Services\Agents\Phase3\DevilsAdvocateAgent;
use App\Services\Agents\Phase3\JudgeAgent;
use App\Services\Cost\CostCalculator;
use App\Services\Cost\TokenTracker;
use App\Services\Orchestration\GateValidator;
use App\Services\Orchestration\PromptBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPhase3Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public LegalCase $case)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $case = $this->case;

        try {
            $case->update([
                'status' => CaseStatus::Phase3Processing,
                'phase' => 3,
                'current_agent' => 10,
            ]);

            $promptBuilder = app(PromptBuilder::class);
            $gateValidator = app(GateValidator::class);
            $openRouter = app(\App\Services\OpenRouter\OpenRouterService::class);
            $tokenTracker = app(TokenTracker::class);
            $costCalc = app(CostCalculator::class);

            $judge = new JudgeAgent($promptBuilder, $gateValidator, $openRouter);
            $result = $judge->execute($case);
            $costUsd = $costCalc->calculateUsd($result['prompt_tokens'], $result['completion_tokens']);
            $tokenTracker->addToCase($case, $result['prompt_tokens'], $result['completion_tokens']);
            $tokenTracker->addToUser($case->user, $result['prompt_tokens'], $result['completion_tokens']);
            $case->increment('total_cost_usd', $costUsd);
            $case->user()->increment('total_cost_usd', $costUsd);

            CaseOutput::create([
                'case_id' => $case->id,
                'agent_number' => 10,
                'filename' => $result['filename'],
                'file_path' => "cases/{$case->id}/outputs/{$result['filename']}",
                'content_type' => 'markdown',
                'content' => $result['content'],
                'file_size' => strlen($result['content']),
            ]);

            $case->update(['current_agent' => 11]);

            $advocate = new DevilsAdvocateAgent($promptBuilder, $gateValidator, $openRouter);
            $result = $advocate->execute($case);
            $costUsd = $costCalc->calculateUsd($result['prompt_tokens'], $result['completion_tokens']);
            $tokenTracker->addToCase($case, $result['prompt_tokens'], $result['completion_tokens']);
            $tokenTracker->addToUser($case->user, $result['prompt_tokens'], $result['completion_tokens']);
            $case->increment('total_cost_usd', $costUsd);
            $case->user()->increment('total_cost_usd', $costUsd);

            CaseOutput::create([
                'case_id' => $case->id,
                'agent_number' => 11,
                'filename' => $result['filename'],
                'file_path' => "cases/{$case->id}/outputs/{$result['filename']}",
                'content_type' => 'markdown',
                'content' => $result['content'],
                'file_size' => strlen($result['content']),
            ]);

            $case->update([
                'status' => CaseStatus::Phase3Completed,
                'phase' => 3,
                'progress_percentage' => 100,
                'current_agent' => null,
                'completed_at' => now(),
            ]);

            CaseMetrics::upsertForCase($case->fresh(['agentExecutions']));
        } catch (\Throwable $e) {
            Log::error("Phase 3 failed for case {$case->id}: " . $e->getMessage(), ['exception' => $e]);
            $case->update(['status' => CaseStatus::Failed]);
            throw $e;
        }
    }
}
