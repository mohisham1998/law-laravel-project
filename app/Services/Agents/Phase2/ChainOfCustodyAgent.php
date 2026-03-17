<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;

class ChainOfCustodyAgent extends Phase2BaseAgent
{
    public function agentNumber(): int
    {
        return 3;
    }

    public function agentName(): string
    {
        return 'Chain of Custody';
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);
        $prompt = $this->promptBuilder->buildPromptForAgent(3, $context);
        $model = $case->model_used ?: config('openrouter.default_model');
        $result = $this->openRouter->complete($model, [
            ['role' => 'user', 'content' => $prompt . "\n\nExecute the Chain of Custody task. Output JSON."],
        ], 0.3);
        $filename = '03_chain_of_custody.json';
        $this->saveOutput($case, $filename, $result['content'], 'json');
        return ['content' => $result['content'], 'filename' => $filename, 'prompt_tokens' => $result['prompt_tokens'], 'completion_tokens' => $result['completion_tokens']];
    }
}
