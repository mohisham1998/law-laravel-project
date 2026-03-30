<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;
use App\Services\ErrorMemoryService;

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

    /**
     * Agent 1 is the first agent — no prior outputs exist yet.
     * It only needs intake + documents + law library to build the strategic plan.
     */
    protected function requiredPriorOutputs(): ?array
    {
        return []; // No prior agent outputs needed
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);

        // Read error memory for lessons learned
        $errorMemory = app(ErrorMemoryService::class);
        $errors = $errorMemory->read($case->id);
        $errorSection = !empty($errors) ? "\n\n## سجل الأخطاء السابقة\n\n" . mb_substr($errors, 0, 3000) : '';

        $prompt = $this->promptBuilder->buildPromptForAgent(1, $context);
        if (!empty($errorSection)) {
            $prompt .= $errorSection;
        }

        $result = $this->executeWithSelfCorrection($case, $prompt);

        // Save primary output
        $this->saveOutputTyped($case, '01_lead_plan.md', $result['content'], 'markdown', 'primary');

        // Generate and save acceptance criteria JSON
        $criteria = json_encode([
            'min_confidence' => 0.70,
            'dual_citations_required' => true,
            'max_unsupported_paragraphs' => 0,
            'defense_tiers' => ['primary', 'alternative', 'consequential'],
            'preamble_required' => true,
            'no_abrogated_articles' => true,
            'ai_erasure_required' => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->saveOutputTyped($case, '01_acceptance_criteria.json', $criteria, 'json', 'secondary');

        return [
            'content' => $result['content'],
            'filename' => '01_lead_plan.md',
            'output_files' => ['01_lead_plan.md', '01_acceptance_criteria.json'],
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'corrections_count' => $result['corrections_count'] ?? 0,
        ];
    }
}
