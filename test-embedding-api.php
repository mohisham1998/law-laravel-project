<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\RAG\EmbeddingService;

echo "=== Testing OpenRouter Embeddings API ===\n\n";

$service = app(EmbeddingService::class);

$testText = "المادة الأولى: تسري أحكام هذا النظام على المسائل المدنية والتجارية";

echo "Test text: {$testText}\n\n";

try {
    $start = microtime(true);
    $result = $service->generateEmbedding($testText);
    $duration = round((microtime(true) - $start) * 1000);
    
    echo "✅ Success!\n";
    echo "   Model: {$result['model']}\n";
    echo "   Embedding dimensions: " . count($result['embedding']) . "\n";
    echo "   Duration: {$duration}ms\n";
    echo "   Sample values: [" . implode(', ', array_slice($result['embedding'], 0, 5)) . ", ...]\n";
    
    if (isset($result['usage'])) {
        echo "   Usage: " . json_encode($result['usage']) . "\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
