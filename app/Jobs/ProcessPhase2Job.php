<?php

namespace App\Jobs;

use App\Enums\CaseStatus;
use App\Models\LegalCase;
use App\Services\Orchestration\LegalOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPhase2Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public LegalCase $case)
    {
        $this->onQueue('default');
    }

    public function handle(LegalOrchestrator $orchestrator): void
    {
        $case = $this->case;

        try {
            $case->update([
                'status' => CaseStatus::Phase2Processing,
                'phase' => 2,
                'started_at' => now(),
            ]);

            $orchestrator->runPhase2($case);
        } catch (\Throwable $e) {
            Log::error("Phase 2 failed for case {$case->id}: " . $e->getMessage(), ['exception' => $e]);
            $case->update(['status' => CaseStatus::Failed]);
            throw $e;
        }
    }
}
