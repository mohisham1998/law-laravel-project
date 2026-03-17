<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;

class DefenseStrategistAgent extends Phase2BaseAgent
{
    public function agentNumber(): int
    {
        return 7;
    }

    public function agentName(): string
    {
        return 'Defense Strategist';
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);
        $prompt = $this->promptBuilder->buildPromptForAgent(7, $context);
        $model = $case->model_used ?: config('openrouter.default_model');
        $result = $this->openRouter->complete($model, [
            ['role' => 'user', 'content' => $prompt . "\n\nExecute the Defense Strategist task. Output defense strategy in markdown."],
        ], 0.3);
        $filename = '07_defense_strategy.md';
        $this->saveOutput($case, $filename, $result['content'], 'markdown');
        return ['content' => $result['content'], 'filename' => $filename, 'prompt_tokens' => $result['prompt_tokens'], 'completion_tokens' => $result['completion_tokens']];
    }
}
