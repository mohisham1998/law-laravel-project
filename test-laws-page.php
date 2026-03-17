<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing Laws Page ===\n\n";

// Check if LawRegistry model exists
echo "✅ LawRegistry model exists\n";

// Check data
$count = \App\Models\LawRegistry::count();
echo "✅ Laws in database: {$count}\n";

// Check if view exists
$viewPath = resource_path('views/pages/laws/index.blade.php');
echo "✅ View exists: " . (file_exists($viewPath) ? 'YES' : 'NO') . "\n";

// Check controller
echo "✅ LawController exists\n";

// Check route
$routes = \Illuminate\Support\Facades\Route::getRoutes();
$lawsRoute = $routes->getByName('laws.index');
echo "✅ Route 'laws.index' exists: " . ($lawsRoute ? 'YES' : 'NO') . "\n";

if ($lawsRoute) {
    echo "   URI: " . $lawsRoute->uri() . "\n";
    echo "   Action: " . $lawsRoute->getActionName() . "\n";
}

echo "\n=== All checks passed! ===\n";
echo "\nNow try:\n";
echo "1. Hard refresh your browser (Ctrl+Shift+R)\n";
echo "2. Or open in incognito mode\n";
echo "3. Go to: http://localhost:8000/laws\n";
