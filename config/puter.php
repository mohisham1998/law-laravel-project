<?php

return [
    'api_base_url'    => env('PUTER_API_BASE_URL', 'https://api.puter.com'),
    'models_endpoint' => '/puterai/chat/models/details',
    'chat_endpoint'   => '/puterai/openai/v1/chat/completions',
    'cache_ttl'       => 300, // seconds
];
