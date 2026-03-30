---

description: "Task list for Dynamic UI Values feature implementation"
---

# Tasks: Dynamic UI Values

**Input**: Design documents from `/specs/006-dynamic-ui-values/`
**Prerequisites**: plan.md, spec.md, data-model.md, quickstart.md

**Tests**: Manual testing with Playwright - each UI component must be verified after implementation (Principle III)

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2)
- Include exact file paths in descriptions

---

## Phase 1: API Infrastructure (Required for All User Stories)

**Purpose**: Add API endpoints that all user stories depend on

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T001 [P] Add `GET /api/cases/{id}/progress` endpoint in `app/Http/Controllers/Api/CaseController.php` - returns case progress, stage states, document/fact/law counts
- [ ] T002 [P] Add `POST /api/cases/{id}/pause` endpoint in `app/Http/Controllers/Api/CaseController.php` - pauses case processing
- [ ] T003 [P] Add `POST /api/cases/{id}/resume` endpoint in `app/Http/Controllers/Api/CaseController.php` - resumes case processing
- [ ] T004 Add `GET /api/dashboard/stats` endpoint in `app/Http/Controllers/Api/DashboardController.php` or create new controller - returns real statistics (active_cases, completed, monthly data)
- [ ] T005 [P] Register new API routes in `routes/api.php`

**Checkpoint**: API endpoints ready - user story implementation can now begin in parallel

---

## Phase 2: User Story 1 - Trustworthy Case Progress Display (Priority: P1) 🎯 MVP

**Goal**: AI Analysis page shows real case progress from database instead of hardcoded 65%

**Independent Test**: Create a case, visit AI Analysis page, verify progress reflects actual case.progress_percentage

### Implementation for User Story 1

- [ ] T006 [P] [US1] Update `app/Http/Controllers/CaseController.php` - add `showAnalysis()` method that loads case with agentExecutions
- [ ] T007 [P] [US1] Modify `resources/views/pages/ai-analysis.blade.php` - replace hardcoded `65%` with `{{ $case->progress_percentage }}`
- [ ] T008 [US1] Update stage status indicators in `ai-analysis.blade.php` - show actual status from `AgentExecution` records (not static badges)
- [ ] T009 [US1] Add empty state handling in `ai-analysis.blade.php` - show "No data yet" when progress_percentage is null

**Checkpoint**: User Story 1 fully functional - progress display shows real data from database

---

## Phase 3: User Story 2 - Accurate Document and Fact Counts (Priority: P1) 🎯 MVP

**Goal**: AI Insights section shows accurate counts from database (not static "12 documents, 8 facts, 24 laws")

**Independent Test**: Upload documents to a case, verify AI Insights shows correct document count

### Implementation for User Story 2

- [ ] T010 [P] [US2] Update CaseController to eager load documents, caseLaws relationships for AI Analysis
- [ ] T011 [US2] Replace hardcoded document count in `ai-analysis.blade.php` with `{{ $case->documents->count() }}`
- [ ] T012 [US2] Replace hardcoded law matches count in `ai-analysis.blade.php` with `{{ $case->caseLaws->count() }}`
- [ ] T013 [US2] Calculate facts count from CaseOutput - count outputs where output_type contains "fact" or "analysis"
- [ ] T014 [US2] Add empty state for counts - show "0" or "No data yet" when relationships are empty

**Checkpoint**: User Story 2 fully functional - counts show accurate values from database

---

## Phase 4: User Story 3 - Real Operational Dashboard (Priority: P1) 🎯 MVP

**Goal**: Dashboard shows real platform activity and trends instead of static chart data

**Independent Test**: Create cases with different statuses, verify dashboard statistics match database counts

### Implementation for User Story 3

- [ ] T015 [P] [US3] Enhance `app/Http/Controllers/DashboardController.php` - query real case counts (active, completed, analyzing)
- [ ] T016 [US3] Update `resources/views/pages/dashboard.blade.php` - replace hardcoded "85%" and "15%" with calculated percentages
- [ ] T017 [US3] Replace hardcoded monthly chart data in `dashboard.blade.php` - query LegalCase for last 6 months
- [ ] T018 [US3] Ensure doughnut chart percentages sum to 100% from actual status distribution
- [ ] T019 [US3] Add empty state handling - show "0 cases" when no data exists

**Checkpoint**: User Story 3 fully functional - dashboard reflects real operational data

---

## Phase 5: User Story 4 - Working Analysis Controls (Priority: P2)

**Goal**: Pause and refresh buttons work predictably on AI Analysis page

**Independent Test**: Start case processing, click pause button, verify status changes to paused

### Implementation for User Story 4

- [ ] T020 [P] [US4] Add Alpine.js handling for pause button in `ai-analysis.blade.php` - call API endpoint on click
- [ ] T021 [P] [US4] Add Alpine.js handling for resume button in `ai-analysis.blade.php` - call API endpoint on click
- [ ] T022 [US4] Add refresh button functionality in `ai-analysis.blade.php` - reload case data via API
- [ ] T023 [US4] Add visual feedback for button states - show loading spinner while API call in progress
- [ ] T024 [US4] Add error handling - show toast notification if API call fails

