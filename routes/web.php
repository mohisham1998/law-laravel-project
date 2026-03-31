<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\LawController;
use App\Http\Controllers\LawLibraryController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// Redirect root to dashboard or login
Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Cases
    Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
    Route::get('/cases/create', [CaseController::class, 'create'])->name('cases.create');
    Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
    Route::post('/cases/bulk/resume', [CaseController::class, 'bulkResume'])->name('cases.bulk.resume');
    Route::post('/cases/bulk/pause', [CaseController::class, 'bulkPause'])->name('cases.bulk.pause');
    Route::post('/cases/bulk/retry', [CaseController::class, 'bulkRetry'])->name('cases.bulk.retry');
Route::delete('/cases/bulk/delete', [CaseController::class, 'bulkDelete'])->name('cases.bulk.delete');
    Route::get('/cases/{case}', [CaseController::class, 'show'])->name('cases.show');
    Route::post('/cases/{case}/start-phase2', [CaseController::class, 'startPhase2'])->name('cases.start-phase2');
    Route::get('/cases/{case}/timeline', [CaseController::class, 'timeline'])->name('cases.timeline');
    Route::get('/cases/{case}/pdf', [CaseController::class, 'pdf'])->name('cases.pdf');
    Route::get('/cases/{case}/stream', [\App\Http\Controllers\CaseStreamController::class, 'stream'])->name('cases.stream');
    Route::post('/cases/{case}/retry-agent', [CaseController::class, 'retryAgent'])->name('cases.retry-agent');
    Route::post('/cases/{case}/resume', [CaseController::class, 'resumeCase'])->name('cases.resume');
    Route::post('/cases/{case}/pause', [CaseController::class, 'pauseCase'])->name('cases.pause');
    Route::post('/cases/{case}/rerun-from', [CaseController::class, 'rerunFrom'])->name('cases.rerun-from');
    Route::patch('/cases/{case}/model-config', [CaseController::class, 'saveModelConfig'])->name('cases.model-config');
    Route::post('/cases/{case}/rerun-agent-with-model', [CaseController::class, 'rerunAgentWithModel'])->name('cases.rerun-agent-with-model');
    Route::post('/cases/{case}/start-phase3', [CaseController::class, 'startPhase3'])->name('cases.start-phase3');
    Route::post('/cases/{case}/abort', [CaseController::class, 'abort'])->name('cases.abort');
    Route::delete('/cases/{case}', [CaseController::class, 'destroy'])->name('cases.destroy');
    Route::post('/cases/{case}/update-missing-info', [CaseController::class, 'updateMissingInfo'])->name('cases.update-missing-info');
    Route::post('/cases/{case}/request-changes', [CaseController::class, 'requestChanges'])->name('cases.request-changes');
    Route::post('/cases/{case}/audit', [CaseController::class, 'audit'])->name('cases.audit');
    Route::post('/cases/{case}/audit/upload', [CaseController::class, 'uploadAuditFile'])->name('cases.audit-upload');
    
    // Documents
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/search', [DocumentController::class, 'search'])->name('documents.search');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    
    // Laws (Law Library with upload)
    Route::get('/laws', [LawController::class, 'index'])->name('laws.index');
    Route::post('/laws', [LawController::class, 'store'])->name('laws.store');
    Route::get('/laws/{law}', [LawController::class, 'show'])->name('laws.show');
    Route::put('/laws/{law}', [LawController::class, 'update'])->name('laws.update');
    Route::delete('/laws/{law}', [LawController::class, 'destroy'])->name('laws.destroy');
    Route::delete('/laws-bulk-delete', [LawController::class, 'bulkDelete'])->name('laws.bulk-delete');
    Route::post('/laws/{law}/upload-files', [LawController::class, 'uploadFiles'])->name('laws.upload-files');
    Route::post('/laws/{law}/files/{file}/replace', [LawController::class, 'replaceFile'])->name('laws.replace-file');
    Route::delete('/laws/{law}/files/{file}', [LawController::class, 'deleteFile'])->name('laws.delete-file');
    Route::get('/laws/{law}/files/{file}/download', [LawController::class, 'downloadFile'])->name('laws.download-file');
    
    // Law Library (RAG Knowledge Base)
    Route::get('/law-library', [LawLibraryController::class, 'index'])->name('law-library.index');
    Route::get('/law-library/create', [LawLibraryController::class, 'create'])->name('law-library.create');
    Route::post('/law-library', [LawLibraryController::class, 'store'])->name('law-library.store');
    Route::get('/law-library/{lawRegistry}', [LawLibraryController::class, 'show'])->name('law-library.show');
    Route::get('/law-library/{lawRegistry}/edit', [LawLibraryController::class, 'edit'])->name('law-library.edit');
    Route::put('/law-library/{lawRegistry}', [LawLibraryController::class, 'update'])->name('law-library.update');
    Route::delete('/law-library/{lawRegistry}', [LawLibraryController::class, 'destroy'])->name('law-library.destroy');
    Route::post('/law-library/{lawRegistry}/upload', [LawLibraryController::class, 'uploadFiles'])->name('law-library.upload-files');
    Route::post('/law-library/{lawRegistry}/reprocess', [LawLibraryController::class, 'reprocess'])->name('law-library.reprocess');
    Route::post('/law-library/{lawRegistry}/files/{lawFile}/reprocess', [LawLibraryController::class, 'reprocessFile'])->name('law-library.reprocess-file');
    
    // AI Analysis
    Route::get('/ai-analysis/{case?}', [CaseController::class, 'showAnalysis'])->name('ai-analysis');

    // Case progress JSON (for AI analysis page live refresh)
    Route::get('/cases/{case}/progress-json', [CaseController::class, 'progressJson'])->name('cases.progress-json');

    // Global search
    Route::get('/search', [\App\Http\Controllers\SearchController::class, 'search'])->name('search');

    // Notifications SSE stream (all active cases for the current user)
    Route::get('/notifications/stream', [\App\Http\Controllers\NotificationStreamController::class, 'stream'])->name('notifications.stream');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    
    // API endpoints for settings
    Route::get('/api/models', [SettingsController::class, 'getModels'])->name('api.models');
    Route::post('/api/estimate-cost', [SettingsController::class, 'estimateCost'])->name('api.estimate-cost');
    Route::post('/api/settings/model-preview', [SettingsController::class, 'modelPreview'])->name('api.model-preview');
    Route::match(['get', 'post'], '/settings/check-openrouter', [SettingsController::class, 'checkOpenRouter'])->name('settings.check-openrouter');
    Route::get('/settings/openrouter-status', [SettingsController::class, 'openRouterStatus'])->name('settings.openrouter-status');
    Route::get('/api/v1/settings/puter-models', [\App\Http\Controllers\PuterController::class, 'getPuterModels'])->name('api.puter-models');

    // Agent system message editor
    Route::get('/api/agents/{agentNumber}/system-message', [\App\Http\Controllers\AgentSystemMessageController::class, 'show'])->name('agents.system-message.show');
    Route::patch('/api/agents/{agentNumber}/system-message', [\App\Http\Controllers\AgentSystemMessageController::class, 'update'])->name('agents.system-message.update');
    Route::delete('/api/agents/{agentNumber}/system-message/override', [\App\Http\Controllers\AgentSystemMessageController::class, 'reset'])->name('agents.system-message.reset');

    // Dev only: clear view/cache so Blade edits show without restarting Docker (only when APP_ENV=local)
    Route::get('/dev/clear-views', function () {
        if (app()->environment('local')) {
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            \Illuminate\Support\Facades\Artisan::call('route:clear');
            return response()->json(['ok' => true, 'message' => 'View, cache, config, and route caches cleared. Refresh your page.']);
        }
        abort(404);
    })->name('dev.clear-views');
});
