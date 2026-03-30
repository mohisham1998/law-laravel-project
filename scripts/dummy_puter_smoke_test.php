<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\CaseStatus;
use App\Jobs\ProcessPhase1Job;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function extractTokenFromFailedJobs(): string
{
    $payload = DB::table('failed_jobs')->orderByDesc('id')->value('payload');
    if (!is_string($payload)) {
        return '';
    }

    $decoded = json_decode($payload, true);
    $command = $decoded['data']['command'] ?? '';
    if (!is_string($command)) {
        return '';
    }

    if (preg_match('/s:10:"puterToken";s:\\d+:"([^"]+)";/', $command, $m) === 1) {
        return $m[1];
    }

    return '';
}

echo "=== Dummy Puter Smoke Test (Phase 1) ===\n\n";

$user = User::query()->first();
if (!$user) {
    echo "FAIL: no user found\n";
    exit(1);
}

$token = (string) ($user->puter_token ?? '');
if ($token === '') {
    $token = extractTokenFromFailedJobs();
}

if ($token === '') {
    echo "FAIL: no Puter token found\n";
    exit(1);
}

$model = 'gpt-5-nano-2025-08-07';
$now = now()->format('Y-m-d H:i:s');

$case = LegalCase::create([
    'title' => 'Dummy Puter smoke case ' . $now,
    'intake_text' => 'هذه قضية تجريبية لاختبار مسار Puter في المرحلة الأولى فقط. لا تستخدم للاعتماد القانوني.',
    'user_id' => $user->id,
    'status' => CaseStatus::Phase1Pending,
    'phase' => 1,
    'category' => 'اختبار',
    'client_name' => 'Dummy Client',
    'model_used' => $model,
    'skill_version' => (string) config('legal.skill_version', 'unknown'),
    'skill_hash' => md5((string) config('legal.skill_version', 'unknown')),
]);

ProcessPhase1Job::dispatch($case, $token);

echo 'Created case: ' . $case->id . "\n";
echo 'Model: ' . $model . "\n";
echo 'Token length: ' . strlen($token) . "\n";
echo "Waiting for Phase 1 result...\n\n";

$maxSeconds = 180;
$start = time();
$lastStatus = '';

while ((time() - $start) < $maxSeconds) {
    $fresh = LegalCase::query()->find($case->id);
    if (!$fresh) {
        echo "FAIL: case not found after creation\n";
        exit(1);
    }

    $status = $fresh->status->value;
    if ($status !== $lastStatus) {
        echo '[' . now()->format('H:i:s') . '] status: ' . $status . "\n";
        $lastStatus = $status;
    }

    if ($status === CaseStatus::AwaitingLaws->value) {
        $requiredCount = $fresh->requiredLaws()->count();
        echo "\nPASS: Phase 1 completed and case moved to awaiting_laws\n";
        echo 'Required laws count: ' . $requiredCount . "\n";
        exit(0);
    }

    if ($status === CaseStatus::Failed->value) {
        echo "\nFAIL: case moved to failed\n";
        echo 'Error: ' . (string) ($fresh->last_error_message ?? 'n/a') . "\n";
        exit(1);
    }

    sleep(3);
}

$fresh = LegalCase::query()->find($case->id);
echo "\nTIMEOUT: no terminal Phase 1 state within {$maxSeconds}s\n";
echo 'Current status: ' . ($fresh?->status?->value ?? 'unknown') . "\n";
exit(2);
