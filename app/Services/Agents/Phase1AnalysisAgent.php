<?php

namespace App\Services\Agents;

use App\Models\CaseOutput;
use App\Models\LegalCase;
use App\Models\RequiredLaw;
use App\Services\OpenRouter\OpenRouterService;
use App\Services\Orchestration\GateValidator;
use App\Services\Orchestration\PromptBuilder;
use Illuminate\Support\Facades\Storage;

class Phase1AnalysisAgent extends BaseAgent
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
        return 0;
    }

    public function agentName(): string
    {
        return 'Phase 1 Analysis';
    }

    public function validateGate(LegalCase $case): bool
    {
        return $this->gateValidator->validatePhase1Gate($case);
    }

    /**
     * @return array{content: string, required_laws: array<int, array{law_name: string, reason: string}>}
     */
    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);
        $prompt = $this->promptBuilder->buildPromptForAgent(0, $context);

        $model = $case->model_used ?: config('openrouter.default_model');
        $result = $this->openRouter->complete($model, [
            ['role' => 'user', 'content' => $prompt],
        ], 0.3, 150);

        $requiredLaws = $this->parseRequiredLaws($result['content']);
        foreach ($requiredLaws as $law) {
            RequiredLaw::create([
                'case_id' => $case->id,
                'law_name' => $law['law_name'],
                'reason' => $law['reason'],
                'is_uploaded' => false,
            ]);
        }

        $outputPath = "cases/{$case->id}/outputs/00_required_laws.md";
        Storage::disk('local')->put($outputPath, $result['content']);

        CaseOutput::create([
            'case_id' => $case->id,
            'agent_number' => 0,
            'filename' => '00_required_laws.md',
            'file_path' => $outputPath,
            'content_type' => 'markdown',
            'content' => $result['content'],
            'file_size' => strlen($result['content']),
        ]);

        return [
            'content' => $result['content'],
            'required_laws' => $requiredLaws,
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
        ];
    }

    protected function buildContext(LegalCase $case): string
    {
        $parts = ["## Intake\n\n{$case->intake_text}"];

        foreach ($case->documents as $doc) {
            $path = Storage::disk('local')->path($doc->file_path);
            if (file_exists($path)) {
                $content = file_get_contents($path);
                $parts[] = "## Document: {$doc->filename}\n\n" . mb_substr($content, 0, 50000);
            }
        }

        return implode("\n\n---\n\n", $parts);
    }

    /**
     * @return array<int, array{law_name: string, reason: string}>
     */
    protected function parseRequiredLaws(string $content): array
    {
        $laws = [];
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $content, $m)) {
            $json = json_decode(trim($m[1]), true);
            if (isset($json['required_laws']) && is_array($json['required_laws'])) {
                foreach ($json['required_laws'] as $item) {
                    if (!empty($item['law_name'] ?? '')) {
                        $laws[] = [
                            'law_name' => (string) $item['law_name'],
                            'reason' => (string) ($item['reason'] ?? 'Required for case analysis'),
                        ];
                    }
                }
            }
        }
        if (empty($laws)) {
            $laws[] = ['law_name' => 'نظام الإثبات', 'reason' => 'Fallback: نظام الإثبات السعودي مطلوب عادةً في القضايا'];
        }
        return $laws;
    }
}
