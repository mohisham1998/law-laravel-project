# Tasks: Case Output Page Redesign

**Input**: Design documents from `/specs/002-case-output-redesign/`
**Branch**: `002-case-output-redesign`
**Stack**: PHP 8.x / Laravel 11 · Blade · Tailwind CSS · Vanilla JS (no new deps)
**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no shared state)
- **[Story]**: Which user story this implements (US1–US5)
- Exact file paths included in every task description

---

## Phase 1: Setup

**Purpose**: Clear Blade view cache and confirm dev environment is ready before editing any view files.

- [x] T001 Clear compiled Blade view cache by running `php artisan view:clear` in the Docker container (`docker exec law-laravel-project-app-1 php artisan view:clear`)
- [x] T002 Confirm a test case in `phase2_processing` or `phase3_completed` status exists and record its UUID for browser verification steps

**Checkpoint**: Environment ready — view edits will be picked up immediately.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Create the new `pipeline-tracker` Blade component that all visual improvements depend on. Must complete before US1 work begins.

- [x] T003 Create new file `resources/views/components/pipeline-tracker.blade.php` with PHP block computing `$definitions = AgentDefinitions::all()` and `$executionsByAgent` keyed collection from `$case->agentExecutions`, serialized to JS via `@json()`
- [x] T004 Implement the 3-phase icon grid HTML in `resources/views/components/pipeline-tracker.blade.php`: one labelled section per phase (المرحلة الأولى / الثانية / الثالثة), each containing a `flex flex-wrap gap-2 overflow-x-auto` row of agent bubbles; each bubble shows agent number + short Arabic name + a status icon `<span>` with `id="tracker-bubble-{N}"`
- [x] T005 Implement the overall progress bar in `resources/views/components/pipeline-tracker.blade.php` below the phase grid: `<div id="trackerProgressBar">` with width initialized from the PHP-computed completed count, and a `<span id="trackerProgressLabel">` showing `X / 13 مكتمل`
- [x] T006 Implement `initTracker()` JS function in `resources/views/components/pipeline-tracker.blade.php` that reads `executionsByAgent` and calls `updateTrackerBubble(num, status)` for each agent during `DOMContentLoaded`; `updateTrackerBubble` maps status → icon + color classes (completed=green checkmark, in_progress=amber pulse, failed=red error, pending=grey schedule)
- [x] T007 Implement SSE event listeners in `resources/views/components/pipeline-tracker.blade.php`: `window.addEventListener('sse:agent.started', ...)` → `updateTrackerBubble(n, 'in_progress')`, `window.addEventListener('sse:agent.completed', ...)` → `updateTrackerBubble(n, 'completed')` + recalculate progress bar, `window.addEventListener('sse:agent.failed', ...)` → `updateTrackerBubble(n, 'failed')`; no new `EventSource` opened

**Checkpoint**: `pipeline-tracker` component self-contained and renderable — proceed to US story phases.

---

## Phase 3: US1 — At-a-Glance Pipeline Progress (Priority: P1) 🎯 MVP

**Goal**: A full-width pipeline tracker showing all 13 agents across 3 phases is visible above the content grid without scrolling, updates in real time via SSE.

**Independent Test**: Open a case in `phase2_processing`. Without scrolling, identify current running agent, completed count, and active phase within 5 seconds. Transition the agent — tracker updates without page refresh.

- [x] T008 [US1] Include the new pipeline tracker in `resources/views/pages/cases/show.blade.php` as a full-width block above the `<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">` grid: `@include('components.pipeline-tracker', ['case' => $case, 'statusVal' => $statusVal])`
- [x] T009 [US1] Verify browser: open the test case — pipeline tracker renders above the main grid with all 13 agent bubbles grouped into 3 phase sections; no JS console errors; progress bar shows correct percentage

---

## Phase 4: US2 — Prominent Phase Gate Actions (Priority: P2)

**Goal**: On a `phase2_completed` case the Phase 3 start banner is the first call-to-action visible below the pipeline tracker, above the agent cards list.

**Independent Test**: Open a `phase2_completed` case. Without scrolling, the Phase 3 judicial arbitration banner is clearly visible with a primary action button. Click it — Phase 3 starts and the banner disappears.

