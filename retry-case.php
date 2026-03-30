<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\LegalCase;
use App\Jobs\ProcessPhase2Job;

$caseId = '019d1ae0-034c-73f5-b957-68c4c47c8254';
$case = LegalCase::find($caseId);

if (!$case) {
    echo "Case not found: $caseId\n";
    exit(1);
}

echo "Dispatching ProcessPhase2Job for case: {$case->id}\n";

// Update case to resume from agent 3
$case->update([
    'resume_from_agent' => 3,
    'status' => 'phase2_processing',
]);

// Clear agent executions for agent 3
$case->agentExecutions()->where('agent_number', '>=', 3)->delete();

echo "Dispatching job...\n";
ProcessPhase2Job::dispatch($case);

echo "Done! The job has been dispatched to the queue.\n";
