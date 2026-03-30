<?php

namespace App\Services\LLM;

interface LLMServiceInterface
{
    /**
     * @param  array<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $meta
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function complete(
        string $model,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $meta = []
    ): array;

    /**
     * @param  callable(string): void  $onChunk
     * @param  array<string, mixed>  $meta
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function completeStream(
        string $model,
        array $messages,
        callable $onChunk,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $meta = []
    ): array;
}
