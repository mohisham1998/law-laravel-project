# Quickstart: Case Output Page Redesign

**Branch**: `002-case-output-redesign`

## Prerequisites

- Docker Desktop running
- Containers started: `docker compose up -d`
- Queue worker running: `docker exec -d law-laravel-project-app-1 php artisan queue:work`

## Dev Workflow

```bash
# After editing any Blade view:
docker exec law-laravel-project-app-1 php artisan view:clear

# After editing any PHP file (OPcache in Docker):
docker exec law-laravel-project-app-1 killall -SIGUSR2 php-fpm

# Watch logs:
docker compose logs -f app
```

## Test Cases

| Scenario | Case Status to Use | What to Verify |
|----------|-------------------|----------------|
| Pipeline tracker — active | `phase2_processing` | Tracker visible above grid, correct agent pulsing |
| Pipeline tracker — complete | `phase3_completed` | All 13 bubbles green, 100% bar |
| Phase gate banner | `phase2_completed` | Full-width banner visible without scrolling |
| PDF export | `phase2_completed` or `phase3_completed` | Button in sidebar, spinner on click, download starts |
| Collapsed default | `phase3_completed` | All 13 agent cards collapsed on load |
| Exactly one streaming area | Any processing status | Count `<details open>` agent cards — should be 1 |
| No duplicate terminal | `phase2_processing` | No dark terminal panel visible separately from agent cards |
| Navbar overlap | Any | Scroll to top — case title fully visible, 8px+ clearance below header |

## Seed / Real Case

See `docs/PRODUCTION_REAL_CASE_SETUP.md` for creating a real test case.

To check current case statuses:
```bash
docker exec law-laravel-project-app-1 php artisan tinker --execute \
  "App\Models\LegalCase::select('id','title','status')->get()->each(fn(\$c) => dump(\$c->id, \$c->status));"
```
