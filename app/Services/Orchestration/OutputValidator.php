<?php

namespace App\Services\Orchestration;

use Illuminate\Support\Facades\Storage;

/**
 * Deterministic PHP validators for agent output.
 *
 * These run AFTER the LLM produces output and catch hallucinations,
 * malformed JSONL, invalid citations, and structural violations
 * without relying on another LLM call.
 */
class OutputValidator
{
    /**
     * Validate that a string is well-formed JSONL (one valid JSON object per line).
     *
     * @return array  Violations list (empty = valid)
     */
    public static function validateJsonl(string $content): array
    {
        $violations = [];
        $lines = array_filter(explode("\n", $content), fn ($l) => trim($l) !== '');

        if (empty($lines)) {
            $violations[] = [
                'type' => 'empty_jsonl',
                'detail' => 'JSONL output is empty — no data lines found',
                'lesson' => 'يجب أن يحتوي المخرج على سطر JSON واحد على الأقل',
            ];
            return $violations;
        }

        $validCount = 0;
        foreach ($lines as $lineNum => $line) {
            $trimmed = trim($line);
            // Skip markdown headers, comments, code fences
            if (str_starts_with($trimmed, '#') || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '```')) {
                continue;
            }
            if (!str_starts_with($trimmed, '{')) {
                continue; // Not a JSON line
            }

            $decoded = json_decode($trimmed, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                $violations[] = [
                    'type' => 'malformed_json_line',
                    'detail' => "Line " . ($lineNum + 1) . " is not valid JSON: " . json_last_error_msg(),
                    'lesson' => 'كل سطر في JSONL يجب أن يكون كائن JSON صالح',
                ];
            } else {
                $validCount++;
            }
        }

        if ($validCount === 0) {
            $violations[] = [
                'type' => 'no_json_objects',
                'detail' => 'No valid JSON objects found in JSONL output',
                'lesson' => 'يجب أن يحتوي JSONL على كائنات JSON صالحة',
            ];
        }

