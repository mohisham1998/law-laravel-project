<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = DB::table('law_registry')->get();
echo "law_registry rows:\n";
foreach ($rows as $r) {
    echo " ID " . $r->id . ": " . $r->name . "\n";
}
echo "\nTotal: " . $rows->count() . "\n";

echo "\nrequired_laws for case 2:\n";
$laws = DB::table('required_laws')->where('case_id', '019d321e-7f9c-72a7-981d-35447549ab8b')->get();
foreach ($laws as $l) {
    echo " ID: " . $l->id . " | " . $l->law_name . " | registry_id: " . ($l->law_registry_id ?? 'NULL') . "\n";
}
