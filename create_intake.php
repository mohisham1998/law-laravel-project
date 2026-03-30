<?php
/**
 * Create intake.txt for a case that was created programmatically (without file upload)
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

// Create the case directory and intake.txt
$caseDir = storage_path("app/cases/{$case->id}");
if (!is_dir($caseDir)) {
    mkdir($caseDir, 0755, true);
    echo "Created directory: $caseDir\n";
}

$intakePath = $caseDir . '/intake.txt';
file_put_contents($intakePath, $case->intake_text);
echo "Created intake.txt (" . strlen($case->intake_text) . " bytes)\n";

// Also create outputs directory
$outputsDir = $caseDir . '/outputs';
if (!is_dir($outputsDir)) {
    mkdir($outputsDir, 0755, true);
    echo "Created outputs directory\n";
}
