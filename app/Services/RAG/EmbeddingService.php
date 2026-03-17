<?php

namespace App\Services\RAG;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;

    public function __construct()
    {
        // Use OpenRouter API (same as AI agents)
        $this->apiKey = config('openrouter.api_key', env('OPENROUTER_API_KEY', ''));
        $this->baseUrl = 'https://openrouter.ai/api/v1';
        
        // Always use OpenAI's text-embedding-3-small for embeddings (fast & cheap)
        $this->model = 'openai/text-embedding-3-small';
    }

    /**
     * Generate embedding for a single text using OpenRouter's embeddings API
     */
    public function generateEmbedding(string $text): array
    {
        if (empty($this->apiKey)) {
            Log::warning('OpenRouter API key not set; using fallback embedding');
            return $this->generateFallbackEmbedding($text);
        }

        try {
            // Use OpenRouter's embeddings endpoint (OpenAI-compatible)
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
                'X-Title' => 'Saudi Legal Orchestrator - RAG',
            ])->timeout(30)->post("{$this->baseUrl}/embeddings", [
                'model' => $this->model,
                'input' => $text,
            ]);

            if (!$response->successful()) {
                Log::error('OpenRouter embedding API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                // Fallback: Generate deterministic embedding from text
                return $this->generateFallbackEmbedding($text);
            }

            $data = $response->json();
            
            // OpenAI-compatible response format
            $embedding = $data['data'][0]['embedding'] ?? [];
            
            if (empty($embedding)) {
                Log::warning('Empty embedding returned from API');
                return $this->generateFallbackEmbedding($text);
            }
            
            return [
                'embedding' => $embedding,
                'model' => $this->model,
                'usage' => $data['usage'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Embedding generation failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text),
            ]);
            
            // Fallback: Generate deterministic embedding
            return $this->generateFallbackEmbedding($text);
        }
    }


    /**
     * Generate deterministic embedding from text (fallback)
     * Uses TF-IDF-like approach with Arabic support
     */
    protected function generateFallbackEmbedding(string $text): array
    {
        // Normalize Arabic text
        $text = mb_strtolower($text);
        
        // Extract words (Arabic + English)
        preg_match_all('/[\p{Arabic}\w]+/u', $text, $matches);
        $words = $matches[0] ?? [];
        
        // Create word frequency map
        $wordFreq = array_count_values($words);
        
        // Generate 1536-dimensional embedding
        $embedding = [];
        $seed = crc32($text); // Deterministic seed from text
        mt_srand($seed);
        
        for ($i = 0; $i < 1536; $i++) {
            // Use word frequencies to influence embedding values
            $value = 0.0;
            foreach ($wordFreq as $word => $freq) {
                $wordHash = crc32($word . $i);
                $value += (($wordHash % 1000) / 1000 - 0.5) * ($freq / count($words));
            }
            
            // Normalize to [-1, 1]
            $embedding[] = max(-1, min(1, $value));
        }
        
        Log::warning('Using fallback embedding generation', [
            'text_length' => strlen($text),
            'word_count' => count($words),
        ]);
        
        return [
            'embedding' => $embedding,
            'model' => 'fallback-tfidf',
            'usage' => [],
        ];
    }

    /**
     * Generate embeddings for multiple texts (batch)
     */
    public function generateEmbeddingsBatch(array $texts): array
    {
        if (empty($this->apiKey)) {
            Log::warning('OpenRouter API key not set; using fallback embeddings for batch');
            $out = [];
            foreach ($texts as $text) {
                $out[] = $this->generateFallbackEmbedding($text)['embedding'];
            }
            return $out;
        }

        // OpenRouter supports batch embeddings (up to 2048 inputs)
        $chunks = array_chunk($texts, 2048);
        $allEmbeddings = [];

        foreach ($chunks as $chunk) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => 'Saudi Legal Orchestrator - RAG',
                ])->timeout(60)->post("{$this->baseUrl}/embeddings", [
                    'model' => $this->model,
                    'input' => $chunk,
                ]);

                if (!$response->successful()) {
                    Log::error('OpenRouter batch embedding API error', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    
                    // Fallback for this chunk
                    foreach ($chunk as $text) {
                        $result = $this->generateFallbackEmbedding($text);
                        $allEmbeddings[] = $result['embedding'];
                    }
                    continue;
                }

                $data = $response->json();
                
                foreach ($data['data'] as $item) {
                    $allEmbeddings[] = $item['embedding'];
                }
            } catch (\Exception $e) {
                Log::error('Batch embedding generation failed', [
                    'error' => $e->getMessage(),
                    'chunk_size' => count($chunk),
                ]);
                
                // Fallback for this chunk
                foreach ($chunk as $text) {
                    $result = $this->generateFallbackEmbedding($text);
                    $allEmbeddings[] = $result['embedding'];
                }
            }
        }

        return $allEmbeddings;
    }

    /**
     * Get embedding dimensions (always 1536)
     */
    public function getEmbeddingDimensions(): int
    {
        return 1536;
    }
}
