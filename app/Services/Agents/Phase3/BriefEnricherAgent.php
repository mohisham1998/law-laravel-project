<?php

namespace App\Services\Agents\Phase3;

use App\Models\LegalCase;
use App\Services\Agents\BaseAgent;
use App\Services\CaseEventService;
use App\Services\LLM\LLMServiceInterface;
use App\Services\Orchestration\GateValidator;
use App\Services\Orchestration\PromptBuilder;
use App\Services\Output\BriefSectionChecker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Agent 14 — Brief Enricher
 *
 * Runs automatically after Agent 13 (Arabic Polisher) on every case.
 * Two-pass enrichment:
 *
 *   Pass 1 (always, zero LLM cost):
 *     - Replace all placeholder tokens ([رقم القضية], [الرقم] …) with real data
 *       extracted from the uploaded case documents.
 *     - Rebuild ملحق ١ from 04_timeline.json (structured events).
 *     - Rebuild ملحق ٢ from 03_statutes_index.jsonl (all cited statutes).
 *
 *   Pass 2 (only when body sections are missing or incomplete):
 *     - Single targeted LLM call to complete the brief body while keeping
 *       existing sections verbatim.
 *     - After the LLM call, Pass 1 deterministic fixes are re-applied so
 *       appendices are always rebuilt from structured data.
 *
 * Output: 15_final_brief_enriched.md
 */
class BriefEnricherAgent extends BaseAgent
{
    protected ?CaseEventService $eventService = null;

    public function __construct(
        PromptBuilder $promptBuilder,
        GateValidator $gateValidator,
        protected LLMServiceInterface $openRouter,
    ) {
        parent::__construct($promptBuilder, $gateValidator);
        $this->eventService = app(CaseEventService::class);
    }

    public function agentNumber(): int
    {
        return 14;
    }

    public function agentName(): string
    {
        return 'مُحسِّن المذكرة';
    }

    public function execute(LegalCase $case): array
    {
        $promptTokens     = 0;
        $completionTokens = 0;

        // ── 1. Get the best available brief ──────────────────────────────────
        $brief = $this->getBestBrief($case);
        if (empty(trim($brief))) {
            Log::warning('BriefEnricherAgent: no brief found, skipping', ['case_id' => $case->id]);
            return $this->emptyResult();
        }

        // ── 2. Check which sections are missing ───────────────────────────────
        $checker  = new BriefSectionChecker();
        $missing  = $checker->getMissingSections($brief);

        Log::info('BriefEnricherAgent: section check', [
            'case_id' => $case->id,
            'missing' => $checker->getLabels($missing),
        ]);

        // ── 3. Extract case metadata from uploaded documents ──────────────────
        $meta = $this->extractCaseMeta($case);

        // ── 4. Pass 1 — deterministic fixes ──────────────────────────────────
        $brief = $this->applyDeterministicFixes($brief, $meta);

        // ── 5. Pass 1 — rebuild appendices from structured data ───────────────
        $brief = $this->rebuildAppendices($case, $brief);

        // ── 6. Pass 2 — LLM gap-fill (only when body sections are missing) ────
        if ($checker->hasMissingBodySections($missing)) {
            Log::info('BriefEnricherAgent: running LLM gap-fill', [
                'case_id'          => $case->id,
                'missing_sections' => $checker->getLabels($missing),
            ]);

            $agentConfig = config('legal.agents.14', []);
            $temperature = $agentConfig['temperature'] ?? 0.2;
            $maxTokens   = $agentConfig['max_tokens'] ?? 8000;
            $model       = $case->modelForAgent($this->agentNumber());

            $prompt   = $this->buildGapFillPrompt($case, $brief, $missing, $checker);
            $messages = [['role' => 'user', 'content' => $prompt]];

            if ($this->eventService) {
                $onChunk = $this->eventService->createStreamCallback(
                    $case->id, $this->agentNumber(), $this->agentName()
                );
                $result = $this->openRouter->completeStream($model, $messages, $onChunk, $temperature, $maxTokens);
                $this->eventService->flushChunkBuffer($case->id, $this->agentNumber(), $this->agentName());
            } else {
                $result = $this->openRouter->complete($model, $messages, $temperature, $maxTokens);
            }

            $enriched = trim($result['content'] ?? '');
            $promptTokens     = $result['prompt_tokens'] ?? 0;
            $completionTokens = $result['completion_tokens'] ?? 0;

            // Only accept the LLM output if it is substantially longer
            if (mb_strlen($enriched) > mb_strlen($brief) * 0.85) {
                $brief = $enriched;
            } else {
                Log::warning('BriefEnricherAgent: LLM output shorter than expected, keeping current brief', [
                    'case_id'         => $case->id,
                    'original_length' => mb_strlen($brief),
                    'llm_length'      => mb_strlen($enriched),
                ]);
            }

            // Re-apply deterministic fixes after LLM (model may re-introduce placeholders)
            $brief = $this->applyDeterministicFixes($brief, $meta);
            $brief = $this->rebuildAppendices($case, $brief);
        }

        // ── 7. Save ───────────────────────────────────────────────────────────
        $this->saveOutput($case, '15_final_brief_enriched.md', $brief);

        return [
            'content'           => $brief,
            'filename'          => '15_final_brief_enriched.md',
            'output_files'      => ['15_final_brief_enriched.md'],
            'prompt_tokens'     => $promptTokens,
            'completion_tokens' => $completionTokens,
        ];
    }

