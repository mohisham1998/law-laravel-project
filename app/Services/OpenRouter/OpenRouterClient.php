<?php

namespace App\Services\OpenRouter;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class OpenRouterClient
{
    public function __construct(
        protected Client $client,
        protected string $apiKey,
        protected string $baseUrl,
        protected int $timeout = 300,
    ) {
    }

    public function chat(string $model, array $messages, ?float $temperature = null, ?int $maxTokens = null): array
    {
        $body = ['model' => $model, 'messages' => $messages];
        if ($temperature !== null) {
            $body['temperature'] = $temperature;
        }
        // Always set max_tokens. When not specified by the caller we use a
        // conservative default so OpenRouter does not reserve the model's full
        // context window against the account credit balance upfront.
        $body['max_tokens'] = $maxTokens ?? 8192;
        try {
            $response = $this->client->post(rtrim($this->baseUrl, '/') . '/chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('app.url', 'https://example.com'),
                ],
                'json' => $body,
                'timeout' => $this->timeout,
            ]);
            $data = json_decode((string) $response->getBody(), true);
            if (!is_array($data)) {
                throw OpenRouterException::fromResponse(500, ['error' => ['message' => 'Invalid API response']]);
            }
            return $data;
        } catch (GuzzleException $e) {
            $status = 500;
            $body = $e->getMessage();
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $status = $e->getResponse()->getStatusCode();
                $body = (string) $e->getResponse()->getBody();
            }
            $decoded = json_decode($body, true) ?? ['error' => ['message' => $e->getMessage()]];
            throw OpenRouterException::fromResponse($status, $decoded);
        }
    }

    /**
     * Stream chat completion with callback for each chunk.
     * 
     * @param string $model
     * @param array $messages
     * @param callable $onChunk Called with each text chunk: fn(string $chunk): void
     * @param float|null $temperature
     * @param int|null $maxTokens
     * @return array{content: string, prompt_tokens: int, completion_tokens: int}
     */
    public function chatStream(string $model, array $messages, callable $onChunk, ?float $temperature = null, ?int $maxTokens = null): array
    {
        $body = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
        ];
        if ($temperature !== null) {
            $body['temperature'] = $temperature;
        }
        // Always set max_tokens — prevents OpenRouter from reserving the model's
        // full context window (e.g. 65536) against account credits upfront.
        $body['max_tokens'] = $maxTokens ?? 8192;

        $fullContent = '';
        $promptTokens = 0;
        $completionTokens = 0;

        try {
            $response = $this->client->post(rtrim($this->baseUrl, '/') . '/chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('app.url', 'https://example.com'),
                    'Accept' => 'text/event-stream',
                ],
                'json' => $body,
                'timeout' => $this->timeout,
                'stream' => true,
            ]);

            $body = $response->getBody();
            $buffer = '';
            
            while (!$body->eof()) {
                $chunk = $body->read(1024);
                $buffer .= $chunk;
                
                // Process complete SSE lines
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }
                    
                    if (str_starts_with($line, 'data: ')) {
                        $data = substr($line, 6);
                        
                        if ($data === '[DONE]') {
                            break 2;
                        }
                        
                        $decoded = json_decode($data, true);
                        if (!$decoded) {
                            continue;
                        }
                        
                        // Extract content delta
                        $delta = $decoded['choices'][0]['delta']['content'] ?? '';
                        if ($delta !== '') {
                            $fullContent .= $delta;
                            $onChunk($delta);
                        }
                        
                        // Extract usage if present (final chunk)
                        if (isset($decoded['usage'])) {
                            $promptTokens = $decoded['usage']['prompt_tokens'] ?? 0;
                            $completionTokens = $decoded['usage']['completion_tokens'] ?? 0;
                        }
                    }
                }
            }

            return [
                'content' => $fullContent,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens,
            ];
        } catch (GuzzleException $e) {
            $status = 500;
            $errBody = $e->getMessage();
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $status = $e->getResponse()->getStatusCode();
                $errBody = (string) $e->getResponse()->getBody();
            }
            $decoded = json_decode($errBody, true) ?? ['error' => ['message' => $e->getMessage()]];
            throw OpenRouterException::fromResponse($status, $decoded);
        }
    }
}
