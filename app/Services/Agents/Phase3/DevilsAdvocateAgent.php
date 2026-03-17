<?php

namespace App\Services\Agents\Phase3;

use App\Models\LegalCase;
use App\Services\Agents\BaseAgent;
use App\Services\OpenRouter\OpenRouterService;
use App\Services\Orchestration\GateValidator;
use App\Services\Orchestration\PromptBuilder;
use Illuminate\Support\Facades\Storage;

class DevilsAdvocateAgent extends BaseAgent
{
    public function __construct(
        PromptBuilder $promptBuilder,
        GateValidator $gateValidator,
        protected OpenRouterService $openRouter,
    ) {
        parent::__construct($promptBuilder, $gateValidator);
    }

    public function agentNumber(): int
    {
        return 11;
    }

    public function agentName(): string
    {
        return "Devil's Advocate";
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);
        $prompt = $this->promptBuilder->buildPromptForAgent(11, $context);

        $model = $case->model_used ?: config('openrouter.default_model');
        $result = $this->openRouter->complete($model, [
            ['role' => 'user', 'content' => $prompt . "\n\nExecute the Devil's Advocate challenge task per SKILL.md. Output markdown."],
        ], 0.4);

        $filename = '11_devils_advocate_challenge.md';
        $path = "cases/{$case->id}/outputs/{$filename}";
        Storage::disk('local')->put($path, $result['content']);

        return [
            'content' => $result['content'],
            'filename' => $filename,
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
        ];
    }

    protected function buildContext(LegalCase $case): string
    {
        $outputs = $case->outputs()->orderBy('agent_number')->get();
        $parts = [];
        foreach ($outputs as $o) {
            $content = $o->content;
            if ($content === null && $o->file_path) {
                $full = Storage::disk('local')->path($o->file_path);
                $content = file_exists($full) ? file_get_contents($full) : '';
            }
            $parts[] = "## {$o->filename}\n\n" . mb_substr((string) $content, 0, 30000);
        }
        return implode("\n\n---\n\n", $parts) ?: $case->intake_text;
    }
}
