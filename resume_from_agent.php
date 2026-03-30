<?php
/**
 * Resume Phase 2 from a specific agent
 */
require "/var/www/html/vendor/autoload.php";
$app = require "/var/www/html/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$caseId = $argv[1] ?? '019d31e3-5156-7078-8c0d-4f8574c1ea21';
$fromAgent = (int) ($argv[2] ?? 8);

$case = App\Models\LegalCase::find($caseId);
if (!$case) {
    echo "Case not found: $caseId\n";
    exit(1);
}

echo "Case: " . $case->id . "\n";
echo "Current status: " . $case->status->value . "\n";
echo "Current agent: " . $case->current_agent . "\n";

// Delete agent execution records from $fromAgent onwards
$deleted = App\Models\AgentExecution::where('case_id', $case->id)
    ->where('agent_number', '>=', $fromAgent)
    ->delete();
echo "Deleted $deleted agent executions (from agent $fromAgent)\n";

// Reset case status to resume
$case->update([
    'status' => App\Enums\CaseStatus::Phase2Pending,
    'phase' => 2,
    'current_agent' => null,
    'resume_from_agent' => $fromAgent,
    'halted_at' => null,
    'halted_at_agent' => null,
    'halt_reason' => null,
    'last_error_message' => null,
]);
echo "Case reset to phase2_pending, resume from agent $fromAgent\n";

App\Jobs\ProcessPhase2Job::dispatch($case->fresh(), '');
echo "Phase 2 dispatched from agent $fromAgent!\n";
