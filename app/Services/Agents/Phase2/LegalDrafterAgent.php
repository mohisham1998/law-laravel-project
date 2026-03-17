<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;

class LegalDrafterAgent extends Phase2BaseAgent
{
    public function agentNumber(): int
    {
        return 8;
    }

    public function agentName(): string
    {
        return 'Legal Drafter';
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);
        $prompt = $this->promptBuilder->buildPromptForAgent(8, $context);
        $model = $case->model_used ?: config('openrouter.default_model');
        $result = $this->openRouter->complete($model, [
            ['role' => 'user', 'content' => $prompt . "\n\nExecute the Legal Drafter task. Output the legal brief v2 in markdown. Use CASE:{} and LAW:{} references."],
        ], 0.3);
        $filename = '08_legal_brief_v2.md';
        $this->saveOutput($case, $filename, $result['content'], 'markdown');
        return ['content' => $result['content'], 'filename' => $filename, 'prompt_tokens' => $result['prompt_tokens'], 'completion_tokens' => $result['completion_tokens']];
    }
}
