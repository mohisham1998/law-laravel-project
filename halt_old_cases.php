<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$toHalt = [
    '019d31e3-5156-7078-8c0d-4f8574c1ea21', // old احتيال تجاري phase2_processing
    '019d31e2-ff87-7371-9e48-61400bca0d9c', // old احتيال تجاري phase1_pending
];

foreach ($toHalt as $id) {
    $case = App\Models\LegalCase::find($id);
    if (!$case) { echo "Not found: $id\n"; continue; }
    $oldStatus = $case->status instanceof \BackedEnum ? $case->status->value : $case->status;
    $case->status = App\Enums\CaseStatus::Halted;
    $case->save();
    echo "Halted: $id (was $oldStatus) | " . substr($case->title ?? '', 0, 50) . "\n";
}

// Verify processing count
$count = App\Models\LegalCase::whereIn('status', [
    App\Enums\CaseStatus::Phase1Pending->value,
    App\Enums\CaseStatus::Phase1Processing->value,
    App\Enums\CaseStatus::Phase2Processing->value,
])->count();
echo "\nProcessing count now: $count\n";