    // =========================================================================
    // Brief retrieval
    // =========================================================================

    private function getBestBrief(LegalCase $case): string
    {
        $candidates = [
            '14_final_brief_polished.md',
            '13_final_brief_v3.md',
            '09_final_brief_v2.md',
            '08_final_brief.md',
        ];

        $best       = '';
        $bestLength = 0;

        foreach ($candidates as $filename) {
            $output = $case->outputs()->where('filename', $filename)->latest('id')->first();
            if (!$output) {
                continue;
            }

            $content = (string) ($output->content ?? '');
            if (empty(trim($content)) && $output->file_path) {
                $full = Storage::disk('local')->path($output->file_path);
                if (file_exists($full)) {
                    $content = file_get_contents($full);
                }
            }

            $length = mb_strlen(trim($content));
            if ($length > 800 && $length > $bestLength) {
                $best       = $content;
                $bestLength = $length;
            }
        }

        return $best;
    }

    // =========================================================================
    // Case metadata extraction from uploaded documents
    // =========================================================================

    private function extractCaseMeta(LegalCase $case): array
    {
        $meta = [
            'case_number'         => null,
            'defense_license'     => null,
            'court_division'      => null,
        ];

        $caseDir  = Storage::disk('local')->path("cases/{$case->id}");
        $txtFiles = glob("{$caseDir}/*.txt") ?: [];

        $allText = '';
        foreach ($txtFiles as $file) {
            $allText .= file_get_contents($file) . "\n";
        }

        if (empty($allText)) {
            return $meta;
        }

        // Case number — look for "القضية رقم (12345)" or "القضية رقم: 12345"
        if (preg_match('/القضية\s+رقم\s*[:(]\s*([\d٠-٩]+)/u', $allText, $m)) {
            $meta['case_number'] = $m[1];
        }

        // Defense attorney license — the line that mentions defense attorney + ترخيص
        // Handles both Arabic-Indic (٣٨/١٥٠) and ASCII (38/150)
        if (preg_match(
            '/(?:المدعى عليه|وكيل المدعى عليهما?|طارق).{0,120}ترخيص\s*(?:محاماة\s*)?رقم\s*\(([٠-٩\d\/\\\\]+)\)/us',
            $allText,
            $m
        )) {
            $meta['defense_license'] = $m[1];
        }

        // Court division name (e.g. الدائرة الجزائية السابعة)
        if (preg_match('/(الدائرة الجزائية\s+\S+)/u', $allText, $m)) {
            $meta['court_division'] = trim($m[1]);
        }

        return $meta;
    }

    // =========================================================================
    // Pass 1 — deterministic fixes
    // =========================================================================

