<?php

namespace Database\Seeders;

use App\Jobs\ProcessLawFileJob;
use App\Models\LawRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class LawLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $lawsPath = base_path('laws');
        
        if (!File::isDirectory($lawsPath)) {
            $this->command->warn('Laws directory not found at: ' . $lawsPath);
            return;
        }

        $files = File::files($lawsPath);
        
        if (empty($files)) {
            $this->command->warn('No law files found in laws directory');
            return;
        }

        $lawsData = [
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

        foreach ($files as $file) {
            $filename = $file->getFilename();
            
            $lawData = $lawsData[$filename] ?? [
                'name' => pathinfo($filename, PATHINFO_FILENAME),
                'description' => null,
                'category' => null,
                'effective_year' => null,
            ];

            // Check if law already exists
            $existingLaw = LawRegistry::where('name', $lawData['name'])->first();
            
            if ($existingLaw) {
                $this->command->info("⏭️  Skipping {$lawData['name']} (already exists)");
                continue;
            }

            // Create law registry
            $law = LawRegistry::create([
                'name' => $lawData['name'],
                'description' => $lawData['description'],
                'category' => $lawData['category'],
                'effective_year' => $lawData['effective_year'],
                'status' => 'active',
            ]);

            // Copy file to storage
            $storagePath = "law_library/{$law->id}/{$filename}";
            $fullPath = storage_path("app/{$storagePath}");
            
            // Create directory if it doesn't exist
            $dir = dirname($fullPath);
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            
            File::copy($file->getPathname(), $fullPath);

            // Create law file record
            $lawFile = $law->files()->create([
                'filename' => $filename,
                'file_path' => $storagePath,
                'file_size' => $file->getSize(),
                'mime_type' => 'text/plain',
                'encoding' => 'UTF-8',
            ]);

            // Dispatch processing job
            ProcessLawFileJob::dispatch($lawFile);

            $this->command->info("✅ Added {$lawData['name']} - processing in background");
        }

        $this->command->info('');
        $this->command->info('🎉 Law library seeding complete!');
        $this->command->info('📚 Total laws: ' . LawRegistry::count());
        $this->command->info('⏳ Processing jobs dispatched. Check Horizon for progress.');
    }
}
