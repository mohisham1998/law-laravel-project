<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Job Queue Status ===\n\n";

$jobsCount = DB::table('jobs')->count();
$failedCount = DB::table('failed_jobs')->count();

echo "Jobs in queue: {$jobsCount}\n";
echo "Failed jobs: {$failedCount}\n\n";

if ($failedCount > 0) {
    $failed = DB::table('failed_jobs')->latest()->first();
    echo "Last failed job:\n";
    echo "  Queue: {$failed->queue}\n";
    echo "  Failed at: {$failed->failed_at}\n";
    echo "  Exception: " . substr($failed->exception, 0, 500) . "...\n";
}

// Check law files
$files = \App\Models\LawFile::all();
echo "\nLaw Files:\n";
foreach ($files as $file) {
    echo "  - {$file->filename}: " . ($file->is_processed ? 'processed' : 'pending') . "\n";
}

echo "\n=== Done ===\n";
