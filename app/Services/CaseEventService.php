<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class CaseEventService
{
    public function emit(string $caseId, int $agentNumber, string $agentName, string $eventType, array $data = []): void
    {
        $payload = array_merge([
            'case_id' => $caseId,
            'agent_number' => $agentNumber,
            'agent_name' => $agentName,
            'event_type' => $eventType,
            'timestamp' => now()->toISOString(),
        ], $data);

        Redis::rpush("case:{$caseId}:events", json_encode($payload));
    }
}
