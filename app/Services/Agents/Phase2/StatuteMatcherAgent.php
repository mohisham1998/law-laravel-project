<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;
use App\Services\ErrorMemoryService;
use App\Services\Orchestration\OutputValidator;
use App\Services\RAG\EmbeddingService;
use App\Services\RAG\VectorSearchService;
use Illuminate\Support\Facades\Storage;

class StatuteMatcherAgent extends Phase2BaseAgent
{
    public function agentNumber(): int
    {
        return 6;
    }

    public function agentName(): string
    {
        return 'Statute Matcher';
    }

    /**
     * Agent 6 matches evidence chunks to statutes.
     * Needs: chunks (what to match), statutes index (what to match against),
     * matching guidelines from Agent 5 (how to match), conflict warnings (what to avoid).
     * Raw documents excluded — 02_chunks.jsonl already contains all document text.
     */
    protected function requiredPriorOutputs(): ?array
    {
        return [
            '02_chunks.jsonl',              // Evidence chunks to match
            '03_statutes_index.jsonl',      // The statute registry (source of truth)
            '03_conflict_warnings.md',      // Abrogated/conflicting statutes to avoid
            '05_matching_guidelines.json',  // Matching rules from Agent 5
        ];
    }

    protected function needsDocuments(): bool
    {
        return false; // 02_chunks.jsonl already contains full document text
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);

        // Extract search queries from Agent 5's output
        $searchQueries = $this->extractSearchQueriesFromAgent5($case);
        $ragResults = $this->performRAGSearch($searchQueries, $case->getPuterToken());
        $ragContext = $this->buildRAGContext($ragResults);
        $fullContext = $context . "\n\n## RAG Statute Candidates\n\n" . $ragContext;

        $prompt = $this->promptBuilder->buildPromptForAgent(6, $fullContext);

        // Append structural instruction for output parsing
        $prompt .= "\n\n---\n## تعليمات هيكلة المخرجات\n\nأنتج المخرجات بالترتيب:\n- `---STATUTES_MAP---` ثم JSONL\n- `---ACCEPTED_MATCHES---` ثم ملخص المطابقات المقبولة بالعربية\n- `---REJECTIONS---` ثم المطابقات المرفوضة مع الأسباب بالعربية\n- `---GAPS_TODO---` ثم الأجزاء بدون مطابقة";

        $result = $this->executeWithSelfCorrection($case, $prompt);

        $content = $result['content'] ?? '';

        // Parse sections
        $statutesMap = $this->extractSection($content, 'STATUTES_MAP', 'ACCEPTED_MATCHES');
        $acceptedMatches = $this->extractSection($content, 'ACCEPTED_MATCHES', 'REJECTIONS');
        $rejections = $this->extractSection($content, 'REJECTIONS', 'GAPS_TODO');
        $gapsTodo = $this->extractSection($content, 'GAPS_TODO', null);

        // Fallbacks
        if (empty(trim($statutesMap))) $statutesMap = $content;
        if (empty(trim($acceptedMatches))) $acceptedMatches = "# المطابقات المقبولة\n\nلم يتم تحديد مطابقات.";
        if (empty(trim($rejections))) $rejections = "# المطابقات المرفوضة\n\nلا توجد مطابقات مرفوضة.";
        if (empty(trim($gapsTodo))) $gapsTodo = "# الفجوات والمهام المتبقية\n\nلا توجد فجوات.";

        // Clean JSONL
        $statutesMap = preg_replace('/^```(?:jsonl?)?\s*/m', '', $statutesMap);
        $statutesMap = preg_replace('/\s*```\s*$/m', '', $statutesMap);
        $statutesMap = trim($statutesMap);

        $this->saveOutputTyped($case, '06_statutes_map.jsonl', $statutesMap, 'jsonl', 'primary');
        $this->saveOutputTyped($case, '06_accepted_matches.md', $acceptedMatches, 'markdown', 'secondary');
        $this->saveOutputTyped($case, '06_rejections.md', $rejections, 'markdown', 'secondary');
        $this->saveOutputTyped($case, '06_gaps_and_todo.md', $gapsTodo, 'markdown', 'secondary');

