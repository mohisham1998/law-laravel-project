<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;
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

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);
        
        // Generate search queries from case facts
        $searchQueries = $this->generateSearchQueries($case);
        
        // Use RAG to find relevant articles
        $ragContext = $this->buildRAGContext($searchQueries);
        
        // Combine with existing context
        $fullContext = $context . "\n\n## RAG Search Results\n\n" . $ragContext;
        
        $prompt = $this->promptBuilder->buildPromptForAgent(5, $fullContext);
        $model = $case->model_used ?: config('openrouter.default_model');
        $result = $this->openRouter->complete($model, [
            ['role' => 'user', 'content' => $prompt . "\n\nExecute the Law Manager task. Output a laws summary in markdown with search queries for Agent 6."],
        ], 0.3);
        $filename = '05_laws_summary.md';
        $this->saveOutput($case, $filename, $result['content'], 'markdown');
        return ['content' => $result['content'], 'filename' => $filename, 'prompt_tokens' => $result['prompt_tokens'], 'completion_tokens' => $result['completion_tokens']];
    }

    protected function generateSearchQueries(LegalCase $case): array
    {
        // Extract key legal concepts from intake text
        $intakeText = $case->intake_text;
        
        // Simple keyword extraction (can be enhanced with NLP)
        $queries = [];
        
        // Add the full intake as a query
        $queries[] = $intakeText;
        
        // Add category-specific queries
        if ($case->category) {
            $queries[] = "أحكام {$case->category}";
        }
        
        return $queries;
    }

    protected function buildRAGContext(array $queries): string
    {
        try {
            $vectorSearch = app(VectorSearchService::class);
            $results = $vectorSearch->searchMultiple($queries, 10, 0.70);
            
            if (empty($results)) {
                return "لم يتم العثور على مواد ذات صلة في قاعدة المعرفة.";
            }
            
            $context = "المواد ذات الصلة من قاعدة المعرفة (RAG):\n\n";
            
            foreach ($results as $result) {
                $article = $result['article'];
                $similarity = $result['similarity'];
                
                $context .= "### {$article->lawRegistry->name} - المادة {$article->article_number} (ثقة: " . number_format($similarity, 2) . ")\n";
                $context .= $article->article_text . "\n\n";
            }
            
            return $context;
        } catch (\Exception $e) {
            \Log::error('RAG search failed in LawManagerAgent', ['error' => $e->getMessage()]);
            return "فشل البحث في قاعدة المعرفة.";
        }
    }
}
