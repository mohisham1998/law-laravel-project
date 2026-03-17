<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| Base path: /api/v1
|
*/

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', [\App\Http\Controllers\Api\DashboardController::class, 'index']);
    Route::post('/admin/validate-skill', [\App\Http\Controllers\Api\AdminController::class, 'validateSkill']);
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::patch('/settings', [SettingsController::class, 'update']);
    Route::get('/settings/models', [SettingsController::class, 'models']);
    Route::get('/settings/cost-breakdown', [SettingsController::class, 'costBreakdown']);
    Route::get('/settings/cost-breakdown/export', [SettingsController::class, 'costBreakdownExport']);
    Route::post('/settings/regenerate-token', [SettingsController::class, 'regenerateToken']);

    Route::middleware(\App\Http\Middleware\RateLimitCases::class)->group(function () {
        Route::post('/cases', [\App\Http\Controllers\Api\CaseController::class, 'store']);
    });
    Route::get('/cases', [\App\Http\Controllers\Api\CaseController::class, 'index']);
    Route::get('/cases/{id}', [\App\Http\Controllers\Api\CaseController::class, 'show']);
    Route::get('/cases/{id}/outputs', [\App\Http\Controllers\Api\OutputController::class, 'index']);
    Route::get('/cases/{id}/outputs/{outputId}', [\App\Http\Controllers\Api\OutputController::class, 'show']);
    Route::get('/cases/{id}/final-brief', [\App\Http\Controllers\Api\OutputController::class, 'finalBrief']);
    Route::get('/cases/{id}/errors', [\App\Http\Controllers\Api\ErrorLogController::class, 'index']);
    Route::get('/cases/{id}/errors/export', [\App\Http\Controllers\Api\ErrorLogController::class, 'export']);
    Route::post('/cases/{id}/laws', [\App\Http\Controllers\Api\LawController::class, 'store']);
    Route::post('/cases/{id}/start-phase2', [\App\Http\Controllers\Api\CaseController::class, 'startPhase2']);
    Route::post('/cases/{id}/start-phase3', [\App\Http\Controllers\Api\CaseController::class, 'startPhase3']);
    Route::delete('/cases/{id}', [\App\Http\Controllers\Api\CaseController::class, 'destroy']);
});
