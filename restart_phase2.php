<?php
/**
 * Restart Phase 2 for a halted case
 */
require "/var/www/html/vendor/autoload.php";
$app = require "/var/www/html/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$caseId = $argv[1] ?? '019d31e3-5156-7078-8c0d-4f8574c1ea21';
$case = App\Models\LegalCase::find($caseId);
if (!$case) {
    echo "Case not found: $caseId\n";
    exit(1);
}

echo "Case: " . $case->id . "\n";
echo "Current status: " . $case->status->value . "\n";

// Clean up agent executions from previous run
$deleted = App\Models\AgentExecution::where('case_id', $case->id)->delete();
echo "Deleted $deleted agent executions\n";

// Clean up case outputs from phase 2 (keep agent 0)
$deletedOutputs = App\Models\CaseOutput::where('case_id', $case->id)
    ->where('agent_number', '>', 0)
    ->delete();
echo "Deleted $deletedOutputs phase 2 outputs\n";

// Reset case status
$case->update([
    'status' => App\Enums\CaseStatus::Phase2Pending,
    'phase' => 2,
    'current_agent' => null,
    'progress_percentage' => 0,
    'halted_at' => null,
    'halted_at_agent' => null,
    'halt_reason' => null,
    'last_error_message' => null,
    'pipeline_started_at' => null,
]);
echo "Case reset to phase2_pending\n";

App\Jobs\ProcessPhase2Job::dispatch($case->fresh(), '');
echo "Phase 2 dispatched!\n";
