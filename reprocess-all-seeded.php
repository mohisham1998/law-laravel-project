<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\RAG\LawProcessingService;

echo "Starting to reprocess all articles...\n";

$service = app(LawProcessingService::class);
$result = $service->reprocessAllArticles();

echo "Processed: {$result['processed_count']} / {$result['total_articles']} articles\n";
echo "Done!\n";
