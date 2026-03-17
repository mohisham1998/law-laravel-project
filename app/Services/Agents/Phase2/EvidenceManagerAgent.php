<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;

class EvidenceManagerAgent extends Phase2BaseAgent
{
    public function agentNumber(): int
    {
        return 2;
    }

    public function agentName(): string
    {
        return 'Evidence Manager';
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);
        $prompt = $this->promptBuilder->buildPromptForAgent(2, $context);
        $model = $case->model_used ?: config('openrouter.default_model');
        $result = $this->openRouter->complete($model, [
            ['role' => 'user', 'content' => $prompt . "\n\nExecute the Evidence Manager task. Output a JSON evidence index."],
        ], 0.3);
        $filename = '02_evidence_index.json';
        $this->saveOutput($case, $filename, $result['content'], 'json');
        return ['content' => $result['content'], 'filename' => $filename, 'prompt_tokens' => $result['prompt_tokens'], 'completion_tokens' => $result['completion_tokens']];
    }
}
