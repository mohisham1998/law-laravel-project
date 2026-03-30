# Bug Fix Report — Saudi Legal Orchestrator
**Date:** 2026-03-24
**Tested via:** Playwright MCP end-to-end test
**Result:** ✅ Full 13/13 agent pipeline completed without errors

---

## Bug 1: Phase2 Approval Modal Not Appearing in Real-Time

### Symptom
After creating a new case and Phase1 completing, the Phase2 approval modal did not appear automatically. The user had to manually refresh the page to see it.

### Root Cause (Primary)
**Duplicate `let currentCaseStatus` variable declaration across two `<script>` blocks in the same HTML page.**

- `resources/views/components/agent-timeline-live.blade.php` (line 242): `let currentCaseStatus = '...'`
- `resources/views/components/phase2-approval-modal.blade.php` (line 254): `let currentCaseStatus = '...'`

Both scripts are included in `resources/views/pages/cases/show.blade.php` in the same global scope. Browsers throw a `SyntaxError: Identifier 'currentCaseStatus' has already been declared` on the second declaration, which silently aborts one of the script blocks — preventing the SSE event listener (that shows the modal) from ever registering.

### Root Cause (Secondary)
**Stale `case.status_changed: awaiting_laws` event replayed from Redis on page reload.**

After approving Phase2 (form POST → redirect), the browser reconnects to the SSE stream which replays all historical events from Redis (including the original `case.status_changed: awaiting_laws` event from Phase1 completion). The modal's SSE listener would re-trigger and re-open the modal even though Phase2 was already running.

### Fixes Applied

**Fix 1a** — Renamed the variable in the modal component to avoid the duplicate `let` conflict:

**File:** `resources/views/components/phase2-approval-modal.blade.php`

```diff
- let currentCaseStatus = '{{ $case->status->value ?? $case->status }}';
+ let modalCaseStatus = '{{ $case->status->value ?? $case->status }}';
```

All references to `currentCaseStatus` inside the modal script were updated to `modalCaseStatus`.

**Fix 1b** — Added a `modalSuppressed` flag to prevent the modal from reopening when SSE replays historical `awaiting_laws` events after Phase2 has already been approved:

```javascript
// If page was loaded with a post-approval status, permanently suppress the modal
const modalPostApprovalStatuses = [
    'phase2_pending', 'phase2_processing', 'phase2_completed',
    'phase3_pending', 'phase3_processing', 'phase3_completed',
    'completed', 'completed_with_warnings', 'failed', 'paused', 'cancelled'
];
let modalSuppressed = modalPostApprovalStatuses.includes(modalCaseStatus);
```

The SSE listener now checks `!modalSuppressed` before showing the modal, and sets `modalSuppressed = true` upon receiving any post-approval status event.

**Fix 1c** — Updated the `DOMContentLoaded` check to respect `modalSuppressed`:

```diff
- if (modalCaseStatus === 'awaiting_laws') {
+ if (modalCaseStatus === 'awaiting_laws' && !modalSuppressed) {
```

### Verification
✅ Phase1 completed → modal appeared automatically in real-time (no page refresh needed)
✅ After approving Phase2 and page reloading, modal did NOT reappear despite stale SSE replay

---

## Bug 2: No Agents Running After Phase2 Approval

### Symptom
After approving Phase2, no agents were running. The queue showed multiple `ProcessPhase2Job` failures. Cases remained stuck at `awaiting_laws` or `failed`.

### Root Cause
**Laravel Horizon worker was configured with a 60-second timeout, but `ProcessPhase2Job` requires up to 32 minutes (30 min pipeline + 2 min overhead).**

The worker container was running:
```
php artisan horizon:work --timeout=60 --tries=1
```

`ProcessPhase2Job` declares:
- `$timeout = max(600, (pipeline_timeout_minutes * 60) + 120)` → 1920 seconds (32 min)
- `$tries = 3`

But Horizon's defaults of `--timeout=60 --tries=1` **override the job-level settings**. Every Phase2 job was being killed by the worker after 60 seconds, then marked as failed.

