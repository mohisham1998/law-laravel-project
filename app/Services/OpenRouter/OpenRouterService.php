<?php

namespace App\Services\OpenRouter;

use App\Services\LLM\LLMServiceInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class OpenRouterService implements LLMServiceInterface
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
    public function complete(string $model, array $messages, ?float $temperature = null, ?int $maxTokens = null, array $meta = []): array
    {
        $lastException = null;
        for ($attempt = 0; $attempt <= $this->retryAttempts; $attempt++) {
            $attemptStart = microtime(true);
            Log::info('llm.request.start', array_merge($meta, [
                'attempt' => $attempt + 1,
                'mode' => 'non_stream',
                'model' => $model,
            ]));

            try {
                $data = $this->client->chat($model, $messages, $temperature, $maxTokens);
                $content = $data['choices'][0]['message']['content'] ?? '';
                $usage = $data['usage'] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];

                Log::info('llm.request.done', array_merge($meta, [
                    'attempt' => $attempt + 1,
                    'mode' => 'non_stream',
                    'model' => $model,
                    'elapsed_ms' => (int) round((microtime(true) - $attemptStart) * 1000),
                    'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                    'completion_tokens' => $usage['completion_tokens'] ?? 0,
                    'content_length' => mb_strlen((string) $content),
                ]));

                return [
                    'content' => $content,
                    'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                    'completion_tokens' => $usage['completion_tokens'] ?? 0,
                    'total_tokens' => $usage['total_tokens'] ?? 0,
                ];
            } catch (OpenRouterException $e) {
                $lastException = $e;
                Log::warning('llm.request.error', array_merge($meta, [
                    'attempt' => $attempt + 1,
                    'mode' => 'non_stream',
                    'model' => $model,
                    'elapsed_ms' => (int) round((microtime(true) - $attemptStart) * 1000),
                    'http_status' => $e->httpStatus,
                    'error' => $e->getMessage(),
                ]));

                if ($attempt < $this->retryAttempts && $this->isRetryable($e)) {
                    usleep($this->retryDelayMs * 1000);
                    continue;
                }
                throw $e;
            }
        }
        throw $lastException ?? new OpenRouterException('OpenRouter request failed');
    }

    /**
     * Stream completion with callback for each chunk.
     * 
     * @param string $model
     * @param array $messages
     * @param callable $onChunk Called with each text chunk: fn(string $chunk): void
     * @param float|null $temperature
     * @param int|null $maxTokens
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function completeStream(string $model, array $messages, callable $onChunk, ?float $temperature = null, ?int $maxTokens = null, array $meta = []): array
    {
        $lastException = null;
        for ($attempt = 0; $attempt <= $this->retryAttempts; $attempt++) {
            $attemptStart = microtime(true);
            $firstTokenLogged = false;
            $wrappedOnChunk = function (string $chunk) use ($onChunk, &$firstTokenLogged, $attemptStart, $meta, $attempt, $model): void {
                if (!$firstTokenLogged) {
                    $firstTokenLogged = true;
                    Log::info('llm.first_token', array_merge($meta, [
                        'attempt' => $attempt + 1,
                        'mode' => 'stream',
                        'model' => $model,
                        'elapsed_ms' => (int) round((microtime(true) - $attemptStart) * 1000),
                    ]));
                }

                $onChunk($chunk);
            };

            Log::info('llm.request.start', array_merge($meta, [
                'attempt' => $attempt + 1,
                'mode' => 'stream',
                'model' => $model,
            ]));

            try {
                $result = $this->client->chatStream($model, $messages, $wrappedOnChunk, $temperature, $maxTokens);

                Log::info('llm.request.done', array_merge($meta, [
                    'attempt' => $attempt + 1,
                    'mode' => 'stream',
                    'model' => $model,
                    'elapsed_ms' => (int) round((microtime(true) - $attemptStart) * 1000),
                    'prompt_tokens' => $result['prompt_tokens'] ?? 0,
                    'completion_tokens' => $result['completion_tokens'] ?? 0,
                    'content_length' => mb_strlen((string) ($result['content'] ?? '')),
                ]));

                return $result;
            } catch (OpenRouterException $e) {
                $lastException = $e;
                Log::warning('llm.request.error', array_merge($meta, [
                    'attempt' => $attempt + 1,
                    'mode' => 'stream',
                    'model' => $model,
                    'elapsed_ms' => (int) round((microtime(true) - $attemptStart) * 1000),
                    'http_status' => $e->httpStatus,
                    'error' => $e->getMessage(),
                ]));

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
