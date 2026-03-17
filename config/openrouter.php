<?php

return [
    'api_key' => env('OPENROUTER_API_KEY', ''),
    'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
    'default_model' => env('OPENROUTER_DEFAULT_MODEL', 'anthropic/claude-3.5-sonnet'),
    'timeout' => env('OPENROUTER_TIMEOUT', 300),
    'retry_attempts' => env('OPENROUTER_RETRY_ATTEMPTS', 3),
    'retry_delay_ms' => env('OPENROUTER_RETRY_DELAY_MS', 2000),
];
