<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Recent cases:\n";
$cases = \App\Models\LegalCase::latest()->take(5)->get(['id', 'title', 'status', 'phase', 'current_agent', 'created_at']);
foreach($cases as $c) {
    echo $c->id . " | " . $c->title . " | " . $c->status->value . " | phase:" . $c->phase . " | agent:" . ($c->current_agent ?? 'null') . " | " . $c->created_at->format('H:i:s') . "\n";
}

echo "\nAgent executions for latest case:\n";
$latest = \App\Models\LegalCase::latest()->first();
if ($latest) {
    $execs = $latest->agentExecutions()->orderBy('agent_number')->get(['agent_number', 'status', 'started_at', 'completed_at']);
    foreach($execs as $e) {
        echo "Agent " . $e->agent_number . ": " . $e->status . " | started: " . ($e->started_at ? $e->started_at->format('H:i:s') : 'null') . "\n";
    }
}
