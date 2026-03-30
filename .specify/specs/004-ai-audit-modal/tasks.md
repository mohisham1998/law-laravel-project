# Tasks: AI-Powered Input Auditing Modal

**Input**: Design documents from `/specs/004-ai-audit-modal/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Not requested. No test tasks included.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Configuration and routing additions needed before any feature work

- [x] T001 [P] Add audit configuration entries (`audit_passing_threshold`, `audit_soft_timeout_seconds`, `audit_hard_timeout_seconds`) to `config/legal.php` with env() defaults per research R4
- [x] T002 [P] Add audit routes (`POST /cases/{case}/audit` and `POST /cases/{case}/audit/upload`) to `routes/web.php` pointing to `CaseController::audit` and `CaseController::uploadAuditFile`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Backend service and controller endpoints that ALL user stories depend on

**CRITICAL**: No modal UI work can begin until the audit endpoint is functional

- [x] T003 Create `InputAuditService` in `app/Services/InputAuditService.php` — build audit prompt (Arabic output, task type, requirements, case inputs including intake_text, document metadata, required laws, Phase 1 output, and any inline_inputs), call `OpenRouterService::complete()`, parse JSON from markdown code block via regex, validate response structure (score, projected_score, summary, feedback tiers), enforce scoring caps (required missing → max 60, recommended missing → max 85, all addressed → 86-100), return structured array per contracts/audit-api.md
- [x] T004 Add `audit()` method to `app/Http/Controllers/CaseController.php` — validate case status is `awaiting_laws`, accept optional `inline_inputs` JSON body, call `InputAuditService`, include `passing_threshold` from config in response, return JSON with `Cache-Control: no-store` header, catch exceptions and return `{ success: false }` with 500 status
- [x] T005 [P] Add `uploadAuditFile()` method to `app/Http/Controllers/CaseController.php` — validate file MIME type and size (reuse existing validation from `store()`), store file to `cases/{case_id}/` on local disk, create `CaseDocument` record, return JSON with document id, filename, mime_type, file_size per contracts/audit-api.md

**Checkpoint**: Audit endpoint callable via curl/Postman — returns structured JSON score and feedback for any `awaiting_laws` case

---

## Phase 3: User Story 5 + User Story 1 — Backward-Compatible Modal with Score Bar (Priority: P1) MVP

**Goal**: Replace the existing modal with a new structure that preserves all current behavior AND adds the AI audit score bar with loading/timeout/fallback states

**Independent Test**: Open any case in `awaiting_laws` status → modal opens automatically with skeleton loading → score bar animates with current + projected scores → all existing buttons (Approve, Request Changes, Cancel) still work → close via backdrop works

### Implementation

- [ ] T006 [US5] Rewrite modal structure in `resources/views/components/phase2-approval-modal.blade.php` — preserve: `@if` trigger on `awaiting_laws` status, case summary section (title + intake_text), required laws listing, Phase 2 agents explanation grid, action buttons area (approve form posting to `cases.start-phase2`, request changes form posting to `cases.request-changes`, missing info form posting to `cases.update-missing-info`), `closeApprovalModal()` function, backdrop click dismiss, cancel redirect, CSS fade-in animation. Add new structural sections: audit score bar area (empty, placeholder), feedback panel area (empty, placeholder), inline inputs area (empty, placeholder). Inject `data-passing-threshold="{{ config('legal.audit_passing_threshold', 70) }}"`, `data-soft-timeout="{{ config('legal.audit_soft_timeout_seconds', 10) }}"`, `data-hard-timeout="{{ config('legal.audit_hard_timeout_seconds', 30) }}"`, `data-audit-url="{{ route('cases.audit', $case) }}"`, and CSRF token as data attributes on the modal root element
- [ ] T007 [US1] Implement JavaScript state manager and initial audit call in `resources/views/components/phase2-approval-modal.blade.php` — create state object (phase, score, projectedScore, summary, feedback, inlineInputs, passingThreshold, abortController, debounceTimer), read config from data attributes, fire `fetch()` to audit URL on modal display with `AbortController`, set phase to `loading`, render skeleton placeholders (animated pulse CSS) on score bar and feedback areas while case summary remains visible
- [ ] T008 [US1] Implement two-phase timeout handling in `resources/views/components/phase2-approval-modal.blade.php` — start 10s `setTimeout` on audit call: on fire, transition skeleton to "still analyzing" indicator text (جاري التحليل...) and enable Proceed Anyway button; start 30s `setTimeout`: on fire, call fallback handler; clear both timers when audit response arrives or modal closes
- [ ] T009 [US1] Implement dual-state progress bar in `resources/views/components/phase2-approval-modal.blade.php` — render current score as solid fill bar (brand primary `#006b34`), projected score as lighter-opacity ghost extension beyond current fill, both percentage labels positioned at their respective fill edges, directional arrow (→) between the two labels, CSS transition (`transition: width 0.8s ease-out`) for animation on initial load and every re-render, RTL-aware layout (bar fills right-to-left)
- [ ] T010 [US1] Implement graceful degradation in `resources/views/components/phase2-approval-modal.blade.php` — on fetch error, JSON parse failure, or 30s hard timeout: hide score bar and feedback panel areas, show neutral Arabic fallback message ("التدقيق غير متاح — يمكنك المتابعة بشكل طبيعي"), enable standard Proceed button (no score context), abort any pending AbortController

