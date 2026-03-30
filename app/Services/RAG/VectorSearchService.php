<?php

namespace App\Services\RAG;

use App\Models\LawArticle;
use App\Models\LawEmbedding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VectorSearchService
{
    public function __construct(
        protected EmbeddingService $embeddingService
    ) {}

    /**
     * Search for relevant law articles using semantic search
     */
    public function search(string $query, int $topK = 20, float $minSimilarity = 0.70): array
    {
        // Generate embedding for query
        $queryEmbeddingData = $this->embeddingService->generateEmbedding($query);
        $queryVector = $queryEmbeddingData['embedding'];

        if (empty($queryVector)) {
            Log::warning('Empty query vector generated', ['query' => $query]);
            return [];
        }

        // Get all embeddings from database
        $embeddings = LawEmbedding::with(['lawArticle.lawRegistry', 'lawArticle.lawFile'])->get();

        if ($embeddings->isEmpty()) {
            Log::warning('No law embeddings found in database');
            return [];
        }

        $results = [];
        $failedCount = 0;

        foreach ($embeddings as $embedding) {
            $articleVector = $embedding->getVectorArray();
            
            if (empty($articleVector)) {
                // Skip embeddings that failed to load (corrupted/empty)
                $failedCount++;
                continue;
            }

            try {
                $similarity = LawEmbedding::cosineSimilarity($queryVector, $articleVector);
                
                if ($similarity >= $minSimilarity) {
                    $results[] = [
                        'article' => $embedding->lawArticle,
                        'similarity' => $similarity,
                        'confidence' => $similarity,
                    ];
                }
            } catch (\Exception $e) {
                $failedCount++;
                Log::error('Similarity calculation failed', [
                    'article_id' => $embedding->law_article_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Log warning if many embeddings failed
        if ($failedCount > 0) {
            Log::warning("Vector search: {$failedCount} embeddings failed to process", [
                'query' => $query,
                'total_embeddings' => $embeddings->count(),
            ]);
        }

        // Sort by similarity (highest first)
        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        // Return top K results
        return array_slice($results, 0, $topK);
    }

    /**
     * Search with multiple queries (for complex cases)
     */
    public function searchMultiple(array $queries, int $topKPerQuery = 10, float $minSimilarity = 0.70): array
    {
        $allResults = [];
        $seenArticleIds = [];

        foreach ($queries as $query) {
            $results = $this->search($query, $topKPerQuery, $minSimilarity);
            
            foreach ($results as $result) {
                $articleId = $result['article']->id;
                
                // Avoid duplicates, keep highest similarity
                if (!isset($seenArticleIds[$articleId]) || $result['similarity'] > $allResults[$seenArticleIds[$articleId]]['similarity']) {
                    if (isset($seenArticleIds[$articleId])) {
                        unset($allResults[$seenArticleIds[$articleId]]);
                    }
                    
                    $seenArticleIds[$articleId] = count($allResults);
                    $allResults[] = $result;
                }
            }
        }

        // Sort by similarity
        usort($allResults, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return $allResults;
    }

    /**
     * Search within a specific law
     */
    public function searchInLaw(int $lawRegistryId, string $query, int $topK = 10, float $minSimilarity = 0.70): array
    {
        $queryEmbeddingData = $this->embeddingService->generateEmbedding($query);
        $queryVector = $queryEmbeddingData['embedding'];

        if (empty($queryVector)) {
            return [];
        }

        $embeddings = LawEmbedding::whereHas('lawArticle', function ($q) use ($lawRegistryId) {
            $q->where('law_registry_id', $lawRegistryId);
        })->with(['lawArticle.lawRegistry', 'lawArticle.lawFile'])->get();

        $results = [];

        foreach ($embeddings as $embedding) {
            $articleVector = $embedding->getVectorArray();
            
            if (empty($articleVector)) {
                continue;
            }

            $similarity = LawEmbedding::cosineSimilarity($queryVector, $articleVector);
            
            if ($similarity >= $minSimilarity) {
                $results[] = [
                    'article' => $embedding->lawArticle,
                    'similarity' => $similarity,
                    'confidence' => $similarity,
                ];
            }
        }

        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($results, 0, $topK);
    }

    /**
     * Get similar articles to a given article
     */
    public function findSimilarArticles(LawArticle $article, int $topK = 5): array
    {
        $embedding = $article->embedding;
        
        if (!$embedding) {
            return [];
        }

        $articleVector = $embedding->getVectorArray();
        
        if (empty($articleVector)) {
            return [];
        }

        $allEmbeddings = LawEmbedding::where('law_article_id', '!=', $article->id)
            ->with(['lawArticle.lawRegistry'])
            ->get();

        $results = [];

        foreach ($allEmbeddings as $otherEmbedding) {
            $otherVector = $otherEmbedding->getVectorArray();
            
            if (empty($otherVector)) {
                continue;
            }

            $similarity = LawEmbedding::cosineSimilarity($articleVector, $otherVector);
            
            $results[] = [
                'article' => $otherEmbedding->lawArticle,
                'similarity' => $similarity,
            ];
        }

        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($results, 0, $topK);
    }
}
