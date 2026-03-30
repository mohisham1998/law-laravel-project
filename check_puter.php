<?php
require "/var/www/html/vendor/autoload.php";
$app = require "/var/www/html/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cases = DB::table('cases')->whereNotNull('puter_token')->get(['id','title','puter_token']);
echo "Cases with puter_token: " . count($cases) . "\n";
foreach ($cases as $c) {
    echo "Case: " . $c->id . " | Token length: " . strlen($c->puter_token ?? '') . "\n";
}
