<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Finding cases awaiting laws...\n";
$cases = \App\Models\LegalCase::where('status', 'awaiting_laws')->get();

echo "Found " . $cases->count() . " cases\n\n";

foreach($cases as $case) {
    echo "Case: " . $case->title . " (ID: " . $case->id . ")\n";
    
    // Update status to phase2_pending and dispatch Phase 2 job
    $case->update([
        'status' => \App\Enums\CaseStatus::Phase2Pending,
        'phase' => 2,
    ]);
    
    \App\Jobs\ProcessPhase2Job::dispatch($case);
    
    echo "✅ Phase 2 job dispatched!\n\n";
}

echo "Done! Check the portal to see agents processing.\n";
