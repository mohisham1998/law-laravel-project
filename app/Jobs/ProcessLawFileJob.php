<?php

namespace App\Jobs;

use App\Models\LawFile;
use App\Services\RAG\LawProcessingService;
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
            } else {
                $message = $result['message'] ?? 'Unknown error';
                $this->lawFile->update(['processing_error' => $message]);
                Log::error("Failed to process {$this->lawFile->filename}", ['message' => $message]);
            }
        } catch (\Throwable $e) {
            Log::error("Exception processing law file {$this->lawFile->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->lawFile->update(['processing_error' => $e->getMessage()]);
            // Do not rethrow - no more failed jobs; error is stored on the file
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->lawFile->update(['processing_error' => $e->getMessage()]);
        Log::error("ProcessLawFileJob failed for law file {$this->lawFile->id}", [
            'filename' => $this->lawFile->filename,
            'error' => $e->getMessage(),
        ]);
    }
}
