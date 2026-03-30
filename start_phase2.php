<?php
/**
 * Start Phase 2 for a case
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
echo "Status: " . $case->status->value . "\n";

$case->update(['status' => App\Enums\CaseStatus::Phase2Pending, 'phase' => 2]);
App\Jobs\ProcessPhase2Job::dispatch($case->fresh(), '');
echo "Phase 2 dispatched!\n";