Additionally, `ProcessPhase2Job` implements `ShouldBeUnique` with `$uniqueFor = max(7200, timeout + 600)` — meaning failed jobs held the unique lock for 7200+ seconds, preventing any retry dispatch for over 2 hours.

### Fix Applied

**Created `config/horizon.php`** with supervisor configuration that sets proper timeout and tries:

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue'      => ['default'],
            'balance'    => 'auto',
            'maxProcesses' => 3,
            'minProcesses' => 1,
            'tries'      => 3,
            'timeout'    => 2100, // 35 minutes — covers Phase2/Phase3 pipeline
        ],
    ],
    'local' => [
        'supervisor-1' => [
            // same config as production
            'tries'   => 3,
            'timeout' => 2100,
        ],
    ],
],
```

**Worker container restarted** to apply the new config. Verified new worker flags:
```
--timeout=2100 --tries=3
```

**Failed jobs cleared** and any stale Redis unique locks removed:
```bash
php artisan queue:flush
```

### Verification
✅ Phase2 job ran to completion (all 9 agents completed)
✅ Phase3 job ran to completion (all 3 agents completed)
✅ No timeout failures in the queue

---

## End-to-End Test Results (Playwright MCP)

**Test case:** نزاع عمالي - مطالبة بمستحقات مالية (Labor Dispute - Financial Claims)
**Case ID:** `019d2160-5c11-7173-9967-5c7246003cdf`

| Step | Result |
|------|--------|
| Create new case via UI form | ✅ Success |
| Phase1 agent runs (Case Analysis) | ✅ Completed in ~5.7s |
| Phase2 approval modal appears in real-time (no refresh) | ✅ Fixed — appeared automatically via SSE |
| Click "Proceed" to approve Phase2 | ✅ Success |
| Modal does NOT reappear after page reload | ✅ Fixed — suppression flag works |
| Phase2 — Agent 1: القائد القانوني (Lead Counsel) | ✅ Completed |
| Phase2 — Agent 2: مدير الأدلة (Evidence Manager) | ✅ Completed |
| Phase2 — Agent 3: سلسلة الحفظ (Chain of Custody) | ✅ Completed |
| Phase2 — Agent 4: الجدول الزمني (Timeline Extractor) | ✅ Completed |
| Phase2 — Agent 5: مدير القانون (Law Manager) | ✅ Completed |
| Phase2 — Agent 6: مطابق الأنظمة (Statute Matcher) | ✅ Completed |
| Phase2 — Agent 7: الاستراتيجي (Defense Strategist) | ✅ Completed |
| Phase2 — Agent 8: الصائغ القانوني (Legal Drafter) | ✅ Completed |
| Phase2 — Agent 9: ضبط الجودة (Quality Assurance) | ✅ Completed |
| Phase3 — Agent 10: القاضي (Judge) | ✅ Completed |
| Phase3 — Agent 11: محامي الخصم (Devil's Advocate) | ✅ Completed |
| Phase3 — Agent 12: وكيل التحصين (Fortification Agent) | ✅ Completed |
| **Total: 13/13 agents** | ✅ **Pipeline complete** |

**Pipeline metrics:**
- Total processing time: ~4.4 minutes
- Agents completed: 9/13 (Phase2) + 3/13 (Phase3) + 1 (Phase1) = 13/13
- Confidence score: 89%
- Files produced: 31
- Total tokens: 308,037
- Self-corrections: 10

---

## Files Modified

| File | Change |
|------|--------|
| `resources/views/components/phase2-approval-modal.blade.php` | Renamed `currentCaseStatus` → `modalCaseStatus`; added `modalSuppressed` flag |
| `config/horizon.php` | **Created** — Horizon supervisor config with `timeout=2100`, `tries=3` |

---

## MCP Server Configuration Note

During investigation, it was discovered that the Playwright MCP server must be configured in `.mcp.json` at the project root (not in `settings.json`):

**File:** `.mcp.json` (project root)
```json
{
  "mcpServers": {
    "playwright": {
      "command": "cmd",
      "args": ["/c", "npx", "-y", "@playwright/mcp@latest"]
    }
  }
}
```

The `mcpServers` key is **not valid** in `settings.json` — it must be in `.mcp.json`.
