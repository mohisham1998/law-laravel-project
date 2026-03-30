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
     * @return string|null  Clean brief, or null if no brief found
     */
    public static function compose(LegalCase $case): ?string
    {
        // Priority: polished (Agent 13) > v3 (Agent 12) > v2 (Agent 9) > v1 (Agent 8)
        $candidates = [
            '14_final_brief_polished.md',
            '13_final_brief_v3.md',
            '09_final_brief_v2.md',
            '08_final_brief.md',
        ];

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
            if (mb_strlen(trim($content)) < 5000) {
                continue; // Too short (truncated), skip to next candidate
            }

            // Apply post-processing and return
            return BriefPostProcessor::process($content);
        }

        return null;
    }
}