        return [
            'content' => $result['content'],
            'filename' => '06_statutes_map.jsonl',
            'output_files' => ['06_statutes_map.jsonl', '06_accepted_matches.md', '06_rejections.md', '06_gaps_and_todo.md'],
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'corrections_count' => $result['corrections_count'] ?? 0,
        ];
    }

    protected function extractSearchQueriesFromAgent5(LegalCase $case): array
    {
        $agent5Output = $case->outputs()->where('agent_number', 5)->first();

        if (!$agent5Output) {
            return [$case->intake_text];
        }

        $content = $agent5Output->content;
        if (!$content && $agent5Output->file_path) {
            $content = Storage::disk('local')->get($agent5Output->file_path);
        }

        $queries = [];
        if (preg_match_all('/(?:Search Query|كلمات البحث|استعلام):\s*(.+)/ui', $content, $matches)) {
            $queries = array_merge($queries, $matches[1]);
        }

        if (empty($queries)) {
            $queries[] = $case->intake_text;
        }

        return array_unique($queries);
    }

    protected function performRAGSearch(array $queries, string $puterToken = ''): array
    {
        try {
            $vectorSearch = $this->makeVectorSearch($puterToken);
            return $vectorSearch->searchMultiple($queries, 15, 0.70);
        } catch (\Exception $e) {
            \Log::error('RAG search failed in StatuteMatcherAgent', [
                'error' => $e->getMessage(),
                'queries' => $queries,
            ]);
            return [];
        }
    }

    protected function buildRAGContext(array $ragResults): string
    {
        if (empty($ragResults)) {
            return "تحذير: لم يتم العثور على مواد ذات صلة. استخدم الاحتياط المنطقي (القواعد الفقهية).";
        }

        $context = "المواد المرشحة من قاعدة المعرفة (مرتبة حسب الصلة):\n\n";
        foreach ($ragResults as $idx => $result) {
            $article = $result['article'];
            $confidence = $result['confidence'] ?? $result['similarity'] ?? 0;
            $context .= sprintf(
                "## [%d] %s - المادة %s (confidence: %.2f)\n%s\n\n",
                $idx + 1,
                $article->lawRegistry->name ?? '',
                $article->article_number,
                $confidence,
                $article->article_text
            );
        }
        $context .= "\nالحد الأدنى للثقة: 0.70 | استخدم الاحتياط المنطقي للمطابقات 0.50-0.69\n";
        return $context;
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

    protected function validateOutput(string $output, LegalCase $case): array
    {
        $violations = parent::validateOutput($output, $case);

        // Extract STATUTES_MAP section for validation
        $mapSection = $this->extractSection($output, 'STATUTES_MAP', 'ACCEPTED_MATCHES');
        if (empty(trim($mapSection))) {
            $mapSection = $output; // Fallback: validate entire output
        }
        $mapSection = preg_replace('/^```(?:jsonl?)?\s*/m', '', $mapSection);
        $mapSection = preg_replace('/\s*```\s*$/m', '', $mapSection);

        // T029: Validate JSONL format
        $violations = array_merge($violations, OutputValidator::validateJsonl($mapSection));

        // Load statutes index for cross-validation
        $statutesIndex = $this->loadPriorOutput($case, '03_statutes_index.jsonl');
        if (!empty($statutesIndex)) {
            // T029: Validate all statute_ids exist in the index
            $violations = array_merge($violations, OutputValidator::validateStatuteIds($mapSection, $statutesIndex));

            // T030: Validate quoted_text matches source content
            $violations = array_merge($violations, OutputValidator::validateQuotedText($mapSection, $statutesIndex));

            // T031: Validate no abrogated statutes cited as valid
            $violations = array_merge($violations, OutputValidator::validateNoAbrogated($mapSection, $statutesIndex));
        }

        // T032: Validate confidence floor
        $violations = array_merge($violations, OutputValidator::validateConfidenceFloor($mapSection));

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
