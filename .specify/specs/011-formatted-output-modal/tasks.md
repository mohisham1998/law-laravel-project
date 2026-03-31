# Tasks: Formatted Text Output Modal + Pre-Production Testing Cycle

**Input**: Design documents from `.specify/specs/011-formatted-output-modal/`
**Branch**: `011-formatted-output-modal`
**Tests**: No automated test files — validation is performed live via Playwright MCP end-to-end

**Organization**: Tasks are grouped by user story and testing phase for independent execution.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no conflicting dependencies)
- **[US1/US2/US3]**: Maps to user story from spec.md
- **[TEST]**: Pre-production Playwright testing cycle task

---

## Phase 1: Setup (Environment Verification)

**Purpose**: Confirm the dev environment is fully operational before any code changes.

- [x] T001 Verify Laravel dev server is running on `http://localhost:8000` — run `php artisan serve` if not, confirm homepage loads
- [x] T002 [P] Verify queue worker is running (`php artisan queue:work`) — required for pipeline processing
- [x] T003 [P] Verify Docker/Redis is running (`docker-compose up -d`) — required for SSE notifications

**Checkpoint**: Dev server, queue worker, and Redis all running. App is accessible at localhost:8000.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Understand the exact integration points in existing code before any modification.

**⚠️ CRITICAL**: Read these files before modifying anything.

- [x] T004 Read `resources/views/components/agent-timeline-live.blade.php` — locate the exact `activatePdfExportButton()` call inside the `case.status_changed` SSE handler (search for `activatePdfExportButton`) and note its line number
- [x] T005 Read `resources/views/components/pdf-export-button.blade.php` — understand the full component structure (enabled button, disabled span, `handlePdfExport` JS function) before repurposing it

**Checkpoint**: Both integration points fully understood. Ready to implement user stories.

---

## Phase 3: User Story 1 — View Case Analysis in Formatted Modal (Priority: P1) 🎯 MVP

**Goal**: Replace the PDF button with an "عرض النتائج" button; create the RTL formatted output modal that renders Markdown pipeline output using Cairo font; auto-opens when the pipeline completes via SSE.

**Independent Test**: Complete any finished case → modal auto-opens with Markdown-rendered Arabic text, Cairo font, RTL direction, structured headers and bullet lists visible.

### Implementation for User Story 1

- [x] T006 [US1] Create `resources/views/components/case-output-modal.blade.php` — full modal overlay component with: `id="caseOutputModal"` hidden by default, `fixed inset-0 z-50` overlay, dark semi-transparent backdrop, inner container `max-w-4xl mx-auto bg-white rounded-2xl` with `max-h-[90vh] overflow-y-auto`, `dir="rtl"`, header with title "نتائج التحليل القانوني" and close button `×` (`id="outputModalCloseBtn"`), content area `id="outputModalContent"` with Cairo font and proper prose styling, empty-state message "لا توجد نتائج متاحة", `marked.js` CDN script tag (`https://cdn.jsdelivr.net/npm/marked/marked.min.js`), and global JS functions `openOutputModal()`, `closeOutputModal()`, `activateOutputButton()` as defined in `contracts/ui-functions.md`

- [x] T007 [P] [US1] Modify `resources/views/components/pdf-export-button.blade.php` — replace PDF export logic with output modal button: change icon from `picture_as_pdf` to `article`, change label from `تصدير PDF` to `عرض النتائج`, change disabled label from `تصدير PDF (غير متاح)` to `عرض النتائج (غير متاح)`, replace `onclick="handlePdfExport(this, '...')"` with `onclick="openOutputModal()"`, add `id="outputModalBtnEl"` to the enabled button, remove the entire `handlePdfExport` JS function and the PDF fetch logic

- [x] T008 [P] [US1] Modify `resources/views/components/agent-timeline-live.blade.php` — find the `activatePdfExportButton()` call inside the SSE `case.status_changed` handler (condition: `data.status === 'phase3_completed' || data.status === 'completed_with_warnings'`) and replace it with: `if (typeof activateOutputButton === 'function') activateOutputButton(); if (typeof openOutputModal === 'function') openOutputModal();` — preserve all other surrounding logic unchanged

