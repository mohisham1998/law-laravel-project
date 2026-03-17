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
    Route::get('/cases/{case}', [CaseController::class, 'show'])->name('cases.show');
    Route::post('/cases/{case}/start-phase2', [CaseController::class, 'startPhase2'])->name('cases.start-phase2');
    Route::get('/cases/{case}/timeline', [CaseController::class, 'timeline'])->name('cases.timeline');
    Route::get('/cases/{case}/pdf', [CaseController::class, 'pdf'])->name('cases.pdf');
    Route::get('/cases/{case}/stream', [\App\Http\Controllers\CaseStreamController::class, 'stream'])->name('cases.stream');
    Route::post('/cases/{case}/retry-agent', [CaseController::class, 'retryAgent'])->name('cases.retry-agent');
    Route::post('/cases/{case}/abort', [CaseController::class, 'abort'])->name('cases.abort');
    
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
    
    // AI Analysis
    Route::get('/ai-analysis', fn () => view('pages.ai-analysis'))->name('ai-analysis');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    
    // API endpoints for settings
    Route::get('/api/models', [SettingsController::class, 'getModels'])->name('api.models');
    Route::post('/api/estimate-cost', [SettingsController::class, 'estimateCost'])->name('api.estimate-cost');
});
