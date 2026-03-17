<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LegalCase;
use App\Models\LawRegistry;
use App\Models\LawArticle;
use App\Models\LawEmbedding;

echo "=== Case & RAG Status Check ===\n\n";

// Get latest case
$case = LegalCase::latest()->first();

if (!$case) {
    echo "❌ No cases found.\n";
    exit(1);
}

echo "📋 Case: #{$case->id}\n";
echo "   Title: {$case->title}\n";
echo "   Status: " . $case->status->value . "\n";
echo "   Phase: {$case->phase}\n";
echo "   Model: {$case->model_used}\n\n";

// Check outputs
$outputs = $case->outputs()->orderBy('agent_number')->get();
echo "📄 Outputs: " . $outputs->count() . "\n";
foreach ($outputs as $output) {
    echo "   - Agent {$output->agent_number}: {$output->filename}\n";
    if ($output->content) {
        echo "     Content: " . substr($output->content, 0, 100) . "...\n";
    }
}
echo "\n";

// Check required laws
$requiredLaws = $case->requiredLaws;
echo "⚖️  Required Laws: " . $requiredLaws->count() . "\n";
foreach ($requiredLaws as $law) {
    echo "   - {$law->law_name}: {$law->reason}\n";
}
echo "\n";

// Check agent executions
$executions = $case->agentExecutions()->orderBy('agent_number')->get();
echo "🤖 Agent Executions: " . $executions->count() . "\n";
foreach ($executions as $exec) {
    echo "   - Agent {$exec->agent_number}: {$exec->status} ({$exec->execution_time_ms}ms)\n";
    if ($exec->error_message) {
        echo "     Error: {$exec->error_message}\n";
    }
}
echo "\n";

// Check law library
$laws = LawRegistry::withCount(['files', 'articles'])->get();
echo "📚 Law Library:\n";
foreach ($laws as $law) {
    $processed = $law->files()->where('is_processed', true)->count();
    $embedded = $law->articles()->whereHas('embedding')->count();
    echo "   - {$law->name}\n";
    echo "     Files: {$law->files_count} (processed: {$processed})\n";
    echo "     Articles: {$law->articles_count} (embedded: {$embedded})\n";
}
echo "\n";

// Check total embeddings
$totalEmbeddings = LawEmbedding::count();
echo "🔍 Total Embeddings: {$totalEmbeddings}\n";

if ($totalEmbeddings > 0) {
    $models = LawEmbedding::select('embedding_model', DB::raw('count(*) as count'))
        ->groupBy('embedding_model')
        ->get();
    echo "   By model:\n";
    foreach ($models as $model) {
        echo "   - {$model->embedding_model}: {$model->count}\n";
    }
}

echo "\n=== Status Check Complete ===\n";
