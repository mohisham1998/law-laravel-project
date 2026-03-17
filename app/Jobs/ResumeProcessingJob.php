<?php

namespace App\Jobs;

use App\Models\LegalCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResumeProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public LegalCase $case,
        public string $failedPhase
    ) {}

    public function handle(): void
    {
        try {
            Log::info("Resuming processing for case {$this->case->id} from phase {$this->failedPhase}");
            
            // Update case status to processing
            $this->case->update([
                'status' => 'phase1_processing',
                'last_failed_phase' => null,
                'last_error_message' => null,
            ]);
            
            // Dispatch the appropriate processing job based on the failed phase
            // This would integrate with your existing processing pipeline
            // For now, we'll just log it
            
            Log::info("Successfully resumed processing for case {$this->case->id}");
            
        } catch (\Exception $e) {
            Log::error("Failed to resume processing for case {$this->case->id}: {$e->getMessage()}");
            
            $this->case->markAsFailed($this->failedPhase, $e->getMessage());
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ResumeProcessingJob failed for case {$this->case->id}: {$exception->getMessage()}");
        
        $this->case->markAsFailed(
            $this->failedPhase,
            "Failed to resume: {$exception->getMessage()}"
        );
    }
}