**Checkpoint**: User Story 4 fully functional - controls work safely and predictably

---

## Phase 6: User Story 5 - Case Detail Timeline Accuracy (Priority: P2)

**Goal**: Case detail page shows accurate execution history based on AgentExecution records

**Independent Test**: Process case through multiple phases, verify timeline shows correct stage completion

### Implementation for User Story 5

- [ ] T025 [P] [US5] Update `resources/views/pages/cases/show.blade.php` - load AgentExecution records for timeline
- [ ] T026 [US5] Replace static timeline stages with dynamic data - show completed/running/pending based on actual agent status
- [ ] T027 [US5] Add failure state handling - show error indicator when agent status is "failed"
- [ ] T028 [US5] Display actual timestamps for completed stages from AgentExecution.completed_at

**Checkpoint**: User Story 5 fully functional - timeline accurately reflects execution history

---

## Phase 7: User Story 6 - Dynamic Agent Progress (Priority: P2)

**Goal**: Dashboard agent progress section shows real execution state (not hardcoded 92%, 45%, 12%)

**Independent Test**: Start multiple agents, verify progress bars reflect actual AgentExecution.progress_percentage

### Implementation for User Story 6

- [ ] T029 [P] [US6] Update DashboardController to query AgentExecution for agent progress data
- [ ] T030 [US6] Replace hardcoded agent progress values in `dashboard.blade.php` with dynamic data
- [ ] T031 [US6] Add status indicators for blocked/waiting agents in dashboard
- [ ] T032 [US6] Show 100% with completion status for finished agents

**Checkpoint**: User Story 6 fully functional - agent progress shows real execution state

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [ ] T033 [P] Run Playwright tests to verify all dynamic components work (Principle III)
- [ ] T034 Add Cache-Control: no-store headers to API responses (Principle II - Zero-Cache)
- [ ] T035 [P] Update CLAUDE.md with new API endpoints added
- [ ] T036 Verify all Arabic labels display correctly (Principle IV)
- [ ] T037 Run quickstart.md validation - ensure all pages load without errors

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (API Infrastructure)**: No dependencies - can start immediately. BLOCKS all user stories.
- **Phase 2-4 (P1 User Stories)**: Depend on Phase 1. Can proceed in parallel after Phase 1 complete.
- **Phase 5-7 (P2 User Stories)**: Depend on Phase 1. Can proceed in parallel or sequential.
- **Phase 8 (Polish)**: Depends on all user stories being complete.

### User Story Dependencies

- **User Story 1 (P1)**: Requires API endpoint T001. No other story dependencies.
- **User Story 2 (P1)**: Requires API endpoint T001. Can share CaseController updates with US1.
- **User Story 3 (P1)**: Requires API endpoint T004. Independent from US1/US2.
- **User Story 4 (P2)**: Requires API endpoints T002, T003. Depends on US1 (uses same page).
- **User Story 5 (P2)**: Independent - different page (case detail).
- **User Story 6 (P2)**: Requires AgentExecution queries - can share with US3 dashboard updates.

### Parallel Opportunities

- T001, T002, T003, T004, T005 (Phase 1) - can all run in parallel after initial setup
- T006, T007 (US1) - can run in parallel (controller + view)
- T010, T011, T012, T013 (US2) - can run in parallel
- T015, T016, T017 (US3) - can run in parallel
- T020, T021 (US4) - can run in parallel (Alpine.js handlers)
- US1, US2, US3 can be worked on in parallel by different developers

---

## Implementation Strategy

### MVP First (User Stories 1, 2, 3 Only)

1. Complete Phase 1: API Infrastructure
2. Complete Phase 2: User Story 1 (Trustworthy Case Progress)
3. **STOP and VALIDATE**: Test US1 independently with Playwright
4. Complete Phase 3: User Story 2 (Accurate Counts)
5. **STOP and VALIDATE**: Test US2 independently
6. Complete Phase 4: User Story 3 (Real Dashboard)
7. **STOP and VALIDATE**: Deploy/demo MVP with first 3 stories

### Incremental Delivery

1. Phase 1 → Foundation ready
2. Add US1 → Test → Deploy (MVP - core progress display)
3. Add US2 → Test → Deploy (accurate counts)
4. Add US3 → Test → Deploy (real dashboard)
5. Add US4 → Test → Deploy (working controls)
6. Add US5 → Test → Deploy (timeline accuracy)
7. Add US6 → Test → Deploy (agent progress)

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Manual Playwright testing required after each component change (Principle III)
- Commit after each task or logical group
- Avoid: vague tasks, same file conflicts, cross-story dependencies that break independence
- All API responses must use Cache-Control: no-store (Principle II)
- Use existing SSE infrastructure for real-time updates (Principle I)