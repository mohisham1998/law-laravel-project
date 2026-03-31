# Implementation Plan: Formatted Text Output Modal + Pre-Production Testing Cycle

**Branch**: `011-formatted-output-modal` | **Date**: 2026-03-31 | **Spec**: [spec.md](./spec.md)

## Summary

Replace the PDF export button and PDF generation flow with a formatted text output modal that renders Markdown pipeline output using the Cairo font in RTL mode. The modal auto-opens when the pipeline completes (via the existing SSE completion event) and is re-openable via a persistent button that replaces the PDF button. Additionally, a full pre-production testing cycle runs the sample case through the entire pipeline using Playwright MCP to validate the pipeline, RAG retrieval, notification system, and the new modal — with live bug-fixing if anything stops.

---

## Technical Context

**Language/Version**: PHP 8.x / Laravel 11
**Primary Dependencies**: Blade, Alpine.js, Tailwind CSS (CDN), marked.js (CDN — lightweight Markdown renderer, no build step)
**Storage**: SQLite (dev) — no schema changes required
**Testing**: Playwright MCP (browser_navigate, browser_click, browser_fill_form, browser_snapshot, browser_wait_for, browser_take_screenshot)
**Target Platform**: Laravel dev server (localhost) + Docker
**Performance Goals**: Modal renders in < 2s; marked.js parse of full output < 100ms
**Constraints**: No new pages; no new backend routes for modal; Cairo font already loaded globally; system-wide RTL already in place
**Scale/Scope**: Single case output view; all 9 agent outputs combined

---

## Constitution Check

| Principle | Status | Notes |
|---|---|---|
| I. Real-Time First | ✅ Pass | Modal auto-opens via existing SSE `phase3_completed` event — no page refresh |
| II. Zero-Cache UI | ✅ Pass | No new static assets; marked.js loaded via CDN with version-pinned URL |
| III. Self-Testing After Every Change | ✅ Pass | Explicit Playwright testing phase validates every change end-to-end |
| IV. Human-Readable Output | ✅ Pass | Markdown rendered as clean formatted Arabic legal text |
| V. Agent Logic From SKILL.md | ✅ Pass | No agent logic changes |
| VI. No New Pages | ✅ Pass | All changes are components + modifications to existing `cases/show.blade.php` |
| VII. General Dev Standards | ✅ Pass | Simple, maintainable — no over-engineering |

---

## Project Structure

### Documentation (this feature)

```text
.specify/specs/011-formatted-output-modal/
├── plan.md              ← this file
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
├── quickstart.md        ← Phase 1 output
└── contracts/           ← Phase 1 output
```

### Source Code Changes

```text
resources/views/
├── components/
│   ├── case-output-modal.blade.php        [NEW] Full output modal component
│   └── pdf-export-button.blade.php        [MODIFY] Repurpose → "عرض النتائج" button
├── pages/cases/
│   └── show.blade.php                     [MODIFY] Include modal + replace PDF button

app/Http/Controllers/
└── CaseController.php                     [MODIFY] Stub/redirect pdf() route gracefully
```

---

## Phase 0: Research

### R-001 — Markdown Rendering Library

**Decision**: `marked.js` v9+ loaded via CDN (`https://cdn.jsdelivr.net/npm/marked/marked.min.js`)
**Rationale**: Lightweight (< 20kb), zero dependencies, CDN-ready (no build step), handles Arabic text transparently (Unicode-safe), supports all required Markdown: `##` headers, `-` bullets, `1.` numbered lists, paragraphs. Used widely in Laravel Blade projects without Vite integration.
**Alternatives considered**:
- `markdown-it` — heavier, plugin ecosystem not needed here
- Server-side PHP Markdown (e.g., `league/commonmark`) — would add a backend round-trip and a new API endpoint; unnecessary since outputs are already available in `dbOutputsByAgent` JS variable
- `showdown.js` — less actively maintained

### R-002 — Output Data Available Client-Side

**Decision**: Combine outputs from `dbOutputsByAgent` JS variable (already injected on page load in `agent-timeline-live.blade.php`) by iterating agents 1→9 in order.
**Rationale**: `dbOutputsByAgent` already contains all markdown content keyed by `agent_number`. For in-progress streaming, the content is in `#agent-stream-{N}` DOM elements. The modal can read both: on-load from `dbOutputsByAgent`, on-completion from the streaming DOM.
**Alternatives considered**: New API endpoint to fetch all outputs — unnecessary; data already on page.