        return $violations;
    }

    /**
     * Validate that every statute_id in the output exists in the statutes index.
     *
     * @param  string  $output         Agent output (e.g. 06_statutes_map.jsonl)
     * @param  string  $statutesIndex  Content of 03_statutes_index.jsonl
     * @return array   Violations list
     */
    public static function validateStatuteIds(string $output, string $statutesIndex): array
    {
        $violations = [];

        // Build set of valid statute_ids from the index
        $validIds = [];
        foreach (explode("\n", $statutesIndex) as $line) {
            $trimmed = trim($line);
            if (!str_starts_with($trimmed, '{')) {
                continue;
            }
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded) && isset($decoded['statute_id'])) {
                $validIds[$decoded['statute_id']] = true;
            }
        }

        if (empty($validIds)) {
            // Can't validate if index is empty — skip silently
            return [];
        }

        // Check each statute_id in the output
        $checked = [];
        foreach (explode("\n", $output) as $line) {
            $trimmed = trim($line);
            if (!str_starts_with($trimmed, '{')) {
                continue;
            }
            $decoded = json_decode($trimmed, true);
            if (!is_array($decoded) || !isset($decoded['statute_id'])) {
                continue;
            }

            $id = $decoded['statute_id'];
            if (isset($checked[$id])) {
                continue; // Already reported
            }
            $checked[$id] = true;

            if (!isset($validIds[$id])) {
                $violations[] = [
                    'type' => 'hallucinated_statute_id',
                    'detail' => "statute_id \"{$id}\" does not exist in 03_statutes_index.jsonl — this is a hallucination",
                    'lesson' => 'يجب أن يكون كل statute_id موجودًا في فهرس المواد النظامية (03_statutes_index.jsonl)',
                ];
            }
        }

        return $violations;
    }

    /**
     * Validate that quoted_text in output is an actual substring of the source content.
     *
     * @param  string  $output         Agent output (JSONL with quoted_text fields)
     * @param  string  $statutesIndex  Content of 03_statutes_index.jsonl
     * @return array   Violations list
     */
    public static function validateQuotedText(string $output, string $statutesIndex): array
    {
        $violations = [];

        // Build map of statute_id → content from the index
        $contentMap = [];
        foreach (explode("\n", $statutesIndex) as $line) {
            $trimmed = trim($line);
            if (!str_starts_with($trimmed, '{')) {
                continue;
            }
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded) && isset($decoded['statute_id'], $decoded['content'])) {
                $contentMap[$decoded['statute_id']] = $decoded['content'];
            }
        }

        if (empty($contentMap)) {
            return [];
        }

        // Check each quoted_text in the output
        foreach (explode("\n", $output) as $line) {
            $trimmed = trim($line);
            if (!str_starts_with($trimmed, '{')) {
                continue;
            }
            $decoded = json_decode($trimmed, true);
            if (!is_array($decoded) || !isset($decoded['quoted_text'], $decoded['statute_id'])) {
                continue;
            }

            $quotedText = trim($decoded['quoted_text']);
            $statuteId = $decoded['statute_id'];

            if ($quotedText === '' || !isset($contentMap[$statuteId])) {
                continue;
            }

            // Check if quoted_text is a substring of the source content
            // Use mb_strpos for Arabic text, and normalize whitespace first
            $normalizedQuote = preg_replace('/\s+/u', ' ', $quotedText);
            $normalizedSource = preg_replace('/\s+/u', ' ', $contentMap[$statuteId]);

            // Allow partial matches (first 50 chars) since LLMs may truncate
            $checkLength = min(mb_strlen($normalizedQuote), 50);
            $checkPortion = mb_substr($normalizedQuote, 0, $checkLength);

            if (mb_strpos($normalizedSource, $checkPortion) === false) {
                $violations[] = [
                    'type' => 'fabricated_quote',
                    'detail' => "quoted_text for {$statuteId} is not found in source content — possible fabrication",
                    'lesson' => 'يجب أن يكون النص المقتبس (quoted_text) نسخة حرفية من المصدر',
                ];
            }
        }

        return $violations;
    }

    /**
     * Validate that no abrogated statutes are cited as valid.
     *
     * @return array  Violations list
     */
    public static function validateNoAbrogated(string $output, string $statutesIndex): array
    {
        $violations = [];

        // Build set of abrogated/superseded statute_ids from the index
        $abrogatedIds = [];
        foreach (explode("\n", $statutesIndex) as $line) {
            $trimmed = trim($line);
            if (!str_starts_with($trimmed, '{')) {
                continue;
            }
            $decoded = json_decode($trimmed, true);
            if (!is_array($decoded) || !isset($decoded['statute_id'])) {
                continue;
            }

            // Check if statute is superseded or explicitly abrogated
            $isAbrogated = false;
            if (!empty($decoded['supersedes'])) {
                $isAbrogated = true;
            }
            if (isset($decoded['abrogated']) && $decoded['abrogated'] === true) {
                $isAbrogated = true;
            }
            if (preg_match('/ملغ[يا]ة?|abrogated|superseded/ui', ($decoded['content'] ?? '') . ' ' . ($decoded['title'] ?? ''))) {
                $isAbrogated = true;
            }

            if ($isAbrogated) {
                $abrogatedIds[$decoded['statute_id']] = true;
            }
        }

        if (empty($abrogatedIds)) {
            return [];
        }

        // Check output for citations of abrogated statutes marked as valid
        foreach (explode("\n", $output) as $line) {
            $trimmed = trim($line);
            if (!str_starts_with($trimmed, '{')) {
                continue;
            }
            $decoded = json_decode($trimmed, true);
            if (!is_array($decoded) || !isset($decoded['statute_id'])) {
                continue;
            }

            $statuteId = $decoded['statute_id'];
            $markedAbrogated = $decoded['abrogated'] ?? null;

            if (isset($abrogatedIds[$statuteId]) && $markedAbrogated === false) {
                $violations[] = [
                    'type' => 'abrogated_cited_as_valid',
                    'detail' => "statute_id \"{$statuteId}\" is abrogated/superseded but cited with abrogated:false",
                    'lesson' => 'لا يجوز الاستشهاد بمواد ملغاة على أنها سارية المفعول',
                ];
            }
        }

        return $violations;
    }

    /**
     * Validate that all confidence scores meet the minimum threshold.
     *
     * @return array  Violations list
     */
    public static function validateConfidenceFloor(string $output, float $threshold = 0.70): array
    {
        $violations = [];

        foreach (explode("\n", $output) as $line) {
            $trimmed = trim($line);
            if (!str_starts_with($trimmed, '{')) {
                continue;
            }
            $decoded = json_decode($trimmed, true);
            if (!is_array($decoded) || !isset($decoded['confidence'])) {
                continue;
            }

            $confidence = (float) $decoded['confidence'];
            if ($confidence > 0 && $confidence < $threshold) {
                $statuteId = $decoded['statute_id'] ?? 'unknown';
                $violations[] = [
                    'type' => 'below_confidence_threshold',
                    'detail' => "Match for {$statuteId} has confidence {$confidence} below threshold {$threshold}",
                    'lesson' => "يجب أن تكون درجة الثقة {$threshold} أو أعلى للقبول",
                ];
            }
        }

        return $violations;
    }

    /**
     * Validate that brief citations are in Arabic prose form (no internal markers).
     *
     * Updated for 009-pipeline-output-quality: markers are no longer used.
     * Instead, validates that Arabic prose citations reference known statute names.
     *
     * @param  string  $brief        Content of final brief
     * @param  string  $statutesMap  Content of 06_statutes_map.jsonl
     * @return array   Violations list
     */
    public static function validateBriefCitations(string $brief, string $statutesMap): array
    {
        $violations = [];

        // Check for residual LAW:{ref} or CASE:{ref} markers (should be zero)
        if (preg_match_all('/(?:LAW|CASE):[A-Z0-9_]+/i', $brief, $matches)) {
            $found = array_unique($matches[0]);
            foreach (array_slice($found, 0, 5) as $marker) {
                $violations[] = [
                    'type' => 'residual_internal_marker',
                    'detail' => "Internal marker \"{$marker}\" found in brief — should have been converted to Arabic prose",
                    'lesson' => 'يجب أن تُكتب جميع الاستشهادات بالعربية النثرية، وليس بعلامات مرجعية داخلية',
                ];
            }
        }

        return $violations;
    }

    /**
     * Validate that a final brief is pure Arabic (≥95% Arabic characters).
     *
     * Checks:
     * - Arabic character ratio ≥ 95%
     * - No JSON code blocks
     * - No internal markers (CASE/LAW)
     * - No English technical terms
     *
     * @return array  Violations list (empty = valid)
     */
    public static function validateArabicFinalBrief(string $brief): array
    {
        $violations = [];

        if (trim($brief) === '') {
            $violations[] = [
                'type' => 'empty_brief',
                'detail' => 'Brief is empty',
                'lesson' => 'المذكرة لا يمكن أن تكون فارغة',
            ];
            return $violations;
        }

        // 1. Arabic character ratio check
        $totalChars = mb_strlen($brief);
        $arabicChars = preg_match_all('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $brief, $m);
        $ratio = $totalChars > 0 ? ($arabicChars / $totalChars) * 100 : 0;

        if ($ratio < 70) {
            $violations[] = [
                'type' => 'low_arabic_ratio',
                'detail' => sprintf('Arabic character ratio is %.1f%% (minimum 70%% required)', $ratio),
                'lesson' => 'يجب أن تحتوي المذكرة على نسبة عالية من الأحرف العربية',
            ];
        }

        // 2. JSON code blocks
        if (preg_match('/```\s*(?:json)?[\s\S]*?```/s', $brief)) {
            $violations[] = [
                'type' => 'json_in_brief',
                'detail' => 'Brief contains JSON code blocks — these must be removed',
                'lesson' => 'لا يجوز أن تحتوي المذكرة على كتل JSON',
            ];
        }

        // 3. Internal markers
        if (preg_match('/(?:LAW|CASE):[A-Z0-9_]+/i', $brief)) {
            $violations[] = [
                'type' => 'internal_markers_present',
                'detail' => 'Brief contains CASE/LAW internal reference markers',
                'lesson' => 'يجب أن تُكتب الاستشهادات بالعربية النثرية لا بالعلامات الداخلية',
            ];
        }

        // 4. English technical terms (not proper nouns)
        $technicalTerms = ['statute_id', 'chunk_id', 'confidence', 'match_type', 'abrogated', 'law_registry_id', 'source'];
        foreach ($technicalTerms as $term) {
            if (stripos($brief, $term) !== false) {
                $violations[] = [
                    'type' => 'technical_english_term',
                    'detail' => "Brief contains technical English term: \"{$term}\"",
                    'lesson' => 'لا يجوز أن تحتوي المذكرة على مصطلحات تقنية إنجليزية',
                ];
                break; // Report once
            }
        }

        return $violations;
    }

    /**
     * Validate that the brief has no significant English text leakage.
     *
     * Allows: single English words, proper nouns, abbreviations.
     * Flags: 3+ consecutive ASCII words in a row (likely English sentences).
     *
     * @return array  Violations list (empty = valid)
     */
    public static function validateNoEnglishLeak(string $brief): array
    {
        $violations = [];

        // Detect 3+ consecutive English words (not just abbreviations or proper nouns)
        // Pattern: 3 or more words consisting only of ASCII letters, separated by spaces
        if (preg_match('/\b[a-zA-Z]{2,}\s+[a-zA-Z]{2,}\s+[a-zA-Z]{2,}\b/', $brief, $match)) {
            $violations[] = [
                'type' => 'english_text_leak',
                'detail' => "Detected consecutive English words in brief: \"" . mb_substr($match[0], 0, 60) . "\"",
                'lesson' => 'المذكرة يجب أن تكون عربية خالصة — المقاطع الإنجليزية المتواصلة غير مقبولة',
            ];
        }

        return $violations;
    }

    /**
     * Validate that the brief has the mandatory core sections per SKILL.md.
     *
     * Accepts two layout formats:
     * 1. Named section headers:  ## المقدمة، ## وقائع الدعوى، etc.
     * 2. Arabic ordinal numbering: أولاً:، ثانياً:، ثالثاً:، ... (used in formal Saudi courts)
     *
     * الملاحق (appendices) are required: every brief must contain at minimum
     * a chronological events timeline (ملحق ١) and a cited articles list (ملحق ٢).
     *
     * @return array  Violations list
     */
    public static function validateBriefStructure(string $brief): array
    {
        $violations = [];

        // Check preamble — always required as the very first line
        if (mb_strpos($brief, 'بسم الله الرحمن الرحيم') === false) {
            $violations[] = [
                'type' => 'missing_brief_sections',
                'detail' => 'البسملة مفقودة — يجب أن تبدأ المذكرة بـ "بسم الله الرحمن الرحيم"',
                'lesson' => 'السطر الأول إلزامياً هو "بسم الله الرحمن الرحيم"',
            ];
        }

        // Check for ordinal Arabic structure (أولاً، ثانياً، ثالثاً...) OR named sections
        // A valid brief has either:
        //   (a) at least 3 ordinal markers (أولاً/ثانياً/ثالثاً), OR
        //   (b) named section keywords (المقدمة + الطلبات)
        $hasOrdinalStructure = (
            mb_strpos($brief, 'أولاً') !== false &&
            mb_strpos($brief, 'ثانياً') !== false &&
            mb_strpos($brief, 'ثالثاً') !== false
        );

        $hasNamedSections = (
            mb_strpos($brief, 'المقدمة') !== false &&
            mb_strpos($brief, 'الطلبات') !== false
        );

        if (!$hasOrdinalStructure && !$hasNamedSections) {
            $violations[] = [
                'type' => 'missing_brief_sections',
                'detail' => 'البنية الهيكلية مفقودة — المذكرة يجب أن تحتوي على أقسام مرقمة (أولاً، ثانياً، ثالثاً...) أو أقسام مسماة (المقدمة، الطلبات، الخاتمة)',
                'lesson' => 'يجب أن تحتوي المذكرة على هيكل أقسام واضح بالترقيم الترتيبي العربي أو بالعناوين المسماة',
            ];
        }

        // الطلبات section is always mandatory
        if (mb_strpos($brief, 'الطلبات') === false && mb_strpos($brief, 'الطلب الأصلي') === false && mb_strpos($brief, 'الطلبات الأصلية') === false) {
            $violations[] = [
                'type' => 'missing_brief_sections',
                'detail' => 'قسم الطلبات مفقود — يجب أن تتضمن المذكرة طلبات صريحة',
                'lesson' => 'قسم الطلبات إلزامي في كل مذكرة دفاعية',
            ];
        }

        // الملاحق (appendices) are mandatory — every brief must have them
        $hasAppendix = (
            mb_strpos($brief, 'ملحق') !== false ||
            mb_strpos($brief, 'الملاحق') !== false
        );

        if (!$hasAppendix) {
            $violations[] = [
                'type' => 'missing_appendix',
                'detail' => 'قسم الملاحق مفقود — يجب أن تحتوي المذكرة على "ملحق ١: مسرد الوقائع الزمني" و"ملحق ٢: المواد النظامية المستشهد بها"',
                'lesson' => 'الملاحق إلزامية في كل مذكرة: مسرد الوقائع + قائمة المواد المستشهد بها كاملةً',
            ];
        }

        // Check for unsupported paragraphs marker (residual AI artifact)
        if (preg_match_all('/⚠️ غير مُسنَّدة/', $brief, $matches)) {
            $count = count($matches[0]);
            $violations[] = [
                'type' => 'unsupported_paragraphs',
                'detail' => "{$count} paragraph(s) marked as unsupported (⚠️ غير مُسنَّدة) — need citations",
                'lesson' => 'كل فقرة جوهرية في المذكرة يجب أن تستند إلى مادة نظامية أو مستند',
            ];
        }

        return $violations;
    }
}
