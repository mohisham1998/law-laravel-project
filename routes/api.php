<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Saudi Legal Case Orchestration System
|--------------------------------------------------------------------------
|
| Base path: /api
| v1 routes: /api/v1/*
|
*/

// Health check - GET /api/health
Route::get('/health', fn () => response()->json([
    'status' => 'healthy',
    'services' => [
        'database' => 'up',
        'redis' => 'up',
    ],
    'timestamp' => now()->toIso8601String(),
]))->name('health');

// API v1 routes (to be implemented)
Route::prefix('v1')->group(function () {
    require base_path('routes/api/v1.php');
});
