<?php

namespace Tests\Unit\Models;

use App\Models\ErrorLog;
use App\Models\LegalCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_case(): void
    {
        $errorLog = ErrorLog::factory()->create();
        $this->assertInstanceOf(LegalCase::class, $errorLog->case);
    }

    public function test_fillable_attributes(): void
    {
        $data = [
            'case_id' => LegalCase::factory()->create()->id,
            'agent_execution_id' => \App\Models\AgentExecution::factory()->create()->id,
            'agent_number' => 1,
            'error_type' => 'api_timeout',
            'error_details' => 'Connection timeout',
            'fix_applied' => 'Retried request',
        ];
        $errorLog = ErrorLog::create($data);

        $this->assertEquals($data['error_details'], $errorLog->error_details);
        $this->assertEquals($data['error_type'], $errorLog->error_type->value);
    }
}
