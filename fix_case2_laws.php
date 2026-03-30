<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$caseId = '019d321e-7f9c-72a7-981d-35447549ab8b';

// Update existing NULL registry entry to point to نظام الإثبات (ID 3)
DB::table('required_laws')
    ->where('case_id', $caseId)
    ->whereNull('law_registry_id')
    ->update([
        'law_name' => 'نظام الإثبات',
        'law_registry_id' => 3,
        'reason' => 'يُطبق في إثبات حقوق الموظف من عقود ووثائق وكشوف رواتب أمام المحكمة العمالية',
        'subject_area' => 'labor',
        'updated_at' => now(),
    ]);
echo "Updated existing entry to نظام الإثبات (ID 3)\n";

// Add remaining 3 laws
$toAdd = [
    ['id' => 1, 'name' => 'اللائحة التنفيذية لنظام الإجراءات الجزائية', 'reason' => 'تُطبق في الجانب التأديبي لإجراءات الفصل وإجراءات التحقيق مع الموظف', 'area' => 'labor'],
    ['id' => 2, 'name' => 'اللوائح التنفيذية لنظام المرافعات الشرعية', 'reason' => 'تنظم إجراءات التقاضي أمام المحاكم العمالية ورفع الدعاوى والدفوع', 'area' => 'labor'],
    ['id' => 4, 'name' => 'نظام المرافعات الشرعية', 'reason' => 'يحكم قواعد قبول الدعوى وطرق الطعن والإجراءات الشكلية في النزاعات العمالية', 'area' => 'labor'],
];

foreach ($toAdd as $law) {
    $exists = DB::table('required_laws')
        ->where('case_id', $caseId)
        ->where('law_registry_id', $law['id'])
        ->exists();
    if ($exists) { echo "Already exists: " . $law['name'] . "\n"; continue; }

    DB::table('required_laws')->insert([
        'case_id' => $caseId,
        'law_registry_id' => $law['id'],
        'law_name' => $law['name'],
        'reason' => $law['reason'],
        'subject_area' => $law['area'],
        'is_uploaded' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "Added: " . $law['name'] . "\n";
}

$total = DB::table('required_laws')->where('case_id', $caseId)->count();
echo "\nTotal required laws for Case 2: $total\n";

// Show final list
$laws = DB::table('required_laws')->where('case_id', $caseId)->get();
foreach ($laws as $l) {
    echo " - " . $l->law_name . " (registry_id: " . ($l->law_registry_id ?? 'NULL') . ")\n";
}
