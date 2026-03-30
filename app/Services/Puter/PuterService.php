<?php

namespace App\Services\Puter;

use App\Services\LLM\LLMServiceInterface;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PuterService implements LLMServiceInterface
{
    public function __construct(
        protected string $puterToken,
    ) {
    }

    public static function fromConfig(string $puterToken): self
    {
        return new self($puterToken);
    }

    /**
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function complete(
        string $model,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $meta = []
    ): array {
        if (empty($this->puterToken)) {
            throw PuterException::authRequired();
        }

        $resolvedModel = $this->normalizeModelId($model);

        Log::info('puter.request.start', array_merge($meta, [
            'model' => $resolvedModel,
            'mode'  => 'non_stream',
        ]));

        $data = $this->driverComplete($resolvedModel, $messages, $temperature, $maxTokens, false);
        $content = $data['content'];
        $usage = $data['usage'];

        Log::info('puter.request.done', array_merge($meta, [
            'model'             => $resolvedModel,
            'mode'              => 'non_stream',
            'prompt_tokens'     => $usage['prompt_tokens'] ?? 0,
            'completion_tokens' => $usage['completion_tokens'] ?? 0,
        ]));

        return [
            'content'           => (string) $content,
            'prompt_tokens'     => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'total_tokens'      => (int) ($usage['total_tokens'] ?? 0),
        ];
    }

    /**
     * @param  callable(string): void  $onChunk
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function completeStream(
        string $model,
        array $messages,
        callable $onChunk,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $meta = []
    ): array {
        if (empty($this->puterToken)) {
            throw PuterException::authRequired();
        }

        $resolvedModel = $this->normalizeModelId($model);

        Log::info('puter.request.start', array_merge($meta, [
            'model' => $resolvedModel,
            'mode'  => 'stream_emulated',
        ]));

        $startTime = microtime(true);
        $data = $this->driverComplete($resolvedModel, $messages, $temperature, $maxTokens, true);

        $fullContent = $data['content'];
        $promptTokens = (int) ($data['usage']['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($data['usage']['completion_tokens'] ?? 0);

        $chunkSize = 120;
        $len = mb_strlen($fullContent);
        for ($i = 0; $i < $len; $i += $chunkSize) {
            $onChunk(mb_substr($fullContent, $i, $chunkSize));
        }

        Log::info('puter.request.done', array_merge($meta, [
            'model'             => $resolvedModel,
            'mode'              => 'stream_emulated',
            'prompt_tokens'     => $promptTokens,
            'completion_tokens' => $completionTokens,
            'elapsed_ms'        => (int) round((microtime(true) - $startTime) * 1000),
        ]));

        return [
            'content'           => $fullContent,
            'prompt_tokens'     => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens'      => $promptTokens + $completionTokens,
        ];
    }

    private function driverComplete(string $model, array $messages, ?float $temperature, ?int $maxTokens, bool $stream): array
    {
        $endpoint = rtrim(config('puter.api_base_url', 'https://api.puter.com'), '/') . '/drivers/call';
        $cookieJar = new CookieJar();

        $this->warmUpPuterSession($cookieJar);

        $send = function (?float $temp) use ($endpoint, $cookieJar, $messages, $model, $maxTokens, $stream) {
            $args = [
                'messages' => $messages,
                'model' => $model,
            ];

            // Puter delegate for current GPT-5 models rejects custom temperature values.
            // Omit temperature entirely so provider default is used.
            if ($maxTokens !== null) {
                $args['max_tokens'] = $maxTokens;
            }
            // Native stream payloads return event chunks; this adapter emulates streaming from full text.

            $payload = [
                'interface' => 'puter-chat-completion',
                'driver' => 'ai-chat',
                'test_mode' => false,
                'method' => 'complete',
                'args' => $args,
                'auth_token' => $this->puterToken,
            ];

            return Http::timeout(300)
                ->withOptions(['cookies' => $cookieJar])
                ->withHeaders($this->driverHeaders())
                ->send('POST', $endpoint, [
                    'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
        };

        $request = $send($temperature);

        if (!$request->successful()) {
            $status = $request->status();
            match (true) {
                $status === 401 => throw PuterException::authExpired(),
                $status === 403 => throw PuterException::networkError('HTTP 403 (drivers/call blocked)'),
                $status === 404 => throw PuterException::modelUnavailable($model),
                $status === 429 => throw PuterException::quotaExceeded(),
                default         => throw PuterException::networkError("HTTP {$status}"),
            };
        }

        $json = $request->json();
        if (!is_array($json)) {
            Log::warning('puter.drivers_call.unexpected_response', [
                'status' => $request->status(),
                'body' => (string) $request->body(),
            ]);
            throw PuterException::networkError('Invalid Puter response shape');
        }

        if (!($json['success'] ?? false)) {
            $errorMessage = (string) ($json['error']['message'] ?? 'Puter call failed');

            if ($temperature !== null && str_contains(strtolower($errorMessage), 'temperature')) {
                $retry = $send(null);
                if ($retry->successful()) {
                    $retryJson = $retry->json();
                    if (is_array($retryJson) && ($retryJson['success'] ?? false)) {
                        $json = $retryJson;
                    } else {
                        $errorMessage = (string) ($retryJson['error']['message'] ?? $errorMessage);
                    }
                }
            }

            if (!($json['success'] ?? false)) {
                $errorStatus = (int) ($json['error']['status'] ?? 0);
                if ($errorStatus === 429) {
                    throw PuterException::quotaExceeded();
                }
                if ($errorStatus === 401) {
                    throw PuterException::authExpired();
                }
                if ($errorStatus === 404) {
                    throw PuterException::modelUnavailable($model);
                }

                throw PuterException::networkError($errorMessage);
            }
        }

        $result = $json['result'] ?? [];
        $usage = $result['usage'] ?? [];
        $content = $result['message']['content'] ?? '';

        return [
            'content' => (string) $content,
            'usage' => [
                'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
                'total_tokens' => (int) (($usage['prompt_tokens'] ?? 0) + ($usage['completion_tokens'] ?? 0)),
            ],
        ];
    }

    private function warmUpPuterSession(CookieJar $cookieJar): void
    {
        $whoamiEndpoint = rtrim(config('puter.api_base_url', 'https://api.puter.com'), '/') . '/whoami';

        $response = Http::timeout(20)
            ->withOptions(['cookies' => $cookieJar])
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->puterToken,
                'Origin' => config('app.url', 'http://127.0.0.1:8000'),
                'Referer' => rtrim(config('app.url', 'http://127.0.0.1:8000'), '/') . '/',
                'User-Agent' => $this->browserLikeUserAgent(),
                'Accept' => 'application/json',
            ])
            ->get($whoamiEndpoint);

        if ($response->status() === 401) {
            throw PuterException::authExpired();
        }
        if (!$response->successful()) {
            throw PuterException::networkError('whoami preflight failed: HTTP ' . $response->status());
        }
    }

    private function driverHeaders(): array
    {
        return [
            'Accept' => '*/*',
            'Content-Type' => 'text/plain;actually=json',
            'Origin' => config('app.url', 'http://127.0.0.1:8000'),
            'Referer' => rtrim(config('app.url', 'http://127.0.0.1:8000'), '/') . '/',
            'User-Agent' => $this->browserLikeUserAgent(),
        ];
    }

    private function browserLikeUserAgent(): string
    {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36';
    }

    private function normalizeModelId(string $model): string
    {
        $resolved = trim($model);

        if (str_starts_with($resolved, 'openrouter:')) {
            $resolved = substr($resolved, strlen('openrouter:'));
        }

        if (str_ends_with($resolved, ':free')) {
            $resolved = substr($resolved, 0, -strlen(':free'));
        }

        return $resolved !== '' ? $resolved : 'gpt-5-nano';
    }
}
