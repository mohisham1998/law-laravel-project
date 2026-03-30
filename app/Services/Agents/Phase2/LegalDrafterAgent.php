<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;
use App\Services\ErrorMemoryService;
use App\Services\Orchestration\OutputValidator;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Agent 8 drafts the final legal brief.
     * Needs all the structured analysis outputs to write the document,
     * but NOT the raw chunks or the full law library (all indexed already).
     */
    protected function requiredPriorOutputs(): ?array
    {
        return [
            '01_lead_plan.md',              // Strategic framework & acceptance criteria
            '03_statutes_index.jsonl',      // Statute registry for accurate citations
            '04_timeline.md',               // Human-readable timeline narrative
            '05_issues_to_statutes.md',     // Legal issues mapped to statutes
            '05_procedural_notes.md',       // Procedural matters
            '06_statutes_map.jsonl',        // Confirmed chunk-to-statute matches (CASE/LAW refs)
            '06_accepted_matches.md',       // Accepted statute matches narrative
            '07_defense_layers.md',         // Three-tier defense structure
            '07_risk_matrix.md',            // Risk assessment per claim
            '07_mitigation_opportunities.md', // Mitigation strategies
        ];
    }

    protected function needsDocuments(): bool
    {
        return false; // Drafting from structured analysis, not raw docs
    }

    protected function needsLawLibrary(): bool
    {
        return false; // All statutes already indexed by Agent 3
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);

        // Read error memory
        $errorMemory = app(ErrorMemoryService::class);
        $errors = $errorMemory->read($case->id);
        $errorSection = !empty($errors) ? "\n\n## سجل الأخطاء السابقة\n\n" . mb_substr($errors, 0, 3000) : '';

        $prompt = $this->promptBuilder->buildPromptForAgent(8, $context);
        if (!empty($errorSection)) {
            $prompt .= $errorSection;
        }

        // Append structural instruction for output parsing
        $prompt .= "\n\n---\n## تعليمات هيكلة المخرجات\n\nبعد المذكرة، أضف:\n- `---DEFENSE_ARGUMENTS---` ثم ملخص حجج الدفاع بالعربية\n- `---ARGUMENTS_INDEX---` ثم فهرس الحجج بصيغة JSON";

        $result = $this->executeWithSelfCorrection($case, $prompt);

        $content = $result['content'] ?? '';

        // Extract main brief (before markers)
        $briefContent = $content;
        $defenseArgs = '';
        $argsIndex = '[]';

        $defensePos = strpos($content, '---DEFENSE_ARGUMENTS---');
        if ($defensePos !== false) {
            $briefContent = trim(substr($content, 0, $defensePos));
            $rest = substr($content, $defensePos + strlen('---DEFENSE_ARGUMENTS---'));

            $indexPos = strpos($rest, '---ARGUMENTS_INDEX---');
            if ($indexPos !== false) {
                $defenseArgs = trim(substr($rest, 0, $indexPos));
                $argsIndex = trim(substr($rest, $indexPos + strlen('---ARGUMENTS_INDEX---')));
            } else {
                $defenseArgs = trim($rest);
            }
        }

        // Clean JSON
        $argsIndex = preg_replace('/^```(?:json)?\s*/m', '', $argsIndex);
        $argsIndex = preg_replace('/\s*```\s*$/m', '', $argsIndex);
        $argsIndex = trim($argsIndex);

        $this->saveOutputTyped($case, '08_final_brief.md', $briefContent, 'markdown', 'primary');
        $this->saveOutputTyped($case, '08_defense_arguments.md', $defenseArgs ?: "# حجج الدفاع\n\nمضمّنة في المذكرة الرئيسية.", 'markdown', 'secondary');
        $this->saveOutputTyped($case, '08_arguments_index.json', $argsIndex, 'json', 'secondary');

        return [
            'content' => $result['content'],
            'filename' => '08_final_brief.md',
            'output_files' => ['08_final_brief.md', '08_defense_arguments.md', '08_arguments_index.json'],
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'corrections_count' => $result['corrections_count'] ?? 0,
        ];
    }

    protected function validateOutput(string $output, LegalCase $case): array
    {
        $violations = parent::validateOutput($output, $case);

        // Extract the brief portion (before section markers)
        $briefContent = $output;
        $defensePos = strpos($output, '---DEFENSE_ARGUMENTS---');
        if ($defensePos !== false) {
            $briefContent = trim(substr($output, 0, $defensePos));
        }

        // T034: Validate brief structure (8 mandatory sections)
        $violations = array_merge($violations, OutputValidator::validateBriefStructure($briefContent));

        // T033: Validate brief citations against accepted statute matches
        $statutesMap = $this->loadPriorOutput($case, '06_statutes_map.jsonl');
        if (!empty($statutesMap)) {
            $violations = array_merge($violations, OutputValidator::validateBriefCitations($briefContent, $statutesMap));
        }

        // Check for residual internal markers (Agent 8 must write pure Arabic prose — no CASE:/LAW: markers)
        if (preg_match('/(?:LAW|CASE):[A-Z0-9_]+/i', $briefContent)) {
            $violations[] = [
                'type' => 'residual_internal_marker',
                'detail' => 'Brief must NOT contain CASE:/LAW: markers — write all citations as Arabic prose',
                'lesson' => 'يجب كتابة جميع الاستشهادات بالعربية الفصحى النثرية — لا تستخدم علامات CASE أو LAW',
            ];
        }

        // Check for markdown tables (not allowed)
        if (preg_match('/\|[-:]+\|/', $briefContent)) {
            $violations[] = [
                'type' => 'format_violation',
                'detail' => 'Brief must not contain markdown tables',
                'lesson' => 'لا تستخدم جداول Markdown في المذكرة',
            ];
        }

        return $violations;
    }

    /**
     * Load a prior agent's output content by filename.
     */
    private function loadPriorOutput(LegalCase $case, string $filename): string
    {
        $output = $case->outputs()->where('filename', $filename)->first();
        if (!$output) {
            return '';
        }
        $content = $output->content;
        if ($content === null && $output->file_path && Storage::disk('local')->exists($output->file_path)) {
            $content = Storage::disk('local')->get($output->file_path);
        }
        return (string) $content;
    }
}
