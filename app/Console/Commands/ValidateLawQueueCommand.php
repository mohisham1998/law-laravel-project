<?php

namespace App\Console\Commands;

use App\Jobs\ProcessLawFileJob;
use App\Models\LawFile;
use App\Models\LawRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ValidateLawQueueCommand extends Command
{
    protected $signature = 'laws:validate-queue
                            {--law= : Law registry ID or name to check (optional)}
                            {--retry : Retry failed jobs for law processing}';

    protected $description = 'Validate Laravel queue for law file processing: list pending, failed files and failed jobs.';

    public function handle(): int
    {
        $lawFilter = $this->option('law');
        $doRetry = $this->option('retry');

        $this->info('=== Law queue validation ===');
        $this->newLine();

        // 1. Queue stats
        $jobsCount = DB::table('jobs')->count();
        $failedJobsCount = DB::table('failed_jobs')->count();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Jobs in queue', $jobsCount],
                ['Failed jobs (all)', $failedJobsCount],
            ]
        );
        $this->newLine();

        // 2. Failed jobs that are ProcessLawFileJob
        $failedJobs = DB::table('failed_jobs')->orderByDesc('failed_at')->get();
        $lawFileFailedJobs = [];
        foreach ($failedJobs as $job) {
            $payload = json_decode($job->payload, true);
            $displayName = $payload['displayName'] ?? null;
            if ($displayName !== ProcessLawFileJob::class) {
                continue;
            }
            $command = $payload['data']['command'] ?? null;
            if ($command) {
                $unserialized = @unserialize($command);
                if ($unserialized && isset($unserialized->lawFile)) {
                    $lawFile = $unserialized->lawFile;
                    $lawFileId = is_object($lawFile) ? $lawFile->id : ($lawFile['id'] ?? null);
                    $lawRegistryId = is_object($lawFile) ? $lawFile->law_registry_id : ($lawFile['law_registry_id'] ?? null);
                    $filename = is_object($lawFile) ? $lawFile->filename : ($lawFile['filename'] ?? '?');
                    $lawFileFailedJobs[] = [
                        'id' => $job->id,
                        'law_file_id' => $lawFileId,
                        'law_registry_id' => $lawRegistryId,
                        'filename' => $filename,
                        'failed_at' => $job->failed_at,
                        'exception' => strlen($job->exception) > 200 ? substr($job->exception, 0, 200) . '...' : $job->exception,
                    ];
                }
            }
        }

        if (!empty($lawFileFailedJobs)) {
            $this->warn('Failed jobs (law file processing):');
            $lawsById = LawRegistry::whereIn('id', array_column($lawFileFailedJobs, 'law_registry_id'))->get()->keyBy('id');
            $rows = [];
            foreach ($lawFileFailedJobs as $row) {
                $law = $lawsById->get($row['law_registry_id']);
                $lawName = $law ? $law->name : 'Law #' . $row['law_registry_id'];
                if ($lawFilter && $lawFilter != $row['law_registry_id'] && stripos($lawName, $lawFilter) === false) {
                    continue;
                }
                $rows[] = [
                    $row['law_file_id'],
                    $lawName,
                    $row['filename'],
                    $row['failed_at'],
                    substr($row['exception'], 0, 80),
                ];
            }
            if (!empty($rows)) {
                $this->table(['Law file ID', 'Law name', 'Filename', 'Failed at', 'Exception (excerpt)'], $rows);
            } else {
                $this->line('  (none matching filter)');
            }
            $this->newLine();
        }

        // 3. Law files not processed (pending or failed in DB)
        $query = LawFile::with('lawRegistry');
        if ($lawFilter) {
            if (is_numeric($lawFilter)) {
                $query->where('law_registry_id', (int) $lawFilter);
            } else {
                $query->whereHas('lawRegistry', fn ($q) => $q->where('name', 'like', '%' . $lawFilter . '%'));
            }
        }

        $pending = (clone $query)->where('is_processed', false)->whereNull('processing_error')->get();
        $failed = (clone $query)->where('is_processed', false)->whereNotNull('processing_error')->get();

        $this->info('Law files by status (DB):');
        $this->line('  Pending (not yet processed or still in queue): ' . $pending->count());
        foreach ($pending->take(20) as $f) {
            $lawName = $f->lawRegistry?->name ?? 'Law #' . $f->law_registry_id;
            $this->line('    - [Law: ' . $lawName . '] ' . $f->filename . ' (id: ' . $f->id . ')');
        }
        if ($pending->count() > 20) {
            $this->line('    ... and ' . ($pending->count() - 20) . ' more');
        }

        $this->line('  Failed (processing_error set): ' . $failed->count());
        foreach ($failed->take(20) as $f) {
            $lawName = $f->lawRegistry?->name ?? 'Law #' . $f->law_registry_id;
            $this->line('    - [Law: ' . $lawName . '] ' . $f->filename . ' (id: ' . $f->id . ')');
            $this->line('      Error: ' . Str::limit($f->processing_error ?? '', 80));
        }
        if ($failed->count() > 20) {
            $this->line('    ... and ' . ($failed->count() - 20) . ' more');
        }
        $this->newLine();

        // 4. Optional retry
        if ($doRetry && !empty($lawFileFailedJobs)) {
            if (!$this->confirm('Retry failed law file jobs? This will re-dispatch ProcessLawFileJob for each failed file.')) {
                return self::SUCCESS;
            }
            $retried = 0;
            foreach ($lawFileFailedJobs as $row) {
                $file = LawFile::find($row['law_file_id']);
                if ($file) {
                    $file->update(['processing_error' => null]);
                    ProcessLawFileJob::dispatch($file);
                    $this->line("  Dispatched: {$file->filename}");
                    $retried++;
                }
            }
            $this->info("Dispatched {$retried} job(s). Clear failed_jobs table manually if desired: php artisan queue:flush");
        }

        $this->info('=== Done ===');
        return self::SUCCESS;
    }
}
