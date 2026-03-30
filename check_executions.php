<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$caseId = $argv[1] ?? '019d321e-7f9c-72a7-981d-35447549ab8b';
$execs = DB::table('agent_executions')
    ->where('case_id', $caseId)
    ->orderBy('agent_number')
    ->get(['agent_number', 'agent_name', 'status', 'started_at', 'completed_at']);

foreach ($execs as $e) {
    $status = $e->status;
    echo "Agent " . $e->agent_number . " (" . $e->agent_name . "): $status\n";
}
echo "\nTotal: " . $execs->count() . "\n";

// Also check case.phase
$case = DB::table('cases')->where('id', $caseId)->first(['status', 'phase']);
echo "\nCase status: " . $case->status . "\n";
echo "Case phase: " . $case->phase . "\n";
