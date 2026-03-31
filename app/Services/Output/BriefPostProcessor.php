<?php

namespace App\Services\Output;

/**
 * Deterministic PHP post-processor for Arabic legal briefs.
 *
 * Strips internal markers, confidence scores, agent metadata,
 * JSON artifacts, filenames, and all non-Arabic technical lines —
 * leaving pure Arabic prose suitable for end-user display.
 */
class BriefPostProcessor
{
    public static function process(string $brief): string
    {
        if (trim($brief) === '') {
            return $brief;
        }

        // 1. Strip CASE:{...} and LAW:{...} citation markers
        $brief = preg_replace('/\[?CASE:\{[^}]*\}\]?/u', '', $brief);
        $brief = preg_replace('/\[?CASE:[A-Z0-9_]+\]?/i', '', $brief);
        $brief = preg_replace('/\[?LAW:\{[^}]*\}(?:\s*"[^"]*")?\]?/u', '', $brief);
        $brief = preg_replace('/\[?LAW:[A-Z0-9_]+\]?/i', '', $brief);

        // 2. Remove code-fence blocks (```json ... ``` or ``` ... ```)
        $brief = preg_replace('/```(?:json|javascript|yaml|xml|plaintext)?[\s\S]*?```/s', '', $brief);

        // 3. Remove raw JSON-object blocks spanning multiple lines (no Arabic content)
        $brief = preg_replace_callback(
            '/^[ \t]*\{[^{}]*\}[ \t]*,?[ \t]*$/ms',
            function ($m) {
                return preg_match('/\p{Arabic}/u', $m[0]) ? $m[0] : '';
            },
            $brief
        );

        // 4. Remove individual JSON key-value lines (English keys, no Arabic)
        // e.g.  "min_confidence": 0.70,   or   "defense_tiers": ["primary"],
        $brief = preg_replace('/^[ \t]*"[a-zA-Z_][a-zA-Z0-9_]*"\s*:\s*[^\n]+,?[ \t]*$/m', '', $brief);

        // 5. Remove standalone JSON bracket lines {  }  [  ]
        $brief = preg_replace('/^[ \t]*[{}\[\]][ \t]*,?[ \t]*$/m', '', $brief);

        // 6. Remove filename artifact lines (e.g. lead_plan.md_01, acceptance_criteria.json)
        $brief = preg_replace('/^[ \t]*[a-z0-9_\-]+(?:_\d+)?\.(?:md|json|jsonl|txt|pdf)(?:_\d+)?[ \t]*$/im', '', $brief);

        // 7. Remove confidence/accuracy score patterns
        $brief = preg_replace('/\bconfidence[:\s]*[\d.]+%?/i', '', $brief);
        $brief = preg_replace('/"confidence"\s*:\s*[\d.]+[,]?/i', '', $brief);

        // 8. Remove agent metadata headers (## Agent N: ...)
        $brief = preg_replace('/^#{1,4}\s*Agent\s*\d+.*$/mi', '', $brief);

        // 9. Remove ⚠️ غير مُسنَّدة paragraphs
        $brief = preg_replace('/[^\n]*⚠️\s*غير\s*مُسنَّدة[^\n]*/u', '', $brief);

        // 10. Remove technical counter/metadata lines (word count, token count, etc.)
        $brief = preg_replace('/^.*\b(?:word[s]?\s*count|token[s]?\s*count|character[s]?\s*count)\s*[=:]\s*\d+.*$/im', '', $brief);
        $brief = preg_replace('/^.*\b(?:tokens?|words?|chars?)\s*[=:]\s*\d+\b.*$/im', '', $brief);

        // 11. Remove === SECTION === style banners (no Arabic)
        $brief = preg_replace('/^[=\-#*]{3,}[^=\-#*\n]*[=\-#*]{3,}[ \t]*$/m', '', $brief);

        // 12. Remove all lines that contain no Arabic characters
        //     Exception: keep blank lines, Markdown structural syntax, pure-number/date lines
        $lines  = explode("\n", $brief);
        $filtered = [];
        foreach ($lines as $line) {
            $stripped = trim($line);

            // Always keep blank lines (paragraph spacing)
            if ($stripped === '') {
                $filtered[] = $line;
                continue;
            }

            // Keep lines that have Arabic text
            if (preg_match('/\p{Arabic}/u', $stripped)) {
                $filtered[] = $line;
                continue;
            }

            // Keep Markdown structural syntax: headings, list markers, horizontal rules, table rows
            if (preg_match('/^#{1,6}\s|^[-*+]\s|^\d+\.\s|^---+$|\*{3}$|^\s*\|/', $stripped)) {
                $filtered[] = $line;
                continue;
            }

            // Keep pure-number / date lines (digits, slashes, dashes, Arabic numerals)
            if (preg_match('/^[\d\s\-\/\.,،؛:؟!()\u0660-\u0669٠-٩]+$/', $stripped)) {
                $filtered[] = $line;
                continue;
            }

            // Skip everything else (English text, filenames, JSON artifacts, etc.)
        }
        $brief = implode("\n", $filtered);

        // 13. Ensure بسم الله الرحمن الرحيم as first non-empty line
        $preamble    = 'بسم الله الرحمن الرحيم';
        $trimmedBrief = ltrim($brief);
        if (!str_starts_with($trimmedBrief, $preamble)) {
            $brief = $preamble . "\n\n" . ltrim($brief);
        }

        // 14. Normalize whitespace — collapse 3+ consecutive newlines to 2
        $brief = preg_replace('/\n{3,}/', "\n\n", $brief);
        $brief = trim($brief);

        return $brief;
    }
}