**Checkpoint**: Modal opens with skeleton → score bar animates → existing Approve/Cancel/Request Changes all work. On API failure, fallback mode works. US1 and US5 acceptance scenarios pass.

---

## Phase 4: User Story 2 — Tiered Feedback Display (Priority: P1)

**Goal**: Render the AI's tiered feedback items (required/recommended/optional) with summary text, grouped by severity with color coding

**Independent Test**: After audit loads, verify summary text appears above feedback; required items render in red, recommended in amber, optional in green; empty tiers are hidden

### Implementation

- [x] T011 [US2] Implement summary assessment section in `resources/views/components/phase2-approval-modal.blade.php` — render `state.summary` text in a styled paragraph above the feedback tiers, use muted text color, 2-3 sentence display, hide section entirely if summary is null or empty
- [x] T012 [US2] Implement tiered feedback panel in `resources/views/components/phase2-approval-modal.blade.php` — render three collapsible sections: required (red-600 left border + red-50 background + "مطلوب" header), recommended (amber-600 left border + amber-50 background + "موصى به" header), optional (green-600 left border + green-50 background + "اختياري" header); each item shows label (bold) and reason (regular text) inline; hide entire tier section if its array is empty; add function `renderFeedback(data)` that clears and rebuilds the feedback DOM from state

**Checkpoint**: Audit response renders summary + 3-tier feedback. Empty tiers hidden. Colors correct. US2 acceptance scenarios pass.

---

## Phase 5: User Story 3 — Inline Resolution (Priority: P2)

**Goal**: Allow the user to address feedback items directly within the modal via inline inputs, with re-audit on change

**Independent Test**: Fill a text input for a flagged item → wait 800ms → score bar re-animates with updated score; upload a file → score updates; select an option → score updates

### Implementation

- [x] T013 [US3] Implement inline text input renderer in `resources/views/components/phase2-approval-modal.blade.php` — for each feedback item with `input_type: "text"`, render a text input (or textarea for longer content) below the item's label+reason, styled per existing form patterns (bg-background-light, focus:ring-2 focus:ring-primary), bind `input` event to debounced re-audit function, store value in `state.inlineInputs.text[label]`
- [x] T014 [US3] Implement inline file upload renderer in `resources/views/components/phase2-approval-modal.blade.php` — for each feedback item with `input_type: "file"`, render a file input with upload button below the item, on file selection: POST to `audit/upload` endpoint via fetch, on success: store returned document ID in `state.inlineInputs.files[]` and show filename badge, on failure: show inline error text (red), trigger debounced re-audit only on successful upload
- [x] T015 [US3] Implement inline selection input renderer in `resources/views/components/phase2-approval-modal.blade.php` — for each feedback item with `input_type: "selection"`, render a `<select>` dropdown populated with the item's `options` array (value + label), styled per existing select patterns (appearance-none, RTL arrow), bind `change` event to debounced re-audit function, store selected value in `state.inlineInputs.selections[label]`
- [x] T016 [US3] Implement 800ms debounce and re-audit cycle in `resources/views/components/phase2-approval-modal.blade.php` — create `debounceAudit()` function: clear existing debounce timer, set new 800ms timeout, on fire: abort any in-flight audit request via AbortController, fire new `fetch()` to audit URL with `inline_inputs` payload from state, on response: update state (score, projectedScore, summary, feedback), re-render score bar (with CSS transition animation) and feedback panel (preserving inline input values the user has already entered), reset timeout timers

**Checkpoint**: Inline text/file/selection inputs render per feedback item type. Editing triggers re-audit after 800ms. Score bar and feedback update. US3 acceptance scenarios pass.

---

## Phase 6: User Story 4 — Adaptive Proceed Action (Priority: P2)

**Goal**: Proceed button adapts to score state — primary "Proceed" when passing, secondary "Proceed Anyway" with warning when below threshold

**Independent Test**: Observe CTA change as score crosses threshold (e.g., fill required items to push score above 70)

### Implementation

- [x] T017 [US4] Implement adaptive CTA logic in `resources/views/components/phase2-approval-modal.blade.php` — create `updateCTA()` function called after every state change: if phase is `loading` → disable proceed button; if phase is `soft-timeout` → show "المتابعة على أي حال" (Proceed Anyway) as secondary button enabled; if phase is `fallback` → show standard "موافقة والمتابعة" (Proceed) as primary button; if score >= passingThreshold → show "موافقة والمتابعة" (Proceed) as primary green button; if score < passingThreshold → show "المتابعة على أي حال" (Proceed Anyway) as secondary outlined button with inline warning text "قد تتأثر جودة المخرجات" (Output quality may be affected); both Proceed and Proceed Anyway submit to the same `cases.start-phase2` form
- [x] T018 [US4] Implement inline input persistence on Proceed in `resources/views/components/phase2-approval-modal.blade.php` and `app/Http/Controllers/CaseController.php` — before the `start-phase2` form submits: collect all `state.inlineInputs.text` values, concatenate with separator ("--- معلومات إضافية من التدقيق ---"), POST to `cases.update-missing-info` endpoint via fetch (reusing existing pattern), then on success submit the `start-phase2` form; file uploads are already persisted as CaseDocuments (from T014); selection values are appended as text to intake; if no inline inputs exist, submit directly without the extra call

