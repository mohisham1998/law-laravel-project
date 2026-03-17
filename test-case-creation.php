<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\LegalCase;
use App\Jobs\ProcessPhase1Job;

echo "=== Testing Case Creation & RAG Integration ===\n\n";

// Get first user
$user = User::first();
if (!$user) {
    echo "❌ No user found. Run db:seed first.\n";
    exit(1);
}

// Set user model
$user->selected_model = 'anthropic/claude-3.5-sonnet';
$user->save();
echo "✅ User model set to: {$user->selected_model}\n";

// Create test case
$case = LegalCase::create([
    'title' => 'قضية اختبار - نزاع عمالي',
    'intake_text' => 'موظف تم فصله من عمله بدون سابق إنذار بعد 5 سنوات من العمل. يطالب بتعويض عن الفصل التعسفي وحقوقه المالية المتأخرة. كان يعمل في شركة خاصة براتب شهري 8000 ريال.',
    'user_id' => $user->id,
    'status' => 'phase1_pending',
    'phase' => 1,
    'category' => 'عمالي',
    'client_name' => 'أحمد محمد',
    'model_used' => $user->selected_model,
    'skill_version' => 'v2.4.0',
    'skill_hash' => md5('v2.4.0'),
]);

echo "✅ Case created: #{$case->id} - {$case->title}\n";
echo "   Status: " . $case->status->value . "\n";
echo "   Model: {$case->model_used}\n\n";

// Dispatch Phase 1 job
ProcessPhase1Job::dispatch($case);
echo "✅ Phase 1 job dispatched to queue!\n\n";

echo "📊 Check status:\n";
echo "   - Horizon: http://localhost:8000/horizon\n";
echo "   - Case: http://localhost:8000/cases/{$case->id}\n\n";

// Check law library status
$lawCount = \App\Models\LawRegistry::count();
$articleCount = \App\Models\LawArticle::count();
$embeddingCount = \App\Models\LawEmbedding::count();

echo "📚 Law Library Status:\n";
echo "   - Laws: {$lawCount}\n";
echo "   - Articles: {$articleCount}\n";
echo "   - Embeddings: {$embeddingCount}\n\n";

if ($embeddingCount === 0) {
    echo "⚠️  Note: No embeddings yet. Law processing jobs are running in background.\n";
    echo "   Wait a few minutes and check Horizon for progress.\n\n";
}

echo "=== Test Complete ===\n";
