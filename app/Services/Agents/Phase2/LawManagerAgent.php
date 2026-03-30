<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;
use App\Services\RAG\EmbeddingService;
use App\Services\RAG\VectorSearchService;

class LawManagerAgent extends Phase2BaseAgent
{
    public function agentNumber(): int
    {
        return 5;
    }

    public function agentName(): string
    {
        return 'Law Manager';
    }

    /**
     * Agent 5 maps legal issues to statutes.
     * Needs: chunks (evidence), statutes index (law references), timeline (events).
     * Raw documents excluded — 02_chunks.jsonl already contains all document text.
     * Law library loaded via buildContext + own RAG vector search.
     */
    protected function requiredPriorOutputs(): ?array
    {
        return [
            '02_chunks.jsonl',           // Evidence chunks to map issues from
            '03_statutes_index.jsonl',   // Statutes index built by Agent 3
            '04_timeline.json',          // Timeline events to classify
        ];
    }

    protected function needsDocuments(): bool
    {
        return false; // 02_chunks.jsonl already contains full document text
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);

        // Generate search queries from case facts
        $searchQueries = $this->generateSearchQueries($case);
        $ragContext = $this->buildRAGContext($searchQueries, $case->getPuterToken());
        $fullContext = $context . "\n\n## RAG Search Results\n\n" . $ragContext;

        $prompt = $this->promptBuilder->buildPromptForAgent(5, $fullContext);

        // Append structural instruction for output parsing
        $prompt .= "\n\n---\n## تعليمات هيكلة المخرجات\n\nأنتج المخرجات بالترتيب التالي مفصولة بعلامات:\n- `---ISSUES_TO_STATUTES---` ثم ربط القضايا بالمواد بالعربية\n- `---PROCEDURAL_NOTES---` ثم الملاحظات الإجرائية بالعربية\n- `---ADVERSARY_ANALYSIS---` ثم التحليل العدائي بالعربية\n- `---MATCHING_GUIDELINES---` ثم إرشادات المطابقة بصيغة JSON";

        $result = $this->executeWithSelfCorrection($case, $prompt);

        $content = $result['content'] ?? '';

        // Parse sections
        $issuesToStatutes = $this->extractSection($content, 'ISSUES_TO_STATUTES', 'PROCEDURAL_NOTES');
        $proceduralNotes = $this->extractSection($content, 'PROCEDURAL_NOTES', 'ADVERSARY_ANALYSIS');
        $adversaryAnalysis = $this->extractSection($content, 'ADVERSARY_ANALYSIS', 'MATCHING_GUIDELINES');
        $matchingGuidelines = $this->extractSection($content, 'MATCHING_GUIDELINES', null);

        // Fallbacks
        if (empty(trim($issuesToStatutes))) $issuesToStatutes = $content;
        if (empty(trim($proceduralNotes))) $proceduralNotes = "# الملاحظات الإجرائية\n\nلم يتم تحديد ملاحظات إجرائية محددة.";
        if (empty(trim($adversaryAnalysis))) $adversaryAnalysis = "# التحليل العدائي\n\nلم يتم إجراء تحليل عدائي.";
        if (empty(trim($matchingGuidelines))) {
            $matchingGuidelines = json_encode([
                'priority_statutes' => [],
                'issue_statute_map' => [],
                'min_confidence' => 0.70,
                'fallback_allowed' => true,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        // Clean JSON
        $matchingGuidelines = preg_replace('/^```(?:json)?\s*/m', '', $matchingGuidelines);
        $matchingGuidelines = preg_replace('/\s*```\s*$/m', '', $matchingGuidelines);
        $matchingGuidelines = trim($matchingGuidelines);

        $this->saveOutputTyped($case, '05_issues_to_statutes.md', $issuesToStatutes, 'markdown', 'primary');
        $this->saveOutputTyped($case, '05_procedural_notes.md', $proceduralNotes, 'markdown', 'secondary');
        $this->saveOutputTyped($case, '05_adversary_evidence_analysis.md', $adversaryAnalysis, 'markdown', 'secondary');
        $this->saveOutputTyped($case, '05_matching_guidelines.json', $matchingGuidelines, 'json', 'secondary');

        return [
            'content' => $result['content'],
            'filename' => '05_issues_to_statutes.md',
            'output_files' => ['05_issues_to_statutes.md', '05_procedural_notes.md', '05_adversary_evidence_analysis.md', '05_matching_guidelines.json'],
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'corrections_count' => $result['corrections_count'] ?? 0,
        ];
    }

    protected function generateSearchQueries(LegalCase $case): array
    {
        $queries = [];
        $queries[] = $case->intake_text;
        if ($case->category) {
            $queries[] = "أحكام {$case->category}";
        }
        return $queries;
    }

    protected function buildRAGContext(array $queries, string $puterToken = ''): string
    {
        try {
            $vectorSearch = $this->makeVectorSearch($puterToken);
            $results = $vectorSearch->searchMultiple($queries, 10, 0.70);

            if (empty($results)) {
                return "لم يتم العثور على مواد ذات صلة في قاعدة المعرفة.";
            }

            $context = "المواد ذات الصلة من قاعدة المعرفة (RAG):\n\n";
            foreach ($results as $result) {
                $article = $result['article'];
                $similarity = $result['similarity'] ?? $result['confidence'] ?? 0;
                $context .= "### {$article->lawRegistry->name} - المادة {$article->article_number} (ثقة: " . number_format($similarity, 2) . ")\n";
                $context .= $article->article_text . "\n\n";
            }
            return $context;
        } catch (\Exception $e) {
            \Log::error('RAG search failed in LawManagerAgent', ['error' => $e->getMessage()]);
            return "فشل البحث في قاعدة المعرفة.";
        }
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
