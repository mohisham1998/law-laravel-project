<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\RAG\LawProcessingService;
use App\Models\LawFile;

echo "=== Manual Law Processing ===\n\n";

$processingService = app(LawProcessingService::class);

$files = LawFile::where('is_processed', false)->get();

echo "Found " . $files->count() . " unprocessed files\n\n";

foreach ($files as $file) {
    echo "Processing: {$file->filename}...\n";
    
    try {
        $result = $processingService->processLawFile($file);
        
        if ($result['success']) {
            echo "  ✅ Success: {$result['articles_count']} articles extracted\n";
        } else {
            echo "  ❌ Failed: {$result['message']}\n";
        }
    } catch (\Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "=== Processing Complete ===\n";