**Checkpoint**: CTA adapts to score. Inline inputs persist on Proceed. Cancel discards inline inputs (files remain as orphaned CaseDocuments — acceptable per research R3). US4 acceptance scenarios pass.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Edge case handling and final validation across all stories

- [x] T019 Handle edge cases in `resources/views/components/phase2-approval-modal.blade.php` — empty feedback list (all tiers empty): display score as 100, show summary only, hide feedback panel, show primary Proceed; minimal intake_text: audit still fires (service handles gracefully); malformed LLM JSON (regex parse fails): treat as audit failure → fallback mode; modal close during loading: abort fetch via AbortController, clear all timers; upload failure in inline file input: show error badge on that item, do not re-trigger audit
- [ ] T020 Run quickstart.md verification steps end-to-end in browser — execute all 10 verification steps from `quickstart.md`, confirm each passes, fix any issues found

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Setup (T001 for config, T002 for routes) — BLOCKS all UI work
- **US5 + US1 (Phase 3)**: Depends on Foundational (needs audit endpoint working)
- **US2 (Phase 4)**: Depends on Phase 3 (needs modal structure and audit response in state)
- **US3 (Phase 5)**: Depends on Phase 4 (needs feedback items rendered to attach inputs to)
- **US4 (Phase 6)**: Depends on Phase 3 (needs score in state); can run parallel to Phases 4-5 but benefits from Phase 5 for full CTA flow
- **Polish (Phase 7)**: Depends on all previous phases

### User Story Dependencies

- **US5 (P1)**: Foundational only — establishes modal skeleton
- **US1 (P1)**: Foundational + US5 skeleton — adds score bar to existing modal
- **US2 (P1)**: US1 — adds feedback rendering below score bar
- **US3 (P2)**: US2 — adds inline inputs to feedback items
- **US4 (P2)**: US1 — adds CTA logic based on score (can start after Phase 3)

### Within Each Phase

- T001 and T002 are parallel [P] (different files)
- T003 and T005 are parallel [P] (different files, independent logic)
- T004 depends on T003 (controller calls service)
- T006 → T007 → T008 → T009 → T010 are sequential (same file, cumulative)
- T011 → T012 sequential (same file)
- T013, T014, T015 could theoretically be parallel but are in the same file — implement sequentially
- T016 depends on T013-T015 (needs inputs to debounce)
- T017 and T018 are sequential (same file + controller)

### Parallel Opportunities

```
Phase 1:  T001 ─┬─ (parallel)
          T002 ─┘

Phase 2:  T003 ─┬─ (parallel)    T004 (after T003)
          T005 ─┘

Phases 3-7: Sequential (same primary file)
```

---

## Parallel Example: Phase 1 + Phase 2

```bash
# Phase 1 — both can run in parallel:
Task: T001 "Add audit config entries to config/legal.php"
Task: T002 "Add audit routes to routes/web.php"

# Phase 2 — T003 and T005 can run in parallel:
Task: T003 "Create InputAuditService in app/Services/InputAuditService.php"
Task: T005 "Add uploadAuditFile() to app/Http/Controllers/CaseController.php"
# Then T004 after T003 completes:
Task: T004 "Add audit() method to app/Http/Controllers/CaseController.php"
```

---

## Implementation Strategy

### MVP First (Phase 1 + 2 + 3 = US5 + US1)

1. Complete Phase 1: Setup (config + routes)
2. Complete Phase 2: Foundational (service + controller)
3. Complete Phase 3: US5 + US1 (modal skeleton + score bar)
4. **STOP and VALIDATE**: Modal opens, audit fires, score bar renders, existing flows preserved
5. This is a functional, valuable increment — the user sees AI-scored completeness

### Incremental Delivery

1. Setup + Foundational → Backend ready
2. US5 + US1 → Modal with score bar → **MVP** (testable, demoable)
3. US2 → Add feedback tiers → Informative (user sees what to improve)
4. US3 → Add inline resolution → Interactive (user can fix gaps in-place)
5. US4 → Add adaptive CTA → Complete (quality-aware proceed flow)
6. Polish → Hardened (edge cases, validation)

Each increment adds value without breaking previous work.

---

## Notes

- All modal UI tasks (T006-T020 except T018) are in the same file (`phase2-approval-modal.blade.php`) — they MUST be sequential
- T018 touches both the Blade file and `CaseController.php` — the controller change is independent but the Blade change depends on T017
- The `InputAuditService` (T003) is the most complex single task — it includes prompt engineering, JSON parsing, and scoring validation
- RTL layout is critical — score bar fills right-to-left, text alignment respects Arabic conventions
- Commit after each phase checkpoint for clean rollback boundaries
