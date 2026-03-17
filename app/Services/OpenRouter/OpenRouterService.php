<?php

namespace App\Services\OpenRouter;

use GuzzleHttp\Client;

class OpenRouterService
{
    public function __construct(
        protected OpenRouterClient $client,
        protected int $retryAttempts = 3,
        protected int $retryDelayMs = 2000,
    ) {
    }

    public static function fromConfig(): self
    {
        $config = config('openrouter');
        $client = new OpenRouterClient(
            new Client(['timeout' => $config['timeout'] ?? 300]),
            $config['api_key'],
            rtrim($config['base_url'], '/'),
            $config['timeout'] ?? 300,
        );
        return new self(
            $client,
            $config['retry_attempts'] ?? 3,
            $config['retry_delay_ms'] ?? 2000,
        );
    }

    /**
     * @param  array<string, mixed>  $messages
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function complete(string $model, array $messages, ?float $temperature = null, ?int $maxTokens = null): array
    {
        $lastException = null;
        for ($attempt = 0; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                $data = $this->client->chat($model, $messages, $temperature, $maxTokens);
                $content = $data['choices'][0]['message']['content'] ?? '';
                $usage = $data['usage'] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
                return [
                    'content' => $content,
                    'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                    'completion_tokens' => $usage['completion_tokens'] ?? 0,
                    'total_tokens' => $usage['total_tokens'] ?? 0,
                ];
            } catch (OpenRouterException $e) {
                $lastException = $e;
                if ($attempt < $this->retryAttempts && $this->isRetryable($e)) {
                    usleep($this->retryDelayMs * 1000);
                    continue;
                }
                throw $e;
            }
        }
        throw $lastException ?? new OpenRouterException('OpenRouter request failed');
    }

    protected function isRetryable(OpenRouterException $e): bool
    {
        $retryableStatuses = [408, 429, 500, 502, 503, 504];
        return $e->httpStatus !== null && in_array($e->httpStatus, $retryableStatuses, true);
    }
}
