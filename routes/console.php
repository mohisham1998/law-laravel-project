<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recovery: any case stuck in phase2_completed for more than 2 minutes gets Phase 3 auto-dispatched.
Artisan::command('cases:recover-phase3', function () {
    $stuck = \App\Models\LegalCase::where('status', \App\Enums\CaseStatus::Phase2Completed)
        ->where('updated_at', '<', now()->subMinutes(2))
        ->get();

    foreach ($stuck as $case) {
        $case->update(['status' => \App\Enums\CaseStatus::Phase3Pending, 'phase' => 3]);
        \App\Jobs\ProcessPhase3Job::dispatch($case);
        $this->info("Recovered case {$case->id}: {$case->title}");
    }

    if ($stuck->isEmpty()) {
        $this->info('No stuck cases found.');
    }
})->purpose('Auto-dispatch Phase 3 for cases stuck in phase2_completed');

