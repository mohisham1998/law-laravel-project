<?php

namespace App\Services\Output;

/**
 * Deterministic PHP post-processor for Arabic legal briefs.
 *
 * Strips internal markers, confidence scores, agent metadata, and
 * JSON artifacts from LLM-generated briefs — leaving pure Arabic prose.
 */
class BriefPostProcessor
{
    /**
     * Clean a brief by removing all AI-internal artifacts.
     *
     * Operations (in order):
     * 1.  Strip CASE:{...} markers
     * 2.  Strip LAW:{...} markers
     * 3.  Remove confidence score patterns
     * 4.  Remove agent metadata headers
     * 5.  Remove ⚠️ غير مُسنَّدة paragraphs
     * 6.  Remove JSON code-fence blocks
     * 7.  Remove lines that are only English technical terms
     * 8.  Ensure بسم الله الرحمن الرحيم as first non-empty line
     * 9.  Normalize whitespace
     * 10. Return cleaned string
     */
    public static function process(string $brief): string
    {
        if (trim($brief) === '') {
            return $brief;
        }

        // 1. Strip CASE:{...} markers — ASCII refs like CASE:REF_123 and bracketed {Arabic text}
        $brief = preg_replace('/\[?CASE:\{[^}]*\}\]?/u', '', $brief);
        $brief = preg_replace('/\[?CASE:[A-Z0-9_]+\]?/i', '', $brief);

        // 2. Strip LAW:{...} markers — ASCII refs like LAW:REF_123 and bracketed {Arabic text}
        $brief = preg_replace('/\[?LAW:\{[^}]*\}(?:\s*"[^"]*")?\]?/u', '', $brief);
        $brief = preg_replace('/\[?LAW:[A-Z0-9_]+\]?/i', '', $brief);

        // 3. Remove confidence score patterns
        $brief = preg_replace('/confidence[:\s]*[\d.]+%?/i', '', $brief);
        $brief = preg_replace('/"confidence"\s*:\s*[\d.]+[,]?/i', '', $brief);

        // 4. Remove agent metadata headers (lines like "## Agent 8:" or "### Agent 9:")
        $brief = preg_replace('/^#{1,4}\s*Agent\s*\d+.*$/mi', '', $brief);

        // 5. Remove ⚠️ غير مُسنَّدة paragraphs (paragraph = block between blank lines)
        $brief = preg_replace('/[^\n]*⚠️\s*غير\s*مُسنَّدة[^\n]*/u', '', $brief);

        // 6. Remove code-fence blocks (```json ... ``` or ``` ... ```)
        $brief = preg_replace('/```(?:json|javascript|yaml|xml)?[\s\S]*?```/s', '', $brief);

        // 7. Remove lines that consist only of English technical terms
        $technicalTerms = [
            'statute_id', 'chunk_id', 'match_type', 'quoted_text', 'law_registry_id',
            'source', 'confidence', 'abrogated', 'supersedes', 'article_no',
            'effective_year', 'local_ref', 'file_label',
        ];
        $lines = explode("\n", $brief);
        $filteredLines = [];
        foreach ($lines as $line) {
            $stripped = trim($line);
            // Skip lines that are only ASCII (likely English-only technical lines)
            // but allow lines that have Arabic characters
            if ($stripped !== '' && !preg_match('/\p{Arabic}/u', $stripped)) {
                // Check if this is a purely technical/English line (not a section separator)
                if (!preg_match('/^[-=\*#]+$/', $stripped) && !str_starts_with($stripped, '---')) {
                    // Check against known technical terms
                    $isOnlyTechnical = true;
                    $lowerLine = strtolower($stripped);
                    foreach ($technicalTerms as $term) {
                        if (str_contains($lowerLine, $term)) {
                            $isOnlyTechnical = true;
                            break;
                        }
                        $isOnlyTechnical = false;
                    }
                    if ($isOnlyTechnical && strlen($stripped) < 100) {
                        continue; // Skip purely technical English lines
                    }
                }
            }
            $filteredLines[] = $line;
        }
        $brief = implode("\n", $filteredLines);

        // 8. Ensure بسم الله الرحمن الرحيم as first non-empty line
        $preamble = 'بسم الله الرحمن الرحيم';
        $trimmedBrief = ltrim($brief);
        if (!str_starts_with($trimmedBrief, $preamble)) {
            $brief = $preamble . "\n\n" . ltrim($brief);
        }

        // 9. Normalize whitespace — collapse 3+ consecutive newlines to 2
        $brief = preg_replace('/\n{3,}/', "\n\n", $brief);
        $brief = trim($brief);

        return $brief;
    }
}
