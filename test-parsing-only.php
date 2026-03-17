<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\RAG\LawParserService;
use App\Models\LawFile;
use App\Models\LawArticle;

echo "=== Test Article Parsing Only ===\n\n";

$parser = app(LawParserService::class);
$file = LawFile::where('filename', 'like', '%نظام الإثبات%')->first();

if (!$file) {
    echo "❌ Law file not found\n";
    exit(1);
}

echo "Parsing: {$file->filename}\n";

try {
    $articles = $parser->parseFile($file);
    
    echo "✅ Found " . count($articles) . " articles\n\n";
    
    // Save articles without embeddings
    foreach ($articles as $articleData) {
        LawArticle::create([
            'law_registry_id' => $file->law_registry_id,
            'law_file_id' => $file->id,
            'article_number' => $articleData['article_number'],
            'article_text' => $articleData['article_text'],
            'start_line' => $articleData['start_line'],
            'end_line' => $articleData['end_line'],
            'keywords' => $parser->extractKeywords($articleData['article_text']),
        ]);
    }
    
    $file->update([
        'total_articles' => count($articles),
        'is_processed' => true,
        'processed_at' => now(),
    ]);
    
    echo "✅ Articles saved to database\n";
    echo "\nSample articles:\n";
    
    foreach (array_slice($articles, 0, 3) as $art) {
        echo "  - المادة {$art['article_number']}: " . substr($art['article_text'], 0, 80) . "...\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Done ===\n";
