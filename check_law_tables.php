<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// List all tables
$tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
echo "Tables:\n";
foreach ($tables as $t) echo " - " . $t->tablename . "\n";

echo "\nrequired_laws columns:\n";
$cols = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'required_laws' ORDER BY ordinal_position");
foreach ($cols as $c) echo " - " . $c->column_name . " (" . $c->data_type . ")\n";