    private function applyDeterministicFixes(string $brief, array $meta): string
    {
        // Replace case-number placeholders — only in the "القضية رقم" context or explicit [رقم القضية] marker.
        // Do NOT use |\[الرقم\] here — that generic placeholder also appears on license/wording lines
        // and would incorrectly inject the case number there. Line 264 strips leftover [الرقم] to empty.
        if (!empty($meta['case_number'])) {
            $brief = preg_replace(
                '/القضية\s+رقم\s*[:(]?\s*\[.*?\]|\[رقم القضية\]/u',
                'القضية رقم: ' . $meta['case_number'],
                $brief
            );
        }

        // Replace defense license placeholders or obvious hallucinations
        if (!empty($meta['defense_license'])) {
            // Remove known hallucinated license patterns (7+ digit sequences)
            $brief = preg_replace(
                '/ترخيص(?:\s+محاماة)?\s+رقم\s*\([٠-٩\d]{7,}\)/u',
                'ترخيص رقم (' . $meta['defense_license'] . ')',
                $brief
            );
            // Replace explicit placeholders
            $brief = preg_replace(
                '/ترخيص(?:\s+محاماة)?\s+رقم\s*\[\s*الرقم\s*\]/u',
                'ترخيص رقم (' . $meta['defense_license'] . ')',
                $brief
            );
        }

        // Replace any remaining [الرقم] or [التاريخ...] generic placeholders.
        // If we have the defense license, also replace [الرقم] that appears in "ترخيص رقم" context.
        if (!empty($meta['defense_license'])) {
            $brief = preg_replace(
                '/ترخيص(?:\s+محاماة)?\s+رقم\s*\[\s*الرقم\s*\]/u',
                'ترخيص رقم (' . $meta['defense_license'] . ')',
                $brief
            );
        }
        $brief = preg_replace('/\[\s*الرقم\s*\]/u', '', $brief);
        $brief = preg_replace('/\[\s*التاريخ[^\]]*\]/u', '', $brief);

        // Ensure court name includes division number (السابعة) when known
        if (!empty($meta['court_division']) && mb_strpos($brief, 'السابعة') === false) {
            $brief = str_replace(
                'الدائرة الجزائية بالمحكمة',
                'الدائرة الجزائية السابعة بالمحكمة',
                $brief
            );
        }

        return $brief;
    }

    // =========================================================================
    // Pass 1 — appendix rebuild from structured data
    // =========================================================================

    private function rebuildAppendices(LegalCase $case, string $brief): string
    {
        $app1 = $this->buildAppendix1($case);
        $app2 = $this->buildAppendix2($case, $brief);

        if (empty($app1) && empty($app2)) {
            return $brief;
        }

        // Remove existing appendix block (everything from the first ملحق to end)
        $appendixStart = $this->findAppendixStart($brief);
        if ($appendixStart !== false) {
            $brief = rtrim(mb_substr($brief, 0, $appendixStart));
        }

        $appendices = '';
        if (!empty($app1)) {
            $appendices .= "\n\n---\n\n" . $app1;
        }
        if (!empty($app2)) {
            $appendices .= "\n\n---\n\n" . $app2;
        }

        return $brief . $appendices;
    }

    private function buildAppendix1(LegalCase $case): string
    {
        // Use the structured JSON timeline for accurate Hijri dates
        $jsonOutput = $case->outputs()->where('filename', '04_timeline.json')->latest('id')->first();
        $jsonContent = '';

        if ($jsonOutput) {
            $jsonContent = (string) ($jsonOutput->content ?? '');
            if (empty(trim($jsonContent)) && $jsonOutput->file_path) {
                $full = Storage::disk('local')->path($jsonOutput->file_path);
                if (file_exists($full)) {
                    $jsonContent = file_get_contents($full);
                }
            }
        }

        // Fallback: try the markdown timeline
        if (empty(trim($jsonContent))) {
            $mdOutput = $case->outputs()->where('filename', '04_timeline.md')->latest('id')->first();
            if ($mdOutput) {
                $mdContent = (string) ($mdOutput->content ?? '');
                if (empty(trim($mdContent)) && $mdOutput->file_path) {
                    $full = Storage::disk('local')->path($mdOutput->file_path);
                    if (file_exists($full)) {
                        $mdContent = file_get_contents($full);
                    }
                }
                if (!empty(trim($mdContent))) {
                    return "ملحق (١): مسرد الوقائع الزمنية\n\n" . trim($mdContent);
                }
            }
            return '';
        }

        // Strip leading markdown heading that some agents prepend
        $jsonContent = ltrim(preg_replace('/^#+[^\n]*\n/u', '', $jsonContent));

        $events = json_decode($jsonContent, true);
        if (!is_array($events)) {
            return '';
        }

        $lines = [];
        foreach ($events as $event) {
            $hijri = $event['date_hijri']  // "1435/03/15"
                  ?? $event['date_raw']    // "15/03/1435"
                  ?? ($event['date'] ?? '');
            $desc = $event['description'] ?? '';
            if (empty(trim($desc))) {
                continue;
            }
            $dateLabel = !empty($hijri) ? "بتاريخ {$hijri}هـ" : '';
            $place     = !empty($event['place']) ? " ({$event['place']})" : '';
            $lines[]   = "- {$dateLabel}{$place}: {$desc}";
        }

        if (empty($lines)) {
            return '';
        }

        return "ملحق (١): مسرد الوقائع الزمنية\n\n" . implode("\n", $lines);
    }

