<?php
require "/var/www/html/vendor/autoload.php";
$app = require "/var/www/html/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Total embeddings in DB: " . App\Models\LawEmbedding::count() . "\n";

// First test: fresh embedding retrieval
$e = App\Models\LawEmbedding::latest("id")->first();
if ($e) {
    $v = $e->getVectorArray();
    echo "Sample embedding dims: " . count($v) . " (first: " . round($v[0] ?? 0, 4) . ")\n";
}

// Test VectorSearch with Agent 0's threshold
$vsService = app(App\Services\RAG\VectorSearchService::class);
$query = "حقوق عمالية فصل تعسفي مكافأة نهاية خدمة نظام العمل";
echo "\nSearch query: " . $query . "\n";

// Try at different thresholds
foreach ([0.55, 0.40, 0.30, 0.20] as $threshold) {
    $results = $vsService->search($query, 5, $threshold);
    echo "  Threshold $threshold: " . count($results) . " results\n";
    foreach ($results as $r) {
        echo "    - " . ($r["article"]->lawRegistry->name ?? "?") . " م" . ($r["article"]->article_number ?? "?") . " (score: " . round($r["similarity"], 3) . ")\n";
    }
}
