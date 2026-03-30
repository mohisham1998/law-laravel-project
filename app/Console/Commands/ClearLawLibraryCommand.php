<?php

namespace App\Console\Commands;

use App\Models\LawRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearLawLibraryCommand extends Command
{
    protected $signature = 'law-library:clear
                            {--force : Skip confirmation}';

    protected $description = 'Remove all laws from the law library (RAG). Use for production when you want to start with a clean RAG and add real laws via the UI.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will delete all law registry entries and their files, articles, and embeddings. Continue?')) {
            return self::FAILURE;
        }

        $this->info('Clearing law library…');

        DB::transaction(function () {
            DB::table('law_search_cache')->delete();
            LawRegistry::query()->delete();
        });

        $this->info('Law library cleared. Add laws via the Law Library page (/law-library) or /laws, then let the queue process the files.');

        return self::SUCCESS;
    }
}
