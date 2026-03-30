<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$payload = DB::table('failed_jobs')->orderByDesc('id')->value('payload');
$token = '';

if (is_string($payload)) {
    $decoded = json_decode($payload, true);
    $command = $decoded['data']['command'] ?? '';

    if (is_string($command) && preg_match('/s:10:"puterToken";s:\\d+:"([^"]+)";/', $command, $m) === 1) {
        $token = $m[1];
    }
}

if ($token === '') {
    echo "NO_TOKEN\n";
    exit(1);
}

$svc = new App\Services\Puter\PuterService($token);
$result = $svc->complete(
    'gpt-5-nano-2025-08-07',
    [['role' => 'user', 'content' => 'اكتب كلمة نجاح فقط']],
    0.2,
    64
);

echo json_encode([
    'token_len' => strlen($token),
    'content' => $result['content'] ?? null,
    'prompt_tokens' => $result['prompt_tokens'] ?? null,
    'completion_tokens' => $result['completion_tokens'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
