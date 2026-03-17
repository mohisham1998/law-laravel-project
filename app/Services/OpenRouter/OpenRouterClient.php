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
        if ($maxTokens !== null) {
            $body['max_tokens'] = $maxTokens;
        }
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
}
