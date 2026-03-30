<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$caseId = $argv[1] ?? '019d3213-8dd2-718b-830e-a84e75d58b27';
$case = App\Models\LegalCase::find($caseId);
if (!$case) { echo "Case not found\n"; exit(1); }
echo 'Status: ' . ($case->status instanceof \BackedEnum ? $case->status->value : $case->status) . PHP_EOL;
$execs = App\Models\AgentExecution::where('case_id', $caseId)->orderBy('agent_number')->get();
foreach($execs as $e) {
    $status = $e->status instanceof \BackedEnum ? $e->status->value : $e->status;
    echo 'Agent ' . $e->agent_number . ' (' . $e->agent_name . '): ' . $status .
        ($e->error_message ? ' | ERR: ' . substr($e->error_message, 0, 80) : '') . PHP_EOL;
}
echo PHP_EOL . 'Total agents: ' . $execs->count() . PHP_EOL;
