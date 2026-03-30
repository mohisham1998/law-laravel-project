<?php

namespace App\Jobs;

use App\Models\LawFile;
use App\Services\RAG\LawProcessingService;
use App\Services\UserNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLawFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes (parsing only; embeddings run in separate job)

    public $tries = 3;

    public function __construct(
        public LawFile $lawFile
    ) {
        $this->onQueue('default');
    }

    public function handle(LawProcessingService $processingService): void
    {
        try {
            Log::info("Processing law file: {$this->lawFile->filename}");

            $result = $processingService->processLawFile($this->lawFile);

            if ($result['success']) {
                $this->lawFile->update(['processing_error' => null]);
                GenerateLawEmbeddingsJob::dispatch($this->lawFile);
                Log::info("Parsed {$this->lawFile->filename}, dispatched embedding job", [
                    'articles_count' => $result['articles_count'] ?? 0,
                ]);

                if ($this->lawFile->uploaded_by_user_id) {
                    app(UserNotificationService::class)->emitToUser($this->lawFile->uploaded_by_user_id, 'rag.processing.parsed', [
                        'title' => 'تم تحليل ملف نظام بنجاح',
                        'body' => $this->lawFile->filename,
                        'severity' => 'info',
                        'url' => route('law-library.show', $this->lawFile->law_registry_id),
                        'law_file_id' => $this->lawFile->id,
                        'law_registry_id' => $this->lawFile->law_registry_id,
                    ]);
                }
            } else {
                $message = $result['message'] ?? 'فشلت المعالجة دون سبب محدد';
                $this->lawFile->update([
                    'processing_error' => $message,
                    'is_processed' => false,
                ]);
                Log::error("Failed to process {$this->lawFile->filename}", ['message' => $message]);

                if ($this->lawFile->uploaded_by_user_id) {
                    app(UserNotificationService::class)->emitToUser($this->lawFile->uploaded_by_user_id, 'rag.processing.failed', [
                        'title' => 'فشل تحليل ملف نظام',
                        'body' => $this->lawFile->filename . ' - ' . $message,
                        'severity' => 'error',
                        'url' => route('law-library.show', $this->lawFile->law_registry_id),
                        'law_file_id' => $this->lawFile->id,
                        'law_registry_id' => $this->lawFile->law_registry_id,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error("Exception processing law file {$this->lawFile->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $userMessage = $this->getUserFriendlyError($e);
            $this->lawFile->update([
                'processing_error' => $userMessage,
                'is_processed' => false,
            ]);

            if ($this->lawFile->uploaded_by_user_id) {
                app(UserNotificationService::class)->emitToUser($this->lawFile->uploaded_by_user_id, 'rag.processing.failed', [
                    'title' => 'فشل تحليل ملف نظام',
                    'body' => $this->lawFile->filename . ' - ' . $userMessage,
                    'severity' => 'error',
                    'url' => route('law-library.show', $this->lawFile->law_registry_id),
                    'law_file_id' => $this->lawFile->id,
                    'law_registry_id' => $this->lawFile->law_registry_id,
                ]);
            }
        }
    }

    protected function getUserFriendlyError(\Throwable $e): string
    {
        $message = $e->getMessage();
        
        if (str_contains($message, 'file not found') || str_contains($message, 'file_exists')) {
            return 'لم يتم العثور على الملف. تأكد من رفع الملف بشكل صحيح.';
        }
        
        if (str_contains($message, 'timeout') || str_contains($message, 'Maximum execution')) {
            return 'انتهت مهلة المعالجة. الملف قد يكون كبيراً جداً.';
        }
        
        if (str_contains($message, 'memory') || str_contains($message, 'Memory')) {
            return 'نفدت ذاكرة الخادم. حاول تقسيم الملف إلى ملفات أصغر.';
        }
        
        return 'فشلت المعالجة: ' . $message;
    }

    public function failed(\Throwable $e): void
    {
        $this->lawFile->update(['processing_error' => $e->getMessage()]);
        Log::error("ProcessLawFileJob failed for law file {$this->lawFile->id}", [
            'filename' => $this->lawFile->filename,
            'error' => $e->getMessage(),
        ]);

        if ($this->lawFile->uploaded_by_user_id) {
            app(UserNotificationService::class)->emitToUser($this->lawFile->uploaded_by_user_id, 'rag.processing.failed', [
                'title' => 'فشل تحليل ملف نظام',
                'body' => $this->lawFile->filename . ' - ' . $e->getMessage(),
                'severity' => 'error',
                'url' => route('law-library.show', $this->lawFile->law_registry_id),
                'law_file_id' => $this->lawFile->id,
                'law_registry_id' => $this->lawFile->law_registry_id,
            ]);
        }
    }
}
