<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;

class TimelineExtractorAgent extends Phase2BaseAgent
{
    public function agentNumber(): int
    {
        return 4;
    }

    public function agentName(): string
    {
        return 'Timeline Extractor';
    }

    /**
     * Agent 4 extracts a timeline purely from the chunked evidence (02_chunks.jsonl).
     * The raw documents are NOT needed — 02_chunks.jsonl already contains all the text.
     * Including both would nearly double the token count with duplicate content.
     * No law library needed — timeline extraction has no statute-matching step.
     */
    protected function requiredPriorOutputs(): ?array
    {
        return ['02_chunks.jsonl'];
    }

    protected function needsDocuments(): bool
    {
        return false; // 02_chunks.jsonl already contains all document text
    }

    protected function needsLawLibrary(): bool
    {
        return false; // Extracting timeline events — no law matching needed
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);
        $prompt = $this->promptBuilder->buildPromptForAgent(4, $context);

        // Append structural instruction for output parsing
        $prompt .= "\n\n---\n## تعليمات هيكلة المخرجات\n\nأنتج JSON أولاً ثم السرد ثم الكيانات. افصل بينها بالعلامات المحددة:\n- `---TIMELINE_PROSE---` ثم اكتب سرداً نثرياً بالعربية\n- `---ENTITIES---` ثم اذكر الكيانات المسماة (أشخاص، مؤسسات، أماكن، تواريخ)";

        $result = $this->executeWithSelfCorrection($case, $prompt);

        $content = $result['content'] ?? '';

        // Parse sections
        $jsonPart = $this->extractBefore($content, 'TIMELINE_PROSE');
        $prosePart = $this->extractBetween($content, 'TIMELINE_PROSE', 'ENTITIES');
        $entitiesPart = $this->extractAfter($content, 'ENTITIES');

        // Clean JSON
        $jsonPart = preg_replace('/^```(?:json)?\s*/m', '', $jsonPart);
        $jsonPart = preg_replace('/\s*```\s*$/m', '', $jsonPart);
        $jsonPart = trim($jsonPart);

        // Fallbacks
        if (empty($prosePart)) {
            $prosePart = "# الجدول الزمني\n\n" . $content;
        }
        if (empty($entitiesPart)) {
            $entitiesPart = "# فهرس الكيانات\n\nلم يتم استخراج كيانات محددة.";
        }

        $this->saveOutputTyped($case, '04_timeline.json', $jsonPart, 'json', 'primary');
        $this->saveOutputTyped($case, '04_timeline.md', $prosePart, 'markdown', 'secondary');
        $this->saveOutputTyped($case, '04_entities_index.md', $entitiesPart, 'markdown', 'secondary');

        return [
            'content' => $result['content'],
            'filename' => '04_timeline.json',
            'output_files' => ['04_timeline.json', '04_timeline.md', '04_entities_index.md'],
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'corrections_count' => $result['corrections_count'] ?? 0,
        ];
    }

    protected function extractBefore(string $content, string $marker): string
    {
        $pos = strpos($content, "---{$marker}---");
        if ($pos === false) {
            return $content;
        }
        return trim(substr($content, 0, $pos));
    }

    protected function extractBetween(string $content, string $start, string $end): string
    {
        $startPos = strpos($content, "---{$start}---");
        if ($startPos === false) return '';
        $startPos += strlen("---{$start}---");

        $endPos = strpos($content, "---{$end}---", $startPos);
        if ($endPos === false) return trim(substr($content, $startPos));

        return trim(substr($content, $startPos, $endPos - $startPos));
    }

    protected function extractAfter(string $content, string $marker): string
    {
        $pos = strpos($content, "---{$marker}---");
        if ($pos === false) return '';
        return trim(substr($content, $pos + strlen("---{$marker}---")));
    }
}
