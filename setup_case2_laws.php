<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$caseId = '019d321e-7f9c-72a7-981d-35447549ab8b';

// Check existing required_laws
$existing = DB::table('required_laws')->where('case_id', $caseId)->get();
echo "Existing required laws: " . $existing->count() . "\n";
foreach ($existing as $l) {
    echo " - " . $l->law_name . " (registry: " . $l->law_registry_id . ")\n";
}

// Check available law registries
$registries = DB::table('law_registries')->get();
echo "\nAvailable law registries:\n";
foreach ($registries as $r) {
    echo " ID " . $r->id . ": " . $r->name . "\n";
}

// For a labor case (فصل تعسفي), the relevant laws are:
// - نظام العمل (Labor Law) - likely in registry
// - نظام الإجراءات الجزائية (if criminal aspect)
// - نظام الإثبات (Evidence)
// - نظام المرافعات الشرعية (Civil Procedure)

// Add labor-relevant laws
$lawsToAdd = [];

// Check which registries exist and add all 4
$registryIds = $registries->pluck('id')->toArray();
$alreadyAdded = $existing->pluck('law_registry_id')->toArray();

$lawMappings = [
    1 => ['law_name' => 'نظام الإثبات', 'reason' => 'يُطبق في إثبات حقوق العمال وسائل الإثبات المعتمدة شرعاً ونظاماً', 'subject_area' => 'labor'],
    2 => ['law_name' => 'اللائحة التنفيذية لنظام الإجراءات الجزائية', 'reason' => 'تحكم الإجراءات في حال تضمنت الدعوى جانباً جزائياً كالاحتيال أو الانتهاك', 'subject_area' => 'labor'],
    3 => ['law_name' => 'اللوائح التنفيذية لنظام المرافعات الشرعية', 'reason' => 'تنظم إجراءات التقاضي أمام المحاكم العمالية ومحاكم المرافعات', 'subject_area' => 'labor'],
    4 => ['law_name' => 'نظام المرافعات الشرعية', 'reason' => 'يحكم قواعد التقاضي والدفوع الشكلية والموضوعية في دعاوى العمل', 'subject_area' => 'labor'],
];

foreach ($lawMappings as $registryId => $lawData) {
    if (in_array($registryId, $alreadyAdded)) {
        echo "Already has law registry $registryId\n";
        continue;
    }
    if (!in_array($registryId, $registryIds)) {
        echo "Registry $registryId not found\n";
        continue;
    }
    DB::table('required_laws')->insert([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'case_id' => $caseId,
        'law_registry_id' => $registryId,
        'law_name' => $lawData['law_name'],
        'reason' => $lawData['reason'],
        'subject_area' => $lawData['subject_area'],
        'is_uploaded' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "Added: " . $lawData['law_name'] . " (registry $registryId)\n";
}

$total = DB::table('required_laws')->where('case_id', $caseId)->count();
echo "\nTotal required laws for Case 2: $total\n";
