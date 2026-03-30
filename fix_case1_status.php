<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$caseId = '019d3213-8dd2-718b-830e-a84e75d58b27';
$case = App\Models\LegalCase::find($caseId);
$oldStatus = $case->status->value ?? $case->status;
echo "Old status: $oldStatus\n";

// Update to phase2_completed so Phase 3 can be triggered
$case->status = App\Enums\CaseStatus::Phase2Completed;
$case->save();

$newStatus = $case->fresh()->status->value ?? $case->fresh()->status;
echo "New status: $newStatus\n";
echo "Case is ready for Phase 3\n";
