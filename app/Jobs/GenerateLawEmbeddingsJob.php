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

class GenerateLawEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;
    
    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 900; // 15 minutes for large files

    public function __construct(
        public LawFile $lawFile
    ) {
        $this->onQueue('default');
    }

    public function handle(LawProcessingService $processingService): void
    {
        try {
            $this->lawFile->refresh();
            $processingService->generateEmbeddingsForLawFile($this->lawFile);
            $this->lawFile->update(['processing_error' => null]);
            Log::info("Generated embeddings for law file: {$this->lawFile->filename}");

            if ($this->lawFile->uploaded_by_user_id) {
                app(UserNotificationService::class)->emitToUser($this->lawFile->uploaded_by_user_id, 'rag.processing.completed', [
                    'title' => 'اكتملت فهرسة ملف النظام',
                    'body' => $this->lawFile->filename,
                    'severity' => 'success',
                    'url' => route('law-library.show', $this->lawFile->law_registry_id),
                    'law_file_id' => $this->lawFile->id,
                    'law_registry_id' => $this->lawFile->law_registry_id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("GenerateLawEmbeddingsJob failed for {$this->lawFile->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $userMessage = $this->getUserFriendlyError($e);
            $this->lawFile->update(['processing_error' => $userMessage]);

            if ($this->lawFile->uploaded_by_user_id) {
                app(UserNotificationService::class)->emitToUser($this->lawFile->uploaded_by_user_id, 'rag.processing.failed', [
                    'title' => 'فشلت فهرسة ملف النظام',
                    'body' => $this->lawFile->filename . ' - ' . $userMessage,
                    'severity' => 'error',
                    'url' => route('law-library.show', $this->lawFile->law_registry_id),
                    'law_file_id' => $this->lawFile->id,
                    'law_registry_id' => $this->lawFile->law_registry_id,
                ]);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        $userMessage = $this->getUserFriendlyError($e);
        $this->lawFile->update(['processing_error' => $userMessage]);
        Log::error("GenerateLawEmbeddingsJob failed (final) for law file {$this->lawFile->id}", [
            'filename' => $this->lawFile->filename,
            'error' => $e->getMessage(),
        ]);

        if ($this->lawFile->uploaded_by_user_id) {
            app(UserNotificationService::class)->emitToUser($this->lawFile->uploaded_by_user_id, 'rag.processing.failed', [
                'title' => 'فشلت فهرسة ملف النظام',
                'body' => $this->lawFile->filename . ' - ' . $userMessage,
                'severity' => 'error',
                'url' => route('law-library.show', $this->lawFile->law_registry_id),
                'law_file_id' => $this->lawFile->id,
                'law_registry_id' => $this->lawFile->law_registry_id,
            ]);
        }
    }

    protected function getUserFriendlyError(\Throwable $e): string
    {
        $message = $e->getMessage();
        
        if (str_contains($message, 'Connection') || str_contains($message, 'cURL') || str_contains($message, 'OpenAI')) {
            return 'فشل الاتصال بخدمة الذكاء الاصطناعي. تأكد من الاتصال بالإنترنت وصحة مفاتيح OpenAI API.';
        }
        
        if (str_contains($message, 'API key') || str_contains($message, 'authentication')) {
            return 'خطأ في مفتاح OpenAI API. تأكد من صحة المفتاح في ملف .env';
        }
        
        if (str_contains($message, 'timeout') || str_contains($message, 'time limit')) {
            return 'انتهت مهلة توليد الفهرسة. عدد المواد كبير جداً، حاول تقسيم الملف.';
        }
        
        if (str_contains($message, 'rate limit') || str_contains($message, 'quota')) {
            return 'تم تجاوز حد الاستخدام لخدمة OpenAI. انتظر قليلاً ثم أعد المحاولة.';
        }
        
        if (str_contains($message, 'name') && str_contains($message, 'null')) {
            return 'خطأ في تحميل بيانات النظام. تأكد من أن النظام موجود في قاعدة البيانات.';
        }
        
        return 'فشل توليد الفهرسة: ' . $message;
    }
}
