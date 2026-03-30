<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$caseId = '019d321e-7f9c-72a7-981d-35447549ab8b';
$case = App\Models\LegalCase::find($caseId);
$oldStatus = $case->status->value ?? $case->status;
echo "Old status: $oldStatus\n";

$case->status = App\Enums\CaseStatus::Phase2Completed;
$case->save();

echo "New status: " . ($case->fresh()->status->value ?? $case->fresh()->status) . "\n";
echo "Ready for Phase 3\n";
