# Quickstart: 011-formatted-output-modal

## What This Changes

Replaces the PDF export button and PDF generation with a formatted text output modal that renders Markdown pipeline output. Also includes a Playwright-driven pre-production test cycle.

## Files to Modify

1. `resources/views/components/pdf-export-button.blade.php` — repurpose as output modal button
2. `resources/views/components/agent-timeline-live.blade.php` — replace `activatePdfExportButton()` with `activateOutputButton(); openOutputModal();`
3. `resources/views/pages/cases/show.blade.php` — include the new modal component

## Files to Create

1. `resources/views/components/case-output-modal.blade.php` — the modal itself

## Files to Stub (optional)

1. `app/Http/Controllers/CaseController.php` → `pdf()` method — redirect gracefully

## Run Order

```bash
# 1. Ensure dev server is running
php artisan serve

# 2. Ensure queue worker is running (for pipeline)
php artisan queue:work

# 3. Ensure Docker / Redis is up (for notifications)
docker-compose up -d

# 4. After implementing: test with Playwright MCP
# (use browser_navigate to http://localhost:8000)
```

## Test Sample Case Location

```
D:\Work\Automize\Projects\law-laravel-project\sample case\
├── intake.txt              ← paste as case intake text
└── documents\              ← upload all 9 files
```

## Key Integration Points

| What | Where | Change |
|---|---|---|
| Auto-open trigger | `agent-timeline-live.blade.php` ~line 648 | `activatePdfExportButton()` → `activateOutputButton(); openOutputModal();` |
| Button | `pdf-export-button.blade.php` | Label + icon + onclick |
| Modal include | `cases/show.blade.php` | Add `@include('components.case-output-modal', ...)` |
| marked.js | `case-output-modal.blade.php` | CDN script tag |
