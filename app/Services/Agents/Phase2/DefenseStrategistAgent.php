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

    /**
     * Agent 7 builds the defense strategy.
     * Needs: statute-to-chunk map (what was matched), timeline (chronology),
     * procedural notes (jurisdiction/standing), lead plan (strategic guidance).
     * Does NOT need raw documents or the full law library (already indexed in Agent 3).
     */
    protected function requiredPriorOutputs(): ?array
    {
        return [
            '01_lead_plan.md',              // Strategic framework from Agent 1
            '04_timeline.json',             // Events timeline for risk assessment
            '05_procedural_notes.md',       // Jurisdiction, standing, limitations
            '06_statutes_map.jsonl',        // The confirmed statute-to-chunk matches
        ];
    }

    protected function needsDocuments(): bool
    {
        return false; // Works from structured prior outputs, not raw docs
    }

    protected function needsLawLibrary(): bool
    {
        return false; // All statutes already indexed in 03_statutes_index.jsonl
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);
        $prompt = $this->promptBuilder->buildPromptForAgent(7, $context);

        // Append structural instruction for output parsing
        $prompt .= "\n\n---\n## تعليمات هيكلة المخرجات\n\nأنتج المخرجات بالترتيب:\n- `---RISK_MATRIX---` ثم مصفوفة المخاطر بالعربية\n- `---DEFENSE_LAYERS---` ثم طبقات الدفاع الثلاث بالعربية\n- `---CHARGES_SCENARIOS---` ثم سيناريوهات التهم بصيغة JSON\n- `---MITIGATION---` ثم فرص التخفيف بالعربية";

        $result = $this->executeWithSelfCorrection($case, $prompt);

        $content = $result['content'] ?? '';

        // Parse sections
        $riskMatrix = $this->extractSection($content, 'RISK_MATRIX', 'DEFENSE_LAYERS');
        $defenseLayers = $this->extractSection($content, 'DEFENSE_LAYERS', 'CHARGES_SCENARIOS');
        $chargesScenarios = $this->extractSection($content, 'CHARGES_SCENARIOS', 'MITIGATION');
        $mitigation = $this->extractSection($content, 'MITIGATION', null);

        // Fallbacks
        if (empty(trim($riskMatrix))) $riskMatrix = $content;
        if (empty(trim($defenseLayers))) $defenseLayers = "# طبقات الدفاع\n\nلم يتم تحديد طبقات دفاع.";
        if (empty(trim($chargesScenarios))) $chargesScenarios = '[]';
        if (empty(trim($mitigation))) $mitigation = "# فرص التخفيف\n\nلم يتم تحديد فرص تخفيف.";

        // Clean JSON
        $chargesScenarios = preg_replace('/^```(?:json)?\s*/m', '', $chargesScenarios);
        $chargesScenarios = preg_replace('/\s*```\s*$/m', '', $chargesScenarios);
        $chargesScenarios = trim($chargesScenarios);

        $this->saveOutputTyped($case, '07_risk_matrix.md', $riskMatrix, 'markdown', 'primary');
        $this->saveOutputTyped($case, '07_defense_layers.md', $defenseLayers, 'markdown', 'secondary');
        $this->saveOutputTyped($case, '07_charges_scenarios.json', $chargesScenarios, 'json', 'secondary');
        $this->saveOutputTyped($case, '07_mitigation_opportunities.md', $mitigation, 'markdown', 'secondary');

        return [
            'content' => $result['content'],
            'filename' => '07_risk_matrix.md',
            'output_files' => ['07_risk_matrix.md', '07_defense_layers.md', '07_charges_scenarios.json', '07_mitigation_opportunities.md'],
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'corrections_count' => $result['corrections_count'] ?? 0,
        ];
    }

    protected function extractSection(string $content, string $startMarker, ?string $endMarker): string
    {
        $pattern = '/---' . preg_quote($startMarker, '/') . '---\s*(.*?)' .
                   ($endMarker ? '(?=---' . preg_quote($endMarker, '/') . '---)' : '$') . '/s';
        if (preg_match($pattern, $content, $m)) {
            return trim($m[1]);
        }
        return '';
    }
}
