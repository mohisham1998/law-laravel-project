<?php

namespace Database\Seeders;

use App\Jobs\GenerateLawEmbeddingsJob;
use App\Models\LawRegistry;
use App\Services\RAG\LawProcessingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class LawLibrarySeeder extends Seeder
{
    /**
     * Default Saudi law files shipped in storage/app/laws (or laws/ at project root).
     * Seeds realistic laws so cases can match valid legislation out of the box.
     */
    protected array $lawsData = [
        'نظام الإثبات.txt' => [
            'name' => 'نظام الإثبات',
            'description' => 'نظام الإثبات السعودي الصادر عام 1443 هـ، ينظم إجراءات الإثبات في المحاكم',
            'category' => 'evidence',
            'effective_year' => '1443',
        ],
        'نظام المرافعات الشرعية.txt' => [
            'name' => 'نظام المرافعات الشرعية',
            'description' => 'نظام المرافعات الشرعية السعودي، ينظم الإجراءات أمام المحاكم الشرعية',
            'category' => 'procedures',
            'effective_year' => '1435',
        ],
        'اللائحة التنفيذية لنظام الإجراءات الجزائية.txt' => [
            'name' => 'اللائحة التنفيذية لنظام الإجراءات الجزائية',
            'description' => 'اللائحة التنفيذية لنظام الإجراءات الجزائية',
            'category' => 'criminal',
            'effective_year' => '1435',
        ],
        'اللوائح التنفيذية لنظام المرافعات الشرعية.txt' => [
            'name' => 'اللوائح التنفيذية لنظام المرافعات الشرعية',
            'description' => 'اللوائح التنفيذية لنظام المرافعات الشرعية',
            'category' => 'procedures',
            'effective_year' => '1435',
        ],
    ];

    public function run(): void
    {
        $lawsPath = base_path('laws');

        if (!File::isDirectory($lawsPath)) {
            $this->command->warn('Laws directory not found at: ' . $lawsPath);
            $this->command->warn('Create a "laws" folder in project root and add .txt law files to seed the RAG.');
            return;
        }

        $files = File::files($lawsPath);
        $allowed = array_keys($this->lawsData);
        $files = array_filter($files, fn($f) => in_array($f->getFilename(), $allowed, true));

        if (empty($files)) {
            $this->command->warn('No default law files found in laws/ (expected: ' . implode(', ', $allowed) . ')');
            return;
        }

        $processingService = app(LawProcessingService::class);
        $processedCount = 0;
        $articlesCount = 0;

        foreach ($files as $file) {
            $filename = $file->getFilename();
            $lawData = $this->lawsData[$filename];

            $existingLaw = LawRegistry::where('name', $lawData['name'])->first();
            if ($existingLaw) {
                $this->command->info("⏭️  Skipping {$lawData['name']} (already exists)");
                continue;
            }

            // Determine status from the الحالة: field in the file header
            $fileContent = File::get($file->getPathname());
            $arabicStatus = '';
            if (preg_match('/^الحالة:\s*(.+)$/um', $fileContent, $statusMatch)) {
                $arabicStatus = trim($statusMatch[1]);
            }
            $mappedStatus = match ($arabicStatus) {
                'ساري'  => 'active',
                'لاغي'  => 'abrogated',
                default => 'draft',
            };

            $law = LawRegistry::create([
                'name' => $lawData['name'],
                'description' => $lawData['description'],
                'category' => $lawData['category'],
                'effective_year' => $lawData['effective_year'],
                'status' => $mappedStatus,
            ]);

            $storagePath = "law_library/{$law->id}/{$filename}";
            $fullPath = storage_path("app/{$storagePath}");
            $dir = dirname($fullPath);
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            File::copy($file->getPathname(), $fullPath);

            $lawFile = $law->files()->create([
                'filename' => $filename,
                'file_path' => $storagePath,
                'file_size' => $file->getSize(),
                'mime_type' => 'text/plain',
                'encoding' => 'UTF-8',
            ]);

            // Process synchronously so articles are in DB immediately (ready for case matching)
            $result = $processingService->processLawFile($lawFile);
            if ($result['success']) {
                $articlesCount += $result['articles_count'] ?? 0;
                $processedCount++;
                GenerateLawEmbeddingsJob::dispatch($lawFile);
                $this->command->info("✅ {$lawData['name']} — {$result['articles_count']} مواد (معالج، جاري فهرسة البحث الدلالي)");
            } else {
                $this->command->warn("⚠️ {$lawData['name']} — فشل: " . ($result['message'] ?? 'unknown'));
            }
        }

        $this->command->info('');
        $this->command->info('🎉 Law library seeding complete!');
        $this->command->info('📚 Laws: ' . LawRegistry::count() . ' | ملفات معالجة: ' . $processedCount . ' | مواد: ' . $articlesCount);
        $this->command->info('🔍 تشغيل الطابور (Horizon/queue:work) لفهرسة المواد للبحث الدلالي (RAG).');
    }
}