- [x] T009 [US1] Modify `resources/views/pages/cases/show.blade.php` — add `@include('components.case-output-modal', ['case' => $case])` immediately before the closing `@endsection` (after the existing `@push('scripts')` block, or just before it); verify the existing `@include('components.pdf-export-button', ['case' => $case])` include is still present (the component itself is now repurposed)

- [x] T010 [US1] Modify `app/Http/Controllers/CaseController.php` — in the `pdf()` method, replace the PDF generation logic with a redirect: `return redirect()->route('cases.show', $case)->with('info', 'تم استبدال تصدير PDF بعرض النتائج المنسقة');` — this prevents 404 errors from any cached PDF button links

- [ ] T011 [US1] Verify US1 end-to-end using Playwright MCP: navigate to any completed case (`phase3_completed` or `completed_with_warnings` status), verify "عرض النتائج" button is active and clickable, click it, verify modal opens with rendered Markdown content (check for `<h2>` or `<h3>` elements and `<ul>` or `<li>` elements inside `#outputModalContent`), take screenshot as evidence

**Checkpoint**: "عرض النتائج" button works. Modal opens with formatted Arabic Markdown output. Cairo font visible. RTL layout correct.

---

## Phase 4: User Story 2 — Navigate Long Documents Comfortably (Priority: P2)

**Goal**: Sticky close button remains visible while scrolling; Escape key closes the modal.

**Independent Test**: Open modal on a long case output → scroll to bottom → close button still visible → press Escape → modal closes.

### Implementation for User Story 2

- [x] T012 [US2] Modify `resources/views/components/case-output-modal.blade.php` — make the modal header sticky: wrap the title + close button row in a `<div class="sticky top-0 bg-white z-10 border-b border-slate-100 px-6 py-4 flex items-center justify-between">` so it stays visible during scroll; ensure the scrollable content area is the inner body div (`overflow-y-auto`) not the outer container

- [x] T013 [US2] Add Escape key listener inside the `<script>` block of `resources/views/components/case-output-modal.blade.php` — add `document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeOutputModal(); });` — place it inside a guard so it only fires when the modal is visible: `if (!document.getElementById('caseOutputModal').classList.contains('hidden'))`

- [ ] T014 [US2] Verify US2 via Playwright MCP: open modal on a long case → use `browser_evaluate` to scroll `#outputModalContent` to bottom → take screenshot confirming close button is visible → use `browser_press_key` with `Escape` → verify modal has `hidden` class → re-open modal → click `×` button → verify modal closes

**Checkpoint**: Sticky header works. Escape key closes modal. Both close paths verified via Playwright.

---

## Phase 5: Pre-Production Testing Cycle — Full Pipeline Validation (Priority: P1 parallel)

**Goal**: Run the complete sample case through the full pipeline using Playwright MCP. Validate pipeline completion, RAG retrieval, notification system, and the new output modal. Fix any failures encountered before marking tests as passed.

**Sample case path**: `D:\Work\Automize\Projects\law-laravel-project\sample case\`

**Independent Test**: All 9 agents complete, RAG law references appear in outputs, notification bell shows completion, modal displays structured Arabic output.

### T0 — Pre-flight

- [ ] T015 [TEST] Navigate to `http://localhost:8000` using Playwright MCP `browser_navigate` — take screenshot to confirm RTL layout, navigation sidebar, and Arabic UI are loaded correctly

### T1 — Login

- [ ] T016 [TEST] Navigate to `/login`, fill credentials using `browser_fill_form`, submit and verify redirect to dashboard — take screenshot of authenticated dashboard

### T2 — Create Case

- [ ] T017 [TEST] Navigate to `/cases/create`, fill the case title field with "قضية اختبار — مذكرة التعقيب والتجريح (اختبار ما قبل الإنتاج)", paste the full content of `D:\Work\Automize\Projects\law-laravel-project\sample case\intake.txt` into the intake text field, submit the form, and verify the new case show page loads with status "جديدة" — record the case ID from the URL