### R-003 — Auto-Open Trigger Point

**Decision**: Hook into the existing `activatePdfExportButton()` call in `agent-timeline-live.blade.php`. Replace with `activateOutputButton(); openOutputModal();`.
**Rationale**: `activatePdfExportButton()` is already called at exactly the right moment — when `data.status === 'phase3_completed' || data.status === 'completed_with_warnings'`. This is the correct SSE hook. No new event listener needed.
**File**: `resources/views/components/agent-timeline-live.blade.php` ~line 648

### R-004 — Button Label (Arabic UI Consistency)

**Decision**: Button label = `عرض النتائج` (View Results).
**Rationale**: Matches the Arabic UI vocabulary in the existing codebase (e.g., `عرض الجدول الزمني`, `عرض في المستندات`). Short, action-oriented, consistent with existing pattern `عرض + noun`. The disabled state label = `عرض النتائج (غير متاح)` matching `تصدير PDF (غير متاح)` pattern.

### R-005 — Playwright Test Approach for Sample Case

**Decision**: Use Playwright MCP tools sequentially: navigate → login → create case → upload docs → monitor pipeline → validate outputs → validate notifications → validate modal.
**Rationale**: The sample case at `D:\Work\Automize\Projects\law-laravel-project\sample case` contains:
- `intake.txt` — Arabic legal brief (defense memo request for criminal case before Jeddah court)
- `documents/` — 9 files: 3 case files (صحيفة الدعوى, مذكرة الرد, محضر الجلسة), 2 official extracts (ناجز, أحوال مدنية), 1 index, 3 law texts (نظام الإجراءات الجزائية, نظام المرافعات, نظام الإثبات)
- Uploading the law texts as documents will also verify RAG retrieval for law content
**Fix strategy**: If pipeline stops at any agent, read the error from the UI/logs, fix root cause in code, and resume from the halted agent using the existing "استئناف" button.

---

## Phase 1: Design & Contracts

### Data Model

See `data-model.md`. No new database tables or columns. The modal reads from:
- `CaseOutput` model: `agent_number`, `content` (Markdown text), `content_type` (markdown/md)
- `Case` model: `status`, `title`, `id` — already on page

### Component Contract: `case-output-modal.blade.php`

**Props**: `['case']` (Eloquent Case model)

**Behavior**:
- Hidden by default (`id="caseOutputModal"`, `class="hidden"`)
- Full viewport overlay with dark backdrop (`fixed inset-0 z-50`)
- Inner container: `max-w-4xl mx-auto`, `max-h-[90vh]`, scrollable (`overflow-y-auto`), RTL (`dir="rtl"`)
- Sticky header: title ("نتائج التحليل القانوني") + close button (×) + `data-modal-type="output"`
- Content area: `id="outputModalContent"` — Markdown rendered via `marked.js`
- Escape key listener: `document.addEventListener('keydown', e => e.key==='Escape' && closeOutputModal())`
- Empty state: if no content, show `لا توجد نتائج متاحة`
- Font: inherits Cairo from `body` (already set globally)

**Global JS functions** (defined inside component `<script>` block, accessible to timeline):
```javascript
function openOutputModal()     // reads outputs → parses Markdown → opens modal
function closeOutputModal()    // hides modal
function activateOutputButton() // enables the "عرض النتائج" button in sidebar
```

### Component Contract: `pdf-export-button.blade.php` (repurposed)

**Behavior change**:
- Remove PDF fetch logic entirely
- Rename button label: `تصدير PDF` → `عرض النتائج`
- Remove PDF icon (`picture_as_pdf`) → replace with `article` icon
- `onclick`: call `openOutputModal()` instead of `handlePdfExport()`
- Disabled state label: `عرض النتائج (غير متاح)`

### Route Contract: `cases.pdf`

- `CaseController::pdf()` — redirect to `cases.show` with a flash message: "تم استبدال تصدير PDF بعرض النتائج المنسقة"
- Route kept to avoid 404 errors from any cached links; no Dompdf dependency needed

### Integration Point: `agent-timeline-live.blade.php`

