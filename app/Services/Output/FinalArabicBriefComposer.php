<?php

namespace App\Services\Output;

use App\Models\LegalCase;

/**
 * Selects the best available brief version from a completed case
 * and returns a clean, post-processed Arabic brief.
 *
 * Selection priority: v3 (Agent 12) > v2 (Agent 9) > v1 (Agent 8)
 */
class FinalArabicBriefComposer
{
    /**
     * Compose the final Arabic brief for a case.
     *
     * Selects the longest valid brief from all candidates (≥ 800 chars after trimming).
     * Longer briefs indicate more complete content (more legal detail, appendices, etc.).
     * Preferred quality order is used as a tiebreaker when lengths are within 10% of each other.
     *
     * @return string|null  Clean brief, or null if no brief found
     */
    public static function compose(LegalCase $case): ?string
    {
        // Ordered by preference (tiebreaker when lengths are similar)
        $candidates = [
            '15_final_brief_enriched.md',
            '14_final_brief_polished.md',
            '13_final_brief_v3.md',
            '09_final_brief_v2.md',
            '08_final_brief.md',
        ];

        $best = null;      // Best content found so far
        $bestLength = 0;   // Length of best content

        foreach ($candidates as $filename) {
            $output = $case->outputs()->where('filename', $filename)->first();
            if (!$output) {
                continue;
            }

            // Get content from DB or file
            $content = $output->content;
            if (empty(trim((string) $content)) && $output->file_path) {
                $full = \Illuminate\Support\Facades\Storage::disk('local')->path($output->file_path);
                if (file_exists($full)) {
                    $content = file_get_contents($full);
                }
            }

            $content = (string) $content;
            $length = mb_strlen(trim($content));

            if ($length < 800) {
                continue; // Too short (truncated/empty), skip
            }

            // Accept this candidate if it's meaningfully longer than current best
            // "meaningfully longer" = more than 10% longer (avoids swapping for tiny gains)
            if ($best === null || $length > $bestLength * 1.10) {
                $best = $content;
                $bestLength = $length;
            }
        }

        if ($best === null) {
            return null;
        }

        // Apply post-processing and return
        return BriefPostProcessor::process($best);
    }
}