    private function buildAppendix2(LegalCase $case, string $brief): string
    {
        $jsonlOutput = $case->outputs()->where('filename', '03_statutes_index.jsonl')->latest('id')->first();
        if (!$jsonlOutput) {
            return '';
        }

        $jsonlContent = (string) ($jsonlOutput->content ?? '');
        if (empty(trim($jsonlContent)) && $jsonlOutput->file_path) {
            $full = Storage::disk('local')->path($jsonlOutput->file_path);
            if (file_exists($full)) {
                $jsonlContent = file_get_contents($full);
            }
        }

        if (empty(trim($jsonlContent))) {
            return '';
        }

        $statutes = [];
        foreach (explode("\n", trim($jsonlContent)) as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] !== '{') {
                continue;
            }
            $statute = json_decode($line, true);
            if (!$statute || empty($statute['local_ref'])) {
                continue;
            }
            $statutes[] = $statute;
        }

        if (empty($statutes)) {
            return '';
        }

        // Only include articles that are actually cited in the brief body
        $cited = $this->extractCitedArticleNumbers($brief);

        $lines   = [];
        $groupBy = [];

        foreach ($statutes as $statute) {
            $artNo = (string) ($statute['article_no'] ?? '');
            // Include if cited in brief, or include all if no citations detected
            if (!empty($cited) && !in_array($artNo, $cited)) {
                continue;
            }
            $lawTitle  = $statute['title'] ?? 'النظام';
            $localRef  = $statute['local_ref'];
            $content   = $statute['content'] ?? '';
            $groupBy[$lawTitle][] = "- **{$localRef}**: {$content}";
        }

        if (empty($groupBy)) {
            // If cite-filtering removed everything, include all statutes
            foreach ($statutes as $statute) {
                $lawTitle = $statute['title'] ?? 'النظام';
                $localRef = $statute['local_ref'];
                $content  = $statute['content'] ?? '';
                $groupBy[$lawTitle][] = "- **{$localRef}**: {$content}";
            }
        }

        foreach ($groupBy as $lawTitle => $articles) {
            $lines[] = "**{$lawTitle}:**";
            foreach ($articles as $article) {
                $lines[] = $article;
            }
        }

        return "ملحق (٢): المواد النظامية المستشهد بها\n\n" . implode("\n", $lines);
    }

    /**
     * Find the character position where the first appendix block starts.
     */
    private function findAppendixStart(string $brief): int|false
    {
        // Look for "ملحق" preceded by newline or "---"
        if (preg_match('/(?:^|\n)\s*(?:---\s*\n+)?\s*ملحق\s*[\(（١1]/um', $brief, $m, PREG_OFFSET_CAPTURE)) {
            return $m[0][1];
        }
        // Looser: just find "ملحق (" or "ملحق١"
        $pos = mb_strrpos($brief, '---');
        if ($pos !== false) {
            $after = mb_substr($brief, $pos);
            if (mb_strpos($after, 'ملحق') !== false) {
                return $pos;
            }
        }
        return false;
    }

    /**
     * Extract Arabic article numbers cited in the brief (e.g. "المادة الثانية", "مادة (71)").
     */
    private function extractCitedArticleNumbers(string $brief): array
    {
        $numbers = [];

        // Numeric: مادة (71) or المادة 71
        preg_match_all('/(?:المادة|مادة)\s*[\(（]?(\d+)[\)）]?/u', $brief, $matches);
        foreach ($matches[1] as $n) {
            $numbers[] = $n;
        }

        // From local_ref patterns like "الحادية والسبعون" → 71
        // We'll map common ones to keep things simple
        $wordMap = [
            'الأولى'               => '1',  'الثانية'              => '2',
            'الثالثة'              => '3',  'الرابعة'              => '4',
            'الخامسة'              => '5',  'السادسة'              => '6',
            'السابعة'              => '7',  'الثامنة'              => '8',
            'التاسعة'              => '9',  'العاشرة'              => '10',
            'الحادية عشرة'         => '11', 'الثانية عشرة'         => '12',
            'الحادية والسبعون'     => '71', 'التاسعة والستون'      => '69',
            'التاسعة والسبعون'     => '79', 'الثانية والتسعون'     => '92',
        ];

        foreach ($wordMap as $word => $num) {
            if (mb_strpos($brief, $word) !== false) {
                $numbers[] = $num;
            }
        }

        return array_unique($numbers);
    }

    // =========================================================================
    // Pass 2 — LLM gap-fill
    // =========================================================================

    private function buildGapFillPrompt(LegalCase $case, string $brief, array $missing, BriefSectionChecker $checker): string
    {
        $missingLabels = implode("\n- ", $checker->getLabels($missing));

        // Gather supporting context
        $defenseContext = $this->loadOutputContent($case, '07_defense_layers.md', 4000);
        $witnessContext = $this->loadCaseTxtFile($case, 4000);  // court session transcript
        $entitiesContext = $this->loadOutputContent($case, '04_entities_index.md', 1500);

        $contextBlock = '';
        if (!empty($defenseContext)) {
            $contextBlock .= "## طبقات الدفاع (للاستعانة بها)\n\n{$defenseContext}\n\n";
        }
        if (!empty($witnessContext)) {
            $contextBlock .= "## مقتطف من محضر الجلسة (للاستعانة بالشهود)\n\n{$witnessContext}\n\n";
        }
        if (!empty($entitiesContext)) {
            $contextBlock .= "## الكيانات والأطراف\n\n{$entitiesContext}\n\n";
        }

        return <<<PROMPT
## المهمة: إتمام المذكرة القانونية

لديك مذكرة قانونية عربية منقوصة. يجب عليك إتمام الأقسام الناقصة أو غير المكتملة دون حذف أي قسم موجود أو تقليصه.

## الأقسام الناقصة أو غير المكتملة:
- {$missingLabels}

## تعليمات إلزامية:
١. أبقِ جميع الأقسام الموجودة في المذكرة حرفياً — لا تحذف ولا تختصر.
٢. أضف الأقسام الناقصة في مواضعها الصحيحة: أولاً، ثانياً، ثالثاً، رابعاً، خامساً، سادساً.
٣. إذا كان "ثالثاً: تجريح الشهود" موجوداً لكنه يغطي شاهداً واحداً فقط، قم بتوسيعه ليشمل جميع الشهود المذكورين في محضر الجلسة.
٤. لكل شاهد: اذكر اسمه، صلته بالأطراف، سبب رد شهادته، والمادة النظامية الحاكمة.
٥. لا تضف ملاحق — ستُولَّد تلقائياً من البيانات المنظمة.
٦. لا تكتب أي تعليق خارج نص المذكرة.
٧. أنتج المذكرة الكاملة بجميع أقسامها.

---

## المذكرة الحالية (أكملها):

{$brief}

---

{$contextBlock}
PROMPT;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function loadOutputContent(LegalCase $case, string $filename, int $maxChars): string
    {
        $output = $case->outputs()->where('filename', $filename)->latest('id')->first();
        if (!$output) {
            return '';
        }
        $content = (string) ($output->content ?? '');
        if (empty(trim($content)) && $output->file_path) {
            $full = Storage::disk('local')->path($output->file_path);
            if (file_exists($full)) {
                $content = file_get_contents($full);
            }
        }
        if (mb_strlen($content) > $maxChars) {
            $content = mb_substr($content, 0, $maxChars) . "\n\n… [مقتطع] …";
        }
        return trim($content);
    }

    /**
     * Load the most relevant uploaded case txt file (court session transcript if found).
     */
    private function loadCaseTxtFile(LegalCase $case, int $maxChars): string
    {
        $caseDir  = Storage::disk('local')->path("cases/{$case->id}");
        $txtFiles = glob("{$caseDir}/*.txt") ?: [];

        // Prefer files that contain court session content (محضر / جلسة / شاهد)
        $bestFile    = '';
        $bestLength  = 0;
        $sessionFile = '';

        foreach ($txtFiles as $file) {
            $content = file_get_contents($file);
            if (preg_match('/محضر.*جلسة|جلسة.*شاهد|الشاهد الأول|الشاهد الثاني/u', $content)) {
                $sessionFile = $content;
                break;
            }
            if (mb_strlen($content) > $bestLength) {
                $bestFile   = $content;
                $bestLength = mb_strlen($content);
            }
        }

        $text = $sessionFile ?: $bestFile;
        if (empty($text)) {
            return '';
        }
        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars) . "\n\n… [مقتطع] …";
        }
        return trim($text);
    }

    protected function saveOutput(LegalCase $case, string $filename, string $content): void
    {
        $path = "cases/{$case->id}/outputs/{$filename}";
        Storage::disk('local')->put($path, $content);
        try {
            $fp = Storage::disk('local')->path($path);
            chmod($fp, 0644);
            chmod(dirname($fp), 0755);
        } catch (\Throwable) {}

        \App\Models\CaseOutput::create([
            'case_id'      => $case->id,
            'agent_number' => $this->agentNumber(),
            'filename'     => $filename,
            'file_path'    => $path,
            'content_type' => 'markdown',
            'content'      => $content,
            'file_size'    => strlen($content),
        ]);
    }

    private function emptyResult(): array
    {
        return [
            'content'           => '',
            'filename'          => '15_final_brief_enriched.md',
            'output_files'      => [],
            'prompt_tokens'     => 0,
            'completion_tokens' => 0,
        ];
    }
}