Replace:
```javascript
activatePdfExportButton();
```
With:
```javascript
activateOutputButton();
openOutputModal();
```
(Both functions defined in `case-output-modal.blade.php`)

---

## Phase 2: Playwright Testing Cycle (Pre-Production)

> **All testing is performed through Playwright MCP only.** No manual browser interaction.
> **Sample case path**: `D:\Work\Automize\Projects\law-laravel-project\sample case`

### T0 — Environment Pre-flight

1. Verify Docker containers are running: `docker ps` — confirm `app`, `queue` services up
2. Navigate to `http://localhost:8000` — verify app loads, RTL layout visible
3. Take screenshot for baseline

### T1 — Login

1. Navigate to `/login`
2. Fill credentials (from `.env` or seeded user)
3. Submit → verify redirect to dashboard

### T2 — Create New Case

1. Navigate to `/cases/create`
2. Fill case title: "قضية اختبار — مذكرة التعقيب والتجريح"
3. Paste full content of `intake.txt` into intake text field
4. Submit → verify redirect to case show page, status = "جديدة"

### T3 — Upload Documents

For each of the 9 files in `sample case/documents/`:
1. Navigate to documents section of the case (or `/documents` filtered by case)
2. Upload file using `browser_file_upload`
3. Verify filename appears in document list

### T4 — Start Analysis & Monitor Pipeline

1. On case show page, click "بدء التحليل" (or trigger via start button if available)
2. Wait for status to change from `جديدة` → `phase1_processing`
3. Monitor agent cards for each agent 1–9 completing:
   - Take screenshot after each agent completes
   - Verify agent card shows green checkmark or completed state
4. Watch for `awaiting_laws` (Phase 2 gate) — if it appears, handle the approval modal
5. Watch for `phase3_completed` or `completed_with_warnings`

**If pipeline stops (any agent fails or halts)**:
- Take screenshot, read error message from UI
- Check `storage/logs/laravel.log` for root cause
- Fix the underlying issue in code
- Use "استئناف من الوكيل N" button (or "إعادة من البداية") via Playwright
- Continue monitoring until completion

### T5 — Validate RAG Retrieval

1. Open agent output cards (agents 4–6, which use law references)
2. Verify output text contains references to law articles from the uploaded law files
   - Look for: "نظام الإثبات", "نظام المرافعات", "نظام الإجراءات الجزائية"
   - Presence of specific article numbers (e.g., "المادة X") confirms RAG is retrieving
3. Screenshot evidence of law references in outputs
4. **If no law references found**: investigate RAG embedding status in `/law-library`, check embeddings were generated, fix if needed

### T6 — Validate Notification System

1. Check notification bell icon in top navbar
2. Click bell → verify dropdown shows a completion notification
   - Expected: "اكتملت القضية" or "اكتملت مع تحذيرات" notification
3. Screenshot notification panel
4. **If no notification appears**: check `NotificationStreamController` SSE connection (DevTools network tab via `browser_evaluate`), check Redis connection, fix if needed

### T7 — Validate Formatted Output Modal

1. Verify modal auto-opened on pipeline completion (should be visible on page)
2. Screenshot: verify Cairo font, RTL direction, structured headers, bullet lists visible
3. Scroll through modal → verify sticky close button visible at bottom
4. Close modal (× button) → verify returns to case page
5. Click "عرض النتائج" button in sidebar → verify modal re-opens with same content
6. Press Escape key → verify modal closes
7. Screenshot final state

### T8 — Final Status

1. Screenshot: full case show page with completed status
2. Verify agent cards all show completed
3. Verify "عرض النتائج" button is active (not greyed out)
4. Log: test passed / issues found and fixed

---

## Complexity Tracking

No constitution violations. All changes are enhancements to existing files.

---

## Risks & Mitigations

| Risk | Mitigation |
|---|---|
| Pipeline stops mid-run during test | Use "استئناف من الوكيل N" resume feature; fix root cause before retrying |
| RAG embeddings not generated for law library | Check `/law-library` seeded status; run `php artisan db:seed` or re-embed via admin |
| SSE connection drops during long pipeline | Auto-reconnect with backoff is already implemented; Playwright waits with `browser_wait_for` |
| `marked.js` CDN unavailable | Pin to specific version; fallback: `<pre>` raw text display |
| Notification SSE not firing | Redis connection check; `NotificationStreamController` debug via logs |
