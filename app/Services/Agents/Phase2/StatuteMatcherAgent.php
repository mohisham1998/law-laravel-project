<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;
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

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);
        
        // Extract search queries from Agent 5's output
        $searchQueries = $this->extractSearchQueriesFromAgent5($case);
        
        // Use RAG to find relevant articles
        $ragResults = $this->performRAGSearch($searchQueries);
        
        // Build enhanced context with RAG results
        $ragContext = $this->buildRAGContext($ragResults);
        $fullContext = $context . "\n\n## RAG Statute Candidates (Pre-filtered by Semantic Search)\n\n" . $ragContext;
        
        $prompt = $this->promptBuilder->buildPromptForAgent(6, $fullContext);
        $model = $case->model_used ?: config('openrouter.default_model');
        $result = $this->openRouter->complete($model, [
            ['role' => 'user', 'content' => $prompt . "\n\nExecute the Statute Matcher task. Use the RAG candidates provided. Output JSONL with statute matches (confidence >= 0.70)."],
        ], 0.2);
        $filename = '06_statutes_map.jsonl';
        $this->saveOutput($case, $filename, $result['content'], 'jsonl');
        return ['content' => $result['content'], 'filename' => $filename, 'prompt_tokens' => $result['prompt_tokens'], 'completion_tokens' => $result['completion_tokens']];
    }

    protected function extractSearchQueriesFromAgent5(LegalCase $case): array
    {
        // Try to read Agent 5's output
        $agent5Output = $case->outputs()->where('agent_number', 5)->first();
        
        if (!$agent5Output) {
            // Fallback: use intake text
            return [$case->intake_text];
        }

        $content = $agent5Output->content;
        if (!$content && $agent5Output->file_path) {
            $content = Storage::disk('local')->get($agent5Output->file_path);
        }

        // Extract queries from Agent 5's output
        // Look for sections like "Search Queries:" or "كلمات البحث:"
        $queries = [];
        
        if (preg_match_all('/(?:Search Query|كلمات البحث|استعلام):\s*(.+)/ui', $content, $matches)) {
            $queries = array_merge($queries, $matches[1]);
        }

        // Fallback: use intake text
        if (empty($queries)) {
            $queries[] = $case->intake_text;
        }

        return array_unique($queries);
    }

    protected function performRAGSearch(array $queries): array
    {
        try {
            $vectorSearch = app(VectorSearchService::class);
            $confidenceThreshold = auth()->user()->confidence_threshold ?? 0.70;
            
            return $vectorSearch->searchMultiple($queries, 15, $confidenceThreshold);
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
            return "تحذير: لم يتم العثور على مواد ذات صلة في قاعدة المعرفة. استخدم Logical Fallback.";
        }

        $context = "المواد المرشحة من قاعدة المعرفة (مرتبة حسب الصلة):\n\n";
        
        foreach ($ragResults as $idx => $result) {
            $article = $result['article'];
            $confidence = $result['confidence'];
            
            $context .= sprintf(
                "## [%d] %s - المادة %s (confidence: %.2f)\n%s\n\n",
                $idx + 1,
                $article->lawRegistry->name,
                $article->article_number,
                $confidence,
                $article->article_text
            );
        }

        $context .= "\nملاحظة: يجب التحقق من كل مادة قبل قبولها. الحد الأدنى للثقة: 0.70\n";
        
        return $context;
    }
}