### T3 — Upload Documents

- [ ] T018 [TEST] Upload all 9 files from `D:\Work\Automize\Projects\law-laravel-project\sample case\documents\` to the case — for each file use `browser_file_upload` via the documents upload interface: "صحيفة الدعوى الابتدائية.txt", "مذكرة الرد الجوابي الأولى.txt", "محضر ضبط الجلسة.txt", "أولاً فهرس ملفات ومستندات القضية وملخصاتها.txt", "المستند رقم (١) مستخرج إلكتروني من نظام (ناجز).txt", "المستند رقم (٢) مستخرج رسمي من الأحوال المدنية.txt", "اللائحة التنفيذية لنظام الإجراءات الجزائية.txt", "اللوائح التنفيذية لنظام المرافعات الشرعية.txt", "نظام الإثبات.txt" — verify all 9 appear in the documents list — take screenshot

### T4 — Pipeline Execution & Monitoring

- [ ] T019 [TEST] Start the analysis pipeline (click the analysis start button on the case show page), then monitor agent cards for progression through all 9 agents — use `browser_wait_for` with a sufficient timeout to watch for status transitions — take screenshot after each phase gate (phase1 complete, awaiting_laws approval if shown, phase2 complete, phase3 complete)

- [ ] T020 [TEST] **Pipeline failure handling** — if the pipeline stops (status becomes `failed`, `halted`, or `timed_out`), immediately: (a) take screenshot of error state, (b) read the error message shown in UI, (c) read `storage/logs/laravel.log` for root cause, (d) fix the underlying code issue, (e) click "استئناف من الوكيل N" if resume is available, or "إعادة من البداية" if full retry needed, (f) re-monitor until completion — repeat until all agents complete successfully

### T5 — RAG Validation

- [ ] T021 [TEST] After pipeline completes, expand agent output cards for agents 4, 5, and 6 (or any agents that reference laws) — use `browser_evaluate` to search `document.body.innerText` for Arabic law references — verify at least one of: "نظام الإثبات", "نظام المرافعات", "نظام الإجراءات الجزائية", or specific article numbers like "المادة" appears in agent outputs — take screenshot of law reference as evidence; **if no law references found**: check `/law-library` page for embedding status, run re-embedding if needed, fix root cause

### T6 — Notification System Validation

- [ ] T022 [TEST] After pipeline completes, locate the notification bell icon in the top navbar — click it to open the notification panel — verify a completion notification is visible (text should contain "اكتملت" or similar completion message) — take screenshot of notification panel — **if no notification appears**: use `browser_evaluate` to check if the SSE EventSource for `/notifications/stream` is open (check `notificationCenter().enabled` and network tab), check Redis connection in `docker-compose ps`, fix root cause and verify notification fires on next test run

### T7 — Output Modal Validation (New Feature)

- [ ] T023 [TEST] After pipeline completes, verify the output modal auto-opened (should be visible without clicking anything) — if not auto-opened, investigate the SSE `activateOutputButton/openOutputModal` hook in `agent-timeline-live.blade.php` and fix — take screenshot of auto-opened modal showing Arabic formatted text

- [ ] T024 [TEST] Validate full modal behavior: (a) verify modal content has rendered Markdown (check for heading and list elements inside `#outputModalContent`), (b) verify RTL direction (`dir="rtl"` on modal container), (c) verify Cairo font is applied (use `browser_evaluate` to check `getComputedStyle`), (d) close modal with `×` button, (e) click "عرض النتائج" button to re-open, (f) press Escape key to close, (g) take final screenshot of case page with "عرض النتائج" button active — all steps must pass

**Checkpoint**: All 9 agents completed, RAG law references confirmed, notification fired, modal auto-opened with correct formatting, all close paths work.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Final cleanup, evidence archival, and any remaining edge-case handling.

