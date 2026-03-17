<?php

namespace App\Services\Cost;

use App\Models\AgentExecution;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TokenTracker
{
    public function addToCase(LegalCase $case, int $promptTokens, int $completionTokens): void
    {
        $total = $promptTokens + $completionTokens;
        $case->increment('total_tokens', $total);
    }

    public function addToUser(User $user, int $promptTokens, int $completionTokens): void
    {
        $total = $promptTokens + $completionTokens;
        $user->increment('total_tokens_consumed', $total);
    }

    public function recordExecution(AgentExecution $execution, int $promptTokens, int $completionTokens): void
    {
        $execution->update([
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $promptTokens + $completionTokens,
        ]);
    }
}
