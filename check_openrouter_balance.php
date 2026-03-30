<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$key = config('openrouter.api_key');

// Try /auth/key endpoint
$response = Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => 'Bearer ' . $key,
    'Content-Type' => 'application/json',
    'HTTP-Referer' => config('app.url'),
])->get('https://openrouter.ai/api/v1/auth/key');

echo "=== /auth/key ===" . PHP_EOL;
echo "Status: " . $response->status() . PHP_EOL;
echo json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

// Try /credits endpoint
$response2 = Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => 'Bearer ' . $key,
    'Content-Type' => 'application/json',
    'HTTP-Referer' => config('app.url'),
])->get('https://openrouter.ai/api/v1/credits');

echo PHP_EOL . "=== /credits ===" . PHP_EOL;
echo "Status: " . $response2->status() . PHP_EOL;
echo json_encode($response2->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
