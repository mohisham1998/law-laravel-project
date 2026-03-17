<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LawFile;
use Illuminate\Support\Facades\Storage;

$file = LawFile::first();

if (!$file) {
    echo "No law files found\n";
    exit(1);
}

echo "File: {$file->filename}\n";
echo "File path (DB): {$file->file_path}\n";

$fullPath = Storage::disk('local')->path($file->file_path);
echo "Full path: {$fullPath}\n";
echo "Exists: " . (file_exists($fullPath) ? 'YES' : 'NO') . "\n";

// Try to read it
if (file_exists($fullPath)) {
    $content = file_get_contents($fullPath);
    echo "Content length: " . strlen($content) . " bytes\n";
    echo "First 200 chars:\n";
    echo substr($content, 0, 200) . "\n";
}
