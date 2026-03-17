<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;

class TimelineExtractorAgent extends Phase2BaseAgent
{
    public function agentNumber(): int
    {
        return 4;
    }

    public function agentName(): string
    {
        return 'Timeline Extractor';
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);
        $prompt = $this->promptBuilder->buildPromptForAgent(4, $context);
        $model = $case->model_used ?: config('openrouter.default_model');
        $result = $this->openRouter->complete($model, [
            ['role' => 'user', 'content' => $prompt . "\n\nExecute the Timeline Extractor task. Output JSON timeline."],
        ], 0.3);
        $filename = '04_timeline.json';
        $this->saveOutput($case, $filename, $result['content'], 'json');
        return ['content' => $result['content'], 'filename' => $filename, 'prompt_tokens' => $result['prompt_tokens'], 'completion_tokens' => $result['completion_tokens']];
    }
}
