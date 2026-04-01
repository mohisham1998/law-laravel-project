<?php

namespace App\Console\Commands;

use App\Jobs\GenerateLawEmbeddingsJob;
use App\Models\LawRegistry;
use App\Services\RAG\LawProcessingService;
use App\Services\RAG\LawParserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ImportCleanedLawsCommand extends Command
{
    protected $signature = 'laws:import
                            {source? : Path to directory containing cleaned law files (default: 1-4 Update/laws_cleaned)}
                            {--clean : Truncate all law tables before importing}
                            {--dry-run : Parse and count files only, do not insert}
                            {--no-embeddings : Skip dispatching embedding jobs (useful for testing)}
                            {--category= : Import only this category subdirectory}';

    protected $description = 'Import cleaned Saudi law .txt files into the law library (supports nested category directories)';

    /**
     * Subdirectories to exclude from import (abrogated / superseded laws).
     */
    private const EXCLUDE_DIRS = ['ملغاة'];

    /**
     * Map Arabic category directory names to English category slugs for the DB.
     */
    private const CATEGORY_MAP = [
        'أنظمة_أساسية' => 'أنظمة أساسية',
        'أنظمة_عادية'  => 'أنظمة عادية',
        'تنظيمات'      => 'تنظيمات',
        'لوائح'         => 'لوائح',
        'غير_مصنف'     => 'غير مصنف',
    ];

    public function handle(LawProcessingService $processingService, LawParserService $parser): int
    {
        $sourcePath = $this->argument('source')
            ?? base_path('1-4 Update/laws_cleaned');

        if (!is_dir($sourcePath)) {
            $this->error("Source directory not found: {$sourcePath}");
            return self::FAILURE;
        }

        // --- Optional clean ---
        if ($this->option('clean') && !$this->option('dry-run')) {
            $this->info('Clearing existing law library…');
            DB::transaction(function () {
                DB::table('law_search_cache')->delete();
                LawRegistry::query()->delete();
            });
            $this->info('Existing records cleared.');
        }

        // --- Discover files ---
        $files = $this->discoverFiles($sourcePath);

        if (empty($files)) {
            $this->warn('No .txt files found in source directory (excluding ملغاة).');
            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d law files to import.', count($files)));

        if ($this->option('dry-run')) {
            $this->table(['File', 'Category'], array_map(
                fn($f) => [$f['filename'], $f['dir_category']],
                $files
            ));
            return self::SUCCESS;
        }

        // --- Import ---
        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $totalArticles = 0;

        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        foreach ($files as $fileInfo) {
            $result = $this->importFile($fileInfo, $processingService, $parser);

            if ($result === 'skipped') {
                $skipped++;
            } elseif ($result === false) {
                $failed++;
            } else {
                $imported++;
                $totalArticles += (int) $result;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Import complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Imported',       $imported],
                ['Skipped (exist)', $skipped],
                ['Failed',         $failed],
                ['Total Articles', $totalArticles],
                ['Total in DB',    LawRegistry::count()],
            ]
        );

        if (!$this->option('no-embeddings') && $imported > 0) {
            $this->info('Embedding jobs dispatched. Run "php artisan queue:work" to generate vectors.');
        }

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------------

    /**
     * Walk the source directory and collect all .txt files, excluding skipped dirs.
     *
     * @return array<array{path: string, filename: string, dir_category: string}>
     */
    private function discoverFiles(string $sourcePath): array
    {
        $files = [];
        $onlyCategory = $this->option('category');

        $dirs = array_filter(scandir($sourcePath), fn($d) => !in_array($d, ['.', '..'], true));

        foreach ($dirs as $dir) {
            $dirPath = $sourcePath . DIRECTORY_SEPARATOR . $dir;

            if (!is_dir($dirPath)) {
                continue;
            }

            if (in_array($dir, self::EXCLUDE_DIRS, true)) {
                $this->line("  Skipping excluded directory: {$dir}");
                continue;
            }

            if ($onlyCategory && $dir !== $onlyCategory) {
                continue;
            }

            $categoryLabel = self::CATEGORY_MAP[$dir] ?? $dir;

            foreach (scandir($dirPath) as $filename) {
                if (!str_ends_with($filename, '.txt')) {
                    continue;
                }

                $files[] = [
                    'path'         => $dirPath . DIRECTORY_SEPARATOR . $filename,
                    'filename'     => $filename,
                    'dir_category' => $categoryLabel,
                ];
            }
        }

        return $files;
    }

    /**
     * Import a single law file.
     *
     * @return int|false|'skipped'  Returns article count on success, 'skipped' if already exists, false on failure.
     */
    private function importFile(
        array $fileInfo,
        LawProcessingService $processingService,
        LawParserService $parser
    ): int|false|string {
        $path      = $fileInfo['path'];
        $filename  = $fileInfo['filename'];
        $dirCategory = $fileInfo['dir_category'];

        $content = file_get_contents($path);

        // Parse header: الاسم / التصنيف / الحالة / تاريخ الإصدار
        $header = $parser->parseHeader($content);

        $name = $header['name'] ?: pathinfo($filename, PATHINFO_FILENAME);

        // Skip if already in DB
        if (LawRegistry::where('name', $name)->exists()) {
            return 'skipped';
        }

        // Status
        $status = match ($header['status']) {
            'ساري'  => 'active',
            'لاغي'  => 'abrogated',
            default => 'active',
        };

        // Category: prefer التصنيف from header, else directory name
        $rawCategory = $header['category'] ?: $dirCategory;
        // Keep only the primary segment (before " / ")
        $category = trim(explode('/', $rawCategory)[0]);

        // Effective year: extract Hijri year (4 digits starting with 1)
        $effectiveYear = '';
        if (preg_match('/\b(1\d{3})\b/', $header['issue_date'], $ym)) {
            $effectiveYear = $ym[1];
        }

        // Description from summary
        $description = $header['summary']
            ? mb_substr($header['summary'], 0, 500)
            : null;

        try {
            // Create registry record
            $law = LawRegistry::create([
                'name'           => $name,
                'description'    => $description,
                'category'       => $category,
                'effective_year' => $effectiveYear ?: null,
                'status'         => $status,
                'metadata'       => [
                    'decree_ref' => $header['decree_ref'] ?? null,
                    'issue_date' => $header['issue_date'] ?? null,
                    'source_dir' => $dirCategory,
                ],
            ]);

            // Copy file to storage
            $storagePath = "law_library/{$law->id}/{$filename}";
            $storageFullPath = storage_path("app/{$storagePath}");
            File::ensureDirectoryExists(dirname($storageFullPath));
            File::copy($path, $storageFullPath);

            // Create LawFile record
            $lawFile = $law->files()->create([
                'filename'   => $filename,
                'file_path'  => $storagePath,
                'file_size'  => filesize($path),
                'mime_type'  => 'text/plain',
                'encoding'   => 'UTF-8',
            ]);

            // Process (extract articles)
            $result = $processingService->processLawFile($lawFile);

            if (!$result['success']) {
                Log::warning("ImportCleanedLaws: processing failed for {$name}: " . ($result['message'] ?? ''));
                return false;
            }

            // Dispatch embedding generation
            if (!$this->option('no-embeddings')) {
                GenerateLawEmbeddingsJob::dispatch($lawFile);
            }

            return (int) ($result['articles_count'] ?? 0);
        } catch (\Throwable $e) {
            Log::error("ImportCleanedLaws: exception for {$name}: " . $e->getMessage());
            $this->newLine();
            $this->warn("  Failed: {$name} — " . $e->getMessage());
            return false;
        }
    }
}
