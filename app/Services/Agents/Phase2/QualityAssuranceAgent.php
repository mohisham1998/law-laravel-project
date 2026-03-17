<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;

class QualityAssuranceAgent extends Phase2BaseAgent
{
    public function agentNumber(): int
    {
        return 9;
    }

    public function agentName(): string
    {
        return 'Quality Assurance';
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);
        $prompt = $this->promptBuilder->buildPromptForAgent(9, $context);
        $model = $case->model_used ?: config('openrouter.default_model');
        $result = $this->openRouter->complete($model, [
            ['role' => 'user', 'content' => $prompt . "\n\nExecute the Quality Assurance task. Output QA report and final brief v2 in markdown."],
        ], 0.2);
        $filename = '09_qa_report.md';
        $this->saveOutput($case, $filename, $result['content'], 'markdown');
        return ['content' => $result['content'], 'filename' => $filename, 'prompt_tokens' => $result['prompt_tokens'], 'completion_tokens' => $result['completion_tokens']];
    }
}
