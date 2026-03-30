# Quickstart: Pipeline Reliability & Quality Enforcement

**Feature**: 005-pipeline-reliability
**Date**: 2026-03-24

---

## Prerequisites

- Laravel 11 development environment running
- Queue worker active (`php artisan queue:work`)
- Database migrated (`php artisan migrate`)
- At least one case with seeded law library for testing

## Setup

```bash
# 1. Switch to feature branch
git checkout 005-pipeline-reliability

# 2. Run the new migration
php artisan migrate

# 3. Restart queue worker (picks up new job retry config)
php artisan queue:restart
php artisan queue:work
```

## Testing the Changes

### Test 1: Halt on Agent Failure

1. Submit a new case for processing
2. To simulate failure: temporarily set an invalid API key in `.env` (`OPENROUTER_API_KEY=invalid`) after Phase 1 completes
3. Observe: Pipeline should halt at the first agent that fails
4. Check: Case status shows "Halted" with the failed agent identified
5. Check: No downstream agents executed (status = "Skipped")
6. Restore API key and click "Retry" — pipeline resumes from the halted agent

### Test 2: Low-Confidence Warnings

1. Process a case to completion
2. Check the case detail view for any amber warning badges on agent outputs
3. If present: verify the confidence score and threshold are displayed
4. If absent: the case had all high-confidence outputs (expected for well-structured cases)
5. Check the case list: cases with warnings should show an amber indicator

### Test 3: Pipeline Timeout

1. Set `PIPELINE_TIMEOUT_MINUTES=1` in `.env` (1-minute timeout for testing)
2. Submit a case — it should timeout during Phase 2
3. Check: Case status shows "Timed Out" with completed/skipped agents listed
4. Reset timeout to 30 and retry — pipeline resumes from the incomplete agent

### Test 4: Retry Budget

1. Process a case that encounters transient failures
2. Monitor the retry budget counter in the case detail
3. If budget exhausts: pipeline halts with "retry budget exhausted" message

## Key Files Changed

| Area | Files |
|------|-------|
| Orchestration | `app/Services/Orchestration/LegalOrchestrator.php` |
| Phase jobs | `app/Jobs/ProcessPhase2Job.php`, `app/Jobs/ProcessPhase3Job.php` |
| Agent base | `app/Services/Agents/Phase2/Phase2BaseAgent.php` |
| Models | `app/Models/LegalCase.php`, `app/Models/AgentExecution.php` |
| Enums | `app/Enums/CaseStatus.php`, `app/Enums/AgentStatus.php` |
| Config | `config/legal.php` |
| Migration | `database/migrations/2026_03_24_000001_add_pipeline_reliability_fields.php` |
| Views | `resources/views/components/agent-timeline-live.blade.php`, `resources/views/components/case-insights.blade.php`, `resources/views/pages/cases/index.blade.php`, `resources/views/pages/cases/show.blade.php` |
| Events | `app/Services/CaseEventService.php` |

## Configuration

All new config values in `config/legal.php`:

```php
'pipeline_timeout_minutes' => env('PIPELINE_TIMEOUT_MINUTES', 30),
'retry_budget_per_case' => env('RETRY_BUDGET_PER_CASE', 10),
```
