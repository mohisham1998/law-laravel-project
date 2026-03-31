<?php

namespace App\Services\LLM;

use App\Services\OpenRouter\OpenRouterService;
use App\Services\Puter\PuterException;
use App\Services\Puter\PuterService;

class LLMServiceFactory
{
    public static function make(?string $puterToken = null, ?string $openrouterApiKey = null): LLMServiceInterface
    {
        // Queue jobs run without an authenticated user context, so a provided Puter token
        // is the strongest signal that Puter should be used.
        if (!empty($puterToken)) {
            return PuterService::fromConfig($puterToken);
        }

        $provider = auth()->user()?->llm_provider ?? 'openrouter';

        if ($provider === 'puter') {
            if (empty($puterToken)) {
                throw PuterException::authRequired();
            }
            return PuterService::fromConfig($puterToken);
        }

        // User key (from DB) takes priority over the env/config key.
        $resolvedKey = $openrouterApiKey ?: auth()->user()?->openrouter_api_key ?: null;
        return OpenRouterService::fromConfig($resolvedKey);
    }
}
