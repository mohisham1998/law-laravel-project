<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cases = App\Models\LegalCase::orderBy('created_at', 'desc')->get();
foreach($cases as $c) {
    $status = $c->status instanceof \BackedEnum ? $c->status->value : $c->status;
    echo $c->id . ' | ' . $status . ' | ' . substr($c->title ?? 'No title', 0, 60) . PHP_EOL;
}
echo PHP_EOL . 'Total: ' . $cases->count() . PHP_EOL;
