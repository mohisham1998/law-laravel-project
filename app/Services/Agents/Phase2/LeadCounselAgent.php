<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;

class LeadCounselAgent extends Phase2BaseAgent
{
    public function agentNumber(): int
    {
        return 1;
    }

    public function agentName(): string
    {
        return 'Lead Counsel';
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);
        $prompt = $this->promptBuilder->buildPromptForAgent(1, $context);
        $model = $case->model_used ?: config('openrouter.default_model');
        $result = $this->openRouter->complete($model, [
            ['role' => 'user', 'content' => $prompt . "\n\nExecute the Lead Counsel task per SKILL.md. Output a strategic plan in markdown."],
        ], 0.3);
        $filename = '01_lead_plan.md';
        $this->saveOutput($case, $filename, $result['content'], 'markdown');
        return ['content' => $result['content'], 'filename' => $filename, 'prompt_tokens' => $result['prompt_tokens'], 'completion_tokens' => $result['completion_tokens']];
    }
}