- [x] T010 [US2] In `resources/views/pages/cases/show.blade.php`, move the Phase 3 gate `@if($statusVal === 'phase2_completed') ... @endif` block to appear immediately after the `@include('components.pipeline-tracker')` line and before the `lg:grid-cols-3` grid opening tag
- [x] T011 [US2] Restyle the Phase 3 gate block in `resources/views/pages/cases/show.blade.php` to use a full-width prominent banner: replace the `bg-white` card with a `bg-gradient-to-r from-indigo-50 to-indigo-100 border-2 border-indigo-300` full-width card with the start button using `bg-indigo-600` and a larger `px-6 py-3` size; add `w-full` to the card wrapper
- [x] T012 [P] [US2] Verify browser on `phase2_completed` case: banner is visible without scrolling; clicking "بدء التحكيم القضائي" submits the form and redirects with Phase 3 pipeline starting

---

## Phase 5: US3 — Single Consolidated Live Output (Priority: P3)

**Goal**: Exactly one live streaming output area exists during active processing (inline in the active agent card). The duplicate dark terminal panel is removed. Completed agent outputs restore from DB on load, formatted, with no raw markdown.

**Independent Test**: During active processing count live-output streaming areas — must be exactly 1. Expand a completed agent card — formatted Arabic text, no `**`, no `\n`, no raw markdown symbols.

- [x] T013 [US3] In `resources/views/pages/cases/show.blade.php`, remove the `@include('components.agent-output-panel', ...)` line (line 122); keep the component file itself — do not delete it
- [x] T014 [US3] In `resources/views/pages/cases/show.blade.php`, remove the `@include('components.output-chain', ['case' => $case])` line (line 123); the pipeline tracker supersedes this component
- [x] T015 [P] [US3] In `resources/views/components/agent-timeline-live.blade.php`, audit `initializeFromDB()` function: ensure it does NOT set the `open` attribute or call `scrollIntoView()` on any agent card on page load for completed cases — only the currently in-progress agent (if any) should auto-expand
- [x] T016 [P] [US3] Verify browser: open a completed case — no dark terminal panel visible; no output-chain accordion visible; expand any agent card — formatted HTML output renders (headings styled, bold text bold, paragraphs separated); open an in-progress case — exactly one streaming area visible (inside the active agent card)

---

## Phase 6: US4 — PDF Export Prominently Surfaced (Priority: P4)

**Goal**: On completed cases the PDF export button is visible in the sidebar without scrolling. Clicking it shows a spinner while the browser awaits the PDF download.

**Independent Test**: On a `phase3_completed` case, PDF export button is visible in the viewport on page load. Click it — button disables and shows spinner; PDF download starts within a few seconds; button re-enables.

- [x] T017 [US4] Update `resources/views/components/pdf-export-button.blade.php`: change the `<a href="...">` element to a `<button type="button">` element; add an `onclick="handlePdfExport(this, '{{ route('cases.pdf', $case) }}')"` attribute; keep all existing Tailwind classes; keep the disabled/greyed variant for non-completed statuses
- [x] T018 [US4] Add `handlePdfExport(btn, url)` JS function inline in `resources/views/components/pdf-export-button.blade.php`: on click, set `btn.disabled = true` and replace innerHTML with spinner icon + "جارٍ التحضير..."; call `window.open(url, '_blank')`; after 3000ms `setTimeout`, restore `btn.disabled = false` and original innerHTML
- [x] T019 [US4] In `resources/views/pages/cases/show.blade.php`, move the `@include('components.pdf-export-button', ['case' => $case])` include into the **sidebar Quick Actions card** as the first item (before the timeline link), so it is visible without scrolling on desktop viewports
- [x] T020 [P] [US4] Verify browser on `phase3_completed` case: PDF button visible in sidebar on page load; click triggers spinner; PDF opens in new tab / download starts; button re-enables after ~3s

---

## Phase 7: US5 — No Navbar/Content Overlap (Priority: P5)

