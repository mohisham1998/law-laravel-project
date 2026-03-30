# Quickstart: AI-Powered Input Auditing Modal

**Branch**: `004-ai-audit-modal` | **Date**: 2026-03-24

## Prerequisites

- Laravel 11 dev environment running (`php artisan serve`)
- Queue worker running (`php artisan queue:work`)
- OpenRouter API key configured in `.env`
- At least one case processed through Phase 1 (status: `awaiting_laws`)

## New Configuration

Add to `.env`:

```
AUDIT_PASSING_THRESHOLD=70
AUDIT_SOFT_TIMEOUT=10
AUDIT_HARD_TIMEOUT=30
```

All optional — defaults are sensible.

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `app/Services/InputAuditService.php` | **NEW** | LLM audit service — builds prompt, calls OpenRouter, parses response |
| `app/Http/Controllers/CaseController.php` | **MODIFIED** | Add `audit()` and `uploadAuditFile()` methods; modify `startPhase2()` to persist inline inputs |
| `resources/views/components/phase2-approval-modal.blade.php` | **REWRITTEN** | Full modal replacement with audit UI |
| `config/legal.php` | **MODIFIED** | Add audit config entries |
| `routes/web.php` | **MODIFIED** | Add audit routes |

## Verification Steps

1. **Open a case in `awaiting_laws` status** → Modal should open automatically
2. **Observe loading state** → Skeleton on score bar and feedback panel; case summary still visible
3. **Wait for audit** → Score bar animates with current + projected scores; summary and feedback tiers appear
4. **Check feedback tiers** → Required (red), recommended (amber), optional (green) with labels and reasons
5. **Fill an inline text input** → After 800ms, score bar re-animates with updated score
6. **Upload a file via inline input** → File uploads, re-audit fires, score updates
7. **Check CTA** → Below threshold: "Proceed Anyway" with warning. At/above: "Proceed"
8. **Click Proceed** → Inline inputs persisted to case, Phase 2 starts
9. **Test Cancel** → Modal closes, no inputs persisted
10. **Test failure** → Disconnect network or use invalid API key → Fallback message, Proceed available

## Quick Smoke Test

```bash
# 1. Ensure a case exists in awaiting_laws status
php artisan tinker --execute="echo App\Models\LegalCase::where('status', 'awaiting_laws')->count();"

# 2. Start the server
php artisan serve

# 3. Navigate to the case
# Open browser to http://localhost:8000/cases/{case-id}
# The audit modal should appear automatically
```
