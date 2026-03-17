<?php

namespace Tests\Unit\Jobs;

use App\Enums\CaseStatus;
use App\Jobs\ResumeProcessingJob;
use App\Models\LegalCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ResumeProcessingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_updates_case_status_and_clears_failure(): void
    {
        $case = LegalCase::factory()->create([
            'status' => CaseStatus::Failed,
            'last_failed_phase' => 'phase1',
            'last_error_message' => 'Previous error',
        ]);

        $job = new ResumeProcessingJob($case, 'phase1');
        $job->handle();

        $case->refresh();
        $this->assertNull($case->last_failed_phase);
        $this->assertNull($case->last_error_message);
    }

    public function test_failed_method_marks_case_on_throwable(): void
    {
        $case = LegalCase::factory()->create([
            'status' => CaseStatus::Failed,
            'last_failed_phase' => 'phase1',
        ]);

        $job = new ResumeProcessingJob($case, 'phase1');
        $job->failed(new \RuntimeException('Simulated failure'));

        $case->refresh();
        $this->assertEquals(CaseStatus::Failed, $case->status);
        $this->assertStringContainsString('Simulated failure', $case->last_error_message);
    }
}