**Goal**: On all viewport widths 375px–1920px, no content is obscured by the fixed navigation bar. The case title and breadcrumb are fully visible below the header with at least 8px clearance.

**Independent Test**: At 375px, 768px, 1280px, and 1440px viewport widths, scroll to the top of the case page — case title and breadcrumb fully visible, no overlap with the sticky header.

- [x] T021 [US5] In `resources/views/layouts/app.blade.php`, confirm that the main content wrapper `<div class="p-8 flex-1">` provides adequate top padding on all viewports; update to `class="p-6 sm:p-8 flex-1"` to ensure minimum 24px breathing room on mobile without changing desktop layout
- [x] T022 [P] [US5] Verify browser at 375px viewport width: scroll to top of case show page — breadcrumb ("العودة") and case title are fully visible below the sticky header with no overlap; repeat at 768px and 1280px

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Final validation across all stories, mobile responsiveness, and SSE reconnection.

- [x] T023 In `resources/views/components/pipeline-tracker.blade.php`, add `overflow-x-auto` to each phase row container so on 375px mobile the agent bubbles scroll horizontally rather than wrapping into multiple rows that break the layout
- [x] T024 [P] Verify full SSE reconnect scenario: open a processing case, simulate disconnect by pausing the Docker container briefly (`docker pause`/`docker unpause`), confirm tracker resumes updating after reconnect without page refresh
- [x] T025 [P] Verify edge case: open a brand-new `pending` case (no executions yet) — pipeline tracker renders all 13 bubbles as grey pending without any JS errors
- [x] T026 [P] Verify edge case: open a `phase2_completed` case — only agents 0–9 show completed/in-progress bubbles; agents 10–12 show as pending; no JS errors about undefined agent executions
- [x] T027 Run `php artisan view:clear` one final time and perform a full walkthrough: pipeline tracker ✓, phase gate banner ✓, single streaming area ✓, PDF button in sidebar ✓, no navbar overlap ✓, all cards collapsed on completed case ✓

---

## Dependency Graph

```
T001 → T002                           (Setup)
           ↓
T003 → T004 → T005 → T006 → T007     (Foundational: pipeline-tracker component)
                              ↓
T008 → T009                           (US1: tracker in page)
T010 → T011 → T012                    (US2: phase gate; can start after T009)
T013 → T014                           (US3a: remove duplicates; can start after T009)
T015 → T016                           (US3b: collapsed default; parallel with T013-T014)
T017 → T018 → T019 → T020            (US4: PDF export; can start after T009)
T021 → T022                           (US5: navbar; independent of US1-US4)
T023 → T024 → T025 → T026 → T027     (Polish: after all story phases)
```

**Parallelizable groups** (after T007 completes):
- US1 (T008-T009) must complete before US2, US3, US4
- US2 (T010-T012), US3 (T013-T016), US4 (T017-T020) can run in parallel after US1
- US5 (T021-T022) is fully independent — can run at any time after T001

---

## Implementation Strategy

**MVP** (deliver US1 first — independent value): T001–T009
- Delivers the pipeline tracker visible above the grid, updating in real time
- Can be verified and used immediately; all other stories build on top

**Increment 2**: US2 + US3 in parallel (T010–T016)
- Phase gate banner repositioned + duplicate terminal removed

**Increment 3**: US4 + US5 in parallel (T017–T022)
- PDF export in sidebar + navbar overlap fix

**Increment 4**: Polish (T023–T027)
- Mobile scroll, edge cases, final walkthrough

---

## Summary

| Metric | Value |
|--------|-------|
| Total tasks | 27 |
| Setup | 2 (T001–T002) |
| Foundational | 5 (T003–T007) |
| US1 — Pipeline Tracker | 2 (T008–T009) |
| US2 — Phase Gate Banner | 3 (T010–T012) |
| US3 — Consolidated Output | 4 (T013–T016) |
| US4 — PDF Export | 4 (T017–T020) |
| US5 — Navbar Overlap | 2 (T021–T022) |
| Polish | 5 (T023–T027) |
| Parallelizable [P] tasks | 11 |
| Files modified | 4 existing + 1 new |
| New backend files | 0 |
| New dependencies | 0 |
