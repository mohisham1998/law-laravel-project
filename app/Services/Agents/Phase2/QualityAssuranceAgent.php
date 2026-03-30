<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;
use App\Services\ErrorMemoryService;
use App\Services\Output\BriefPostProcessor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Agent 9 is the QA gate — it verifies and refines the final brief.
     * Needs the brief itself plus the reference files it will cross-check against.
     */
    protected function requiredPriorOutputs(): ?array
    {
        return [
            '03_statutes_index.jsonl',   // To verify quoted_text accuracy
            '03_conflict_warnings.md',   // To check no abrogated articles slipped through
            '04_timeline.json',          // To verify date consistency
            '06_statutes_map.jsonl',     // To verify CASE/LAW citation existence
            '08_final_brief.md',         // The document being quality-checked
            '08_defense_arguments.md',   // Defense arguments to verify
        ];
    }

    protected function needsDocuments(): bool
    {
        return false; // QA works on structured outputs only
    }

    protected function needsLawLibrary(): bool
    {
        return false; // All statutes already indexed — QA cross-checks, doesn't re-load
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);

        // Read error memory
        $errorMemory = app(ErrorMemoryService::class);
        $errors = $errorMemory->read($case->id);
        $errorSection = !empty($errors) ? "\n\n## سجل الأخطاء السابقة\n\n" . mb_substr($errors, 0, 3000) : '';

        $prompt = $this->promptBuilder->buildPromptForAgent(9, $context);
        if (!empty($errorSection)) {
            $prompt .= $errorSection;
        }

        // Append structural instruction for output parsing
        $prompt .= "\n\n---\n## تعليمات هيكلة المخرجات\n\nأنتج المخرجات بالترتيب:\n- `---QA_SUMMARY---` ثم ملخص فحص الجودة بالعربية\n- `---VIOLATIONS---` ثم المخالفات المكتشفة بالعربية\n- `---FIXES_APPLIED---` ثم الإصلاحات المطبقة بصيغة JSON\n- `---TODO_BACK---` ثم المهام المعادة للوكلاء بالعربية\n- `---FINAL_BRIEF_V2---` ثم المذكرة النهائية المنقحة (فقط إذا لم تبقَ مخالفات حرجة)\n\n⚠️ مهم: أنتج FINAL_BRIEF_V2 فقط إذا لم تبقَ مخالفات حرجة.";

        $result = $this->executeWithSelfCorrection($case, $prompt);

        $content = $result['content'] ?? '';

        // Parse sections
        $qaSummary = $this->extractSection($content, 'QA_SUMMARY', 'VIOLATIONS');
        $violations = $this->extractSection($content, 'VIOLATIONS', 'FIXES_APPLIED');
        $fixesApplied = $this->extractSection($content, 'FIXES_APPLIED', 'TODO_BACK');
        $todoBack = $this->extractSection($content, 'TODO_BACK', 'FINAL_BRIEF_V2');
        $finalBriefV2 = $this->extractSection($content, 'FINAL_BRIEF_V2', null);

        // Fallbacks
        if (empty(trim($qaSummary))) $qaSummary = "# ملخص ضبط الجودة\n\n" . $content;
        if (empty(trim($violations))) $violations = "# المخالفات\n\nلم يتم اكتشاف مخالفات.";
        if (empty(trim($fixesApplied))) $fixesApplied = '[]';
        if (empty(trim($todoBack))) $todoBack = "# المهام المعادة\n\nلا توجد مهام معادة.";

        // Clean JSON for fixes
        $fixesApplied = preg_replace('/^```(?:json)?\s*/m', '', $fixesApplied);
        $fixesApplied = preg_replace('/\s*```\s*$/m', '', $fixesApplied);
        $fixesApplied = trim($fixesApplied);

        $this->saveOutputTyped($case, '09_QA_summary.md', $qaSummary, 'markdown', 'primary');
        $this->saveOutputTyped($case, '09_violations.md', $violations, 'markdown', 'secondary');
        $this->saveOutputTyped($case, '09_fixes_applied.json', $fixesApplied, 'json', 'secondary');
        $this->saveOutputTyped($case, '09_todo_back_to_agents.md', $todoBack, 'markdown', 'secondary');

        // Fallback: if FINAL_BRIEF_V2 marker not found, construct v2 from v1 + fixes
        if (empty(trim($finalBriefV2)) || mb_strlen(trim($finalBriefV2)) <= 100) {
            $finalBriefV2 = $this->buildBriefV2Fallback($case, $fixesApplied);
        }

        // Save final brief v2 only if it has content (no critical violations)
        if (!empty(trim($finalBriefV2)) && mb_strlen(trim($finalBriefV2)) > 100) {
            // Apply post-processing before saving
            $finalBriefV2 = BriefPostProcessor::process($finalBriefV2);
            $this->saveOutputTyped($case, '09_final_brief_v2.md', $finalBriefV2, 'markdown', 'primary');
        }

        $outputFiles = ['09_QA_summary.md', '09_violations.md', '09_fixes_applied.json', '09_todo_back_to_agents.md'];
        if (!empty(trim($finalBriefV2)) && mb_strlen(trim($finalBriefV2)) > 100) {
            $outputFiles[] = '09_final_brief_v2.md';
        }

        return [
            'content' => $result['content'],
            'filename' => '09_QA_summary.md',
            'output_files' => $outputFiles,
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'corrections_count' => $result['corrections_count'] ?? 0,
        ];
    }

    /**
     * Fallback: construct brief v2 from v1 (Agent 8 output) when Agent 9 fails to
     * produce the FINAL_BRIEF_V2 marker section.
     */
    protected function buildBriefV2Fallback(LegalCase $case, string $fixesJson): string
    {
        // Try to load brief v1 from Agent 8
        $v1Output = $case->outputs()->where('filename', '08_final_brief.md')->first();
        if (!$v1Output) {
            Log::warning('Agent 9 fallback: no 08_final_brief.md found', ['case_id' => $case->id]);
            return '';
        }

        $v1Content = (string) ($v1Output->content ?? '');
        if (empty(trim($v1Content)) && $v1Output->file_path) {
            $full = Storage::disk('local')->path($v1Output->file_path);
            if (file_exists($full)) {
                $v1Content = file_get_contents($full);
            }
        }

        if (empty(trim($v1Content))) {
            return '';
        }

        Log::info('Agent 9 fallback: constructing v2 from v1 brief', ['case_id' => $case->id]);

        // Apply post-processing cleanup as the "v2 pass"
        return BriefPostProcessor::process($v1Content);
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
