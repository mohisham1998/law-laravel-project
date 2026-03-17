<?php

namespace Tests\Unit\Models;

use App\Enums\CaseStatus;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $case->user);
        $this->assertEquals($user->id, $case->user->id);
    }

    public function test_has_documents_relationship(): void
    {
        $case = LegalCase::factory()->create();
        $this->assertCount(0, $case->documents);

        $case->documents()->create([
            'filename' => 'test.txt',
            'file_path' => 'cases/' . $case->id . '/documents/test.txt',
            'file_size' => 100,
            'mime_type' => 'text/plain',
            'encoding' => 'UTF-8',
        ]);

        $this->assertCount(1, $case->fresh()->documents);
    }

    public function test_scope_failed_returns_only_failed_cases(): void
    {
        LegalCase::factory()->create(['status' => CaseStatus::Failed]);
        LegalCase::factory()->create(['status' => CaseStatus::Phase1Pending]);
        LegalCase::factory()->create(['status' => CaseStatus::Phase3Completed]);

        $failed = LegalCase::failed()->get();
        $this->assertCount(1, $failed);
        $this->assertEquals(CaseStatus::Failed, $failed->first()->status);
    }

    public function test_can_retry_returns_true_when_failed_with_phase(): void
    {
        $case = LegalCase::factory()->create([
            'status' => CaseStatus::Failed,
            'last_failed_phase' => 'phase1',
            'last_error_message' => 'Test error',
        ]);

        $this->assertTrue($case->canRetry());
    }

    public function test_can_retry_returns_false_when_not_failed(): void
    {
        $case = LegalCase::factory()->create([
            'status' => CaseStatus::Phase1Pending,
            'last_failed_phase' => 'phase1',
        ]);

        $this->assertFalse($case->canRetry());
    }

    public function test_mark_as_failed_updates_status(): void
    {
        $case = LegalCase::factory()->create(['status' => CaseStatus::Phase1Processing]);
        $case->markAsFailed('phase1', 'API timeout');

        $case->refresh();
        $this->assertEquals(CaseStatus::Failed, $case->status);
        $this->assertEquals('phase1', $case->last_failed_phase);
        $this->assertEquals('API timeout', $case->last_error_message);
    }

    public function test_clear_failure_removes_failure_data(): void
    {
        $case = LegalCase::factory()->create([
            'status' => CaseStatus::Failed,
            'last_failed_phase' => 'phase1',
            'last_error_message' => 'Error',
        ]);
        $case->clearFailure();

        $case->refresh();
        $this->assertNull($case->last_failed_phase);
        $this->assertNull($case->last_error_message);
    }
}