- [x] T025 [P] Handle the `info` flash message in `resources/views/pages/cases/show.blade.php` — add a handler for `session('info')` flash alongside the existing `session('success')` and `session('error')` blocks (blue info styling: `bg-blue-50 border-blue-200 text-blue-800`)

- [ ] T026 [P] Verify empty state in `resources/views/components/case-output-modal.blade.php` — use `browser_evaluate` in Playwright to call `openOutputModal()` on a case that has no outputs yet (newly created case) — confirm "لا توجد نتائج متاحة" message appears instead of blank modal

- [ ] T027 Take final full-page screenshot of the completed test case show page — verify: status badge shows "مكتملة" or "مكتملة بتحذيرات", all agent cards show green completed state, "عرض النتائج" button is active, notification bell has unread indicator — archive screenshot as pre-production release evidence

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 — must complete before coding starts
- **US1 (Phase 3)**: Depends on Phase 2 — core feature; MVP deliverable
- **US2 (Phase 4)**: Depends on Phase 3 (T006 modal component must exist before making header sticky)
- **Testing Cycle (Phase 5)**: Depends on Phase 3 + Phase 4 being complete — tests the full feature
- **Polish (Phase 6)**: Depends on Phase 5 — fills gaps found during testing

### User Story Dependencies

- **US1 (P1)**: No dependencies on US2 — independently testable after T011
- **US2 (P2)**: Modifies the component created in US1 (T006) — sequential, not parallel
- **Testing Cycle**: Validates US1 + US2 together in a real pipeline run

### Within Phase 3 (US1)

- **T006** (create modal) and **T007** (modify PDF button) are parallel [P] — different files
- **T008** (modify timeline) and **T009** (modify show.blade.php) are parallel [P] — different files — but both depend on T006 being created first
- **T010** (stub pdf controller) is parallel [P] with T006–T009 — different layer entirely
- **T011** (verify US1) runs last — depends on T006–T010 all complete

### Within Phase 5 (Testing Cycle)

- T015–T016 (preflight + login) → T017 (create case) → T018 (upload docs) → T019 (start pipeline) → T020 (fix failures if any) → T021 (RAG) → T022 (notifications) → T023–T024 (modal validation)
- **Strictly sequential** — each test step depends on the previous

---

## Parallel Opportunities

### Phase 3 (US1) — Parallel Set 1

```
T006: Create case-output-modal.blade.php  ←─ [P] run together
T007: Modify pdf-export-button.blade.php  ←─ [P] run together
T010: Stub CaseController::pdf()          ←─ [P] run together
```

### Phase 3 (US1) — Parallel Set 2 (after T006 complete)

```
T008: Modify agent-timeline-live.blade.php  ←─ [P] run together
T009: Modify cases/show.blade.php           ←─ [P] run together
```

### Phase 6 (Polish) — Parallel

```
T025: Add info flash handler  ←─ [P] run together
T026: Test empty state        ←─ [P] run together
```

---

## Implementation Strategy

### MVP First (User Story 1 Only — T001–T011)

1. Complete Phase 1: Environment verification (T001–T003)
2. Complete Phase 2: Read integration points (T004–T005)
3. Complete Phase 3: Build and verify the modal (T006–T011)
4. **STOP and VALIDATE**: Click "عرض النتائج" on any completed case — modal should show formatted output
5. MVP is shippable at this point

### Full Delivery (All Phases)

1. MVP (above) → US2 navigation polish (T012–T014) → Pre-production test cycle (T015–T024) → Polish (T025–T027)
2. Each phase adds value without breaking previous phases

---

## Notes

- No automated test files are created — all validation is via Playwright MCP live browser interaction
- If the pipeline fails during T019–T020, the fix happens in the same session before proceeding
- The sample case documents include 3 Saudi law texts that seed RAG retrieval validation
- `marked.js` renders Markdown to HTML client-side — no server-side dependencies added
- Cairo font is already globally loaded — no font changes needed anywhere
- All UI is system-wide RTL — `dir="rtl"` on modal container inherits from existing body styles
