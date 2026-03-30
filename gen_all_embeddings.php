<?php
/**
 * Generate all law embeddings using PDO LOB parameter (fixes PostgreSQL bytea encoding)
 */
require "/var/www/html/vendor/autoload.php";
$app = require "/var/www/html/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Delete old invalid embeddings
$deleted = App\Models\LawEmbedding::query()->delete();
echo "Deleted $deleted old embeddings\n";

$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
$embeddingService = app(App\Services\RAG\EmbeddingService::class);

// Get all law articles
$articles = App\Models\LawArticle::with("lawRegistry")->get();
echo "Total articles: " . count($articles) . "\n";

$created = 0;
$failed = 0;
$sql = "INSERT INTO law_embeddings (law_article_id, embedding_model, embedding_dimensions, embedding_vector, norm, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";

foreach ($articles as $article) {
    $articleId = (int) $article->getKey();
    $text = "المادة " . $article->article_number . " من " . $article->lawRegistry->name . ": " . mb_substr($article->article_text, 0, 1000);

    $embeddingData = $embeddingService->generateEmbedding($text);
    $vector = $embeddingData["embedding"];

    if (empty($vector)) {
        $failed++;
        continue;
    }

    $packed = pack("f*", ...$vector);
    $dims = count($vector);
    $sumSq = array_sum(array_map(fn($v) => $v * $v, $vector));
    $norm = (string) sqrt($sumSq);
    $model = (string) $embeddingData["model"];

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $articleId, PDO::PARAM_INT);
    $stmt->bindValue(2, $model, PDO::PARAM_STR);
    $stmt->bindValue(3, $dims, PDO::PARAM_INT);
    $stmt->bindValue(4, $packed, PDO::PARAM_LOB);
    $stmt->bindValue(5, $norm, PDO::PARAM_STR);

    try {
        $stmt->execute();
        $created++;
        if ($created % 100 === 0) {
            echo "Progress: $created/" . count($articles) . "\n";
        }
    } catch (Throwable $e) {
        $failed++;
        if ($failed <= 3) echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\nDone! Created: $created, Failed: $failed\n";
echo "Total in DB: " . App\Models\LawEmbedding::count() . "\n";

// Verify one retrieval works
$e = App\Models\LawEmbedding::latest("id")->first();
if ($e) {
    $v = $e->getVectorArray();
    echo "Sample vector dims: " . count($v) . " (valid: " . (count($v) > 0 ? "YES" : "NO") . ")\n";
}
