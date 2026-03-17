<?php

namespace Tests\Feature\Enums;

use App\Enums\CaseStatus;
use PHPUnit\Framework\TestCase;

class CaseStatusTest extends TestCase
{
    public function test_phase1_pending_value(): void
    {
        $this->assertEquals('phase1_pending', CaseStatus::Phase1Pending->value);
    }

    public function test_failed_value(): void
    {
        $this->assertEquals('failed', CaseStatus::Failed->value);
    }

    public function test_all_statuses_have_values(): void
    {
        foreach (CaseStatus::cases() as $status) {
            $this->assertNotEmpty($status->value);
            $this->assertIsString($status->value);
        }
    }
}
