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

class GenerateLawEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900; // 15 minutes for large files

    public $tries = 3;

    public function __construct(
        public LawFile $lawFile
    ) {
        $this->onQueue('default');
    }

    public function handle(LawProcessingService $processingService): void
    {
        try {
            $processingService->generateEmbeddingsForLawFile($this->lawFile);
            $this->lawFile->update(['processing_error' => null]);
            Log::info("Generated embeddings for law file: {$this->lawFile->filename}");
        } catch (\Throwable $e) {
            Log::error("GenerateLawEmbeddingsJob failed for {$this->lawFile->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->lawFile->update(['processing_error' => $e->getMessage()]);
            // Do not rethrow - we recorded the error; job is considered handled
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->lawFile->update(['processing_error' => $e->getMessage()]);
        Log::error("GenerateLawEmbeddingsJob failed (final) for law file {$this->lawFile->id}", [
            'filename' => $this->lawFile->filename,
            'error' => $e->getMessage(),
        ]);
    }
}
