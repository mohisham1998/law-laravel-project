---

description: "Task list for Pipeline Reliability & Quality Enforcement feature implementation"
---

# Tasks: Pipeline Reliability & Quality Enforcement

**Input**: Design documents from `/specs/005-pipeline-reliability/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/
**Feature Branch**: `005-pipeline-reliability`

**Tests**: Manual testing only (quickstart.md test scenarios). No automated test tasks required per spec.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Project Configuration)

**Purpose**: No new project setup needed - existing Laravel project

This is an existing Laravel 11 project. Skip to Phase 2 for foundational changes.

---

## Phase 2: Foundational (Data Layer - Blocks All User Stories)

**Purpose**: Core database schema changes and enum updates that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T001 Create migration `2026_03_24_000001_add_pipeline_reliability_fields.php` in database/migrations/ with new columns
- [ ] T002 [P] Add `Halted` and `TimedOut` values to CaseStatus enum in app/Enums/CaseStatus.php
- [ ] T003 [P] Add `Skipped` value to AgentStatus enum in app/Enums/AgentStatus.php
- [ ] T004 Update LegalCase model in app/Models/LegalCase.php - add new fields to $fillable and $casts
- [ ] T005 Update AgentExecution model in app/Models/AgentExecution.php - add new fields to $fillable and $casts
- [ ] T006 Add config keys to config/legal.php: pipeline_timeout_minutes, retry_budget_per_case, job_retries
- [ ] T007 Run migration and verify columns exist: php artisan migrate
- [ ] T008 Run php artisan cache:clear to ensure config changes are loaded

**Checkpoint**: Foundation ready - database schema supports all user stories

---

## Phase 3: User Story 1 - Pipeline Halts on Agent Failure (Priority: P1) 🎯 MVP

**Goal**: When any agent fails to produce its output, the pipeline halts immediately. Subsequent agents are prevented from executing. User receives clear notification identifying the failed agent and can retry from the failure point.

**Independent Test**: Simulate an agent failure mid-pipeline, verify pipeline stops, user receives clear failure notification, no downstream agents execute.

### Implementation for User Story 1

- [ ] T009 [P] [US1] Implement halt-on-failure logic in LegalOrchestrator.php - replace continue-on-failure with immediate halt after agent retries exhausted
- [ ] T010 [US1] Add emitPipelineHalted method to CaseEventService.php in app/Services/CaseEventService.php
- [ ] T011 [US1] Update ProcessPhase2Job.php in app/Jobs/ProcessPhase2Job.php - set pipeline_started_at before calling orchestrator
- [ ] T012 [US1] Mark subsequent agents as Skipped when pipeline halts in LegalOrchestrator.php
- [ ] T013 [US1] Update case status to Halted with halted_at_agent and halt_reason in LegalOrchestrator.php

**Checkpoint**: User Story 1 complete - pipeline halts on agent failure, user can retry from failure point

---

## Phase 4: User Story 2 - Low-Confidence Output Warnings (Priority: P2)

**Goal**: Users see visible warnings on case detail for any agent output produced below the acceptable confidence threshold after exhausting self-correction attempts.

**Independent Test**: Trigger a scenario where self-correction is exhausted and best-effort output is used, verify user sees confidence warnings in case view.

### Implementation for User Story 2

- [ ] T014 [P] [US2] Update Phase2BaseAgent.php in app/Services/Agents/Phase2/Phase2BaseAgent.php - extract and return confidence_score from self-correction validation
- [ ] T015 [US2] Persist confidence_score and below_threshold to AgentExecution in LegalOrchestrator.php
- [ ] T016 [US2] Add emitLowConfidence method to CaseEventService.php in app/Services/CaseEventService.php
- [ ] T017 [US2] Update agent.completed SSE event to include confidence_score, below_threshold, self_correction_exhausted fields
- [ ] T018 [US2] Add confidence warning to agent-timeline-live.blade.php in resources/views/components/ - show amber warning icon for below_threshold agents
- [ ] T019 [P] [US2] Replace synthetic confidence with actual data in case-insights.blade.php in resources/views/components/
- [ ] T020 [US1] [US2] Set case status to CompletedWithWarnings when any agent has below_threshold=true in LegalOrchestrator.php

**Checkpoint**: User Story 2 complete - low-confidence outputs are surfaced to user with warnings

---

## Phase 5: User Story 3 - Pipeline Execution Timeout (Priority: P3)

**Goal**: Pipeline stops gracefully when total execution time exceeds the maximum allowed duration. All successfully completed work is preserved.

**Independent Test**: Simulate slow agent responses that exceed the time limit, verify pipeline terminates gracefully with appropriate user notification.

### Implementation for User Story 3

- [ ] T021 [P] [US3] Add wall-clock timeout check before each agent starts in LegalOrchestrator.php
- [ ] T022 [US3] Implement emitTimeoutWarning method in CaseEventService.php - emit at 80% of timeout
- [ ] T023 [US3] Set case status to TimedOut with halted_at_agent when timeout exceeded in LegalOrchestrator.php
- [ ] T024 [US3] Update ProcessPhase3Job.php in app/Jobs/ProcessPhase3Job.php - apply same halt-on-failure pattern for timeout

**Checkpoint**: User Story 3 complete - pipeline respects timeout limit and halts gracefully

---

## Phase 6: User Story 4 - Unified Retry Policy (Priority: P3)

**Goal**: A consistent, predictable retry policy across all retry mechanisms. A shared budget ensures one problematic agent cannot exhaust all retry capacity.

**Independent Test**: Trigger transient failures across multiple agents, verify retry behavior follows unified policy and respects shared budget.

### Implementation for User Story 4

- [ ] T025 [P] [US4] Implement retry budget counter in LegalOrchestrator.php - track retry_budget_used on LegalCase
- [ ] T026 [US4] Check retry budget before each agent retry in LegalOrchestrator.php
- [ ] T027 [US4] Halt pipeline when retry budget exhausted with halt_reason=retry_budget_exhausted in LegalOrchestrator.php
- [ ] T028 [US4] Update ProcessPhase2Job.php - reduce $tries from 5 to 2, initialize retry_budget_max from config

**Checkpoint**: User Story 4 complete - unified retry policy with shared budget enforced

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: UI enhancements and resume/retry functionality that span multiple user stories

- [ ] T029 [P] Add status badges to cases/index.blade.php in resources/views/pages/ - Halted (red), TimedOut (orange), CompletedWithWarnings (amber)
- [ ] T030 [US1] Add halt/timeout banner to cases/show.blade.php in resources/views/pages/ - show prominent banner with retry button
- [ ] T031 [P] Update CaseController.php in app/Http/Controllers/CaseController.php - handle Halted and TimedOut statuses in retry action
- [ ] T032 Update LegalCase::canRetry() to include Halted and TimedOut statuses in app/Models/LegalCase.php
- [ ] T033 Reset retry_budget_used when case retries in LegalOrchestrator.php
- [ ] T034 Test all 4 scenarios from quickstart.md - verify manual test cases pass
- [ ] T035 Update CLAUDE.md or AGENT_PIPELINE_INVESTIGATION_REPORT.md with new pipeline behavior documentation

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - N/A (existing project)
- **Foundational (Phase 2)**: No dependencies - can start immediately - BLOCKS all user stories
- **User Stories (Phase 3-6)**: All depend on Foundational phase completion
  - User Story 1 (P1): MVP - Core halt-on-failure - can proceed first
  - User Story 2 (P2): Depends on Phase2BaseAgent changes from US1
  - User Story 3 (P3): Depends on orchestrator changes from US1
  - User Story 4 (P3): Depends on orchestrator changes from US1
  - US2, US3, US4 can run in parallel after Phase 2 completes
- **Polish (Phase 7)**: Depends on all user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 2 (P2)**: Can start after Foundational (Phase 2) - Depends on Phase2BaseAgent implementation from US1
- **User Story 3 (P3)**: Can start after Foundational (Phase 2) - Can proceed independently
- **User Story 4 (P3)**: Can start after Foundational (Phase 2) - Can proceed independently

### Within Each User Story

- Models before services
- Services before orchestrator integration
- Core implementation before UI updates
- Story complete before moving to next priority

### Parallel Opportunities

- All Foundational tasks marked [P] can run in parallel (T002, T003, T004, T005, T006)
- All User Story 1 implementation tasks can run in parallel after foundational (T009, T010, T011)
- User Story 2 implementation has parallel opportunities (T014, T015, T016, T017)
- User Story 3 and US4 can run in parallel after Phase 2
- Polish phase tasks T029 and T030 are parallelizable
- T031 and T032 are parallelizable

---

## Parallel Example: Foundational Phase

```bash
# These tasks can run in parallel (different files, no dependencies):
Task: "Add Halted and TimedOut values to CaseStatus enum in app/Enums/CaseStatus.php"
Task: "Add Skipped value to AgentStatus enum in app/Enums/AgentStatus.php"
Task: "Update LegalCase model - add new fields to $fillable and $casts in app/Models/LegalCase.php"
Task: "Update AgentExecution model - add new fields to $fillable and $casts in app/Models/AgentExecution.php"
Task: "Add config keys to config/legal.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
2. Complete Phase 3: User Story 1 (Pipeline Halt on Failure)
3. **STOP and VALIDATE**: Test US1 independently using quickstart.md Test 1
4. Deploy/demo if ready

### Incremental Delivery

1. Complete Foundational → Foundation ready
2. Add User Story 1 → Test independently → Deploy/Demo (MVP!)
3. Add User Story 2 → Test independently → Deploy/Demo
4. Add User Story 3 → Test independently → Deploy/Demo
5. Add User Story 4 → Test independently → Deploy/Demo
6. Polish phase → Final release
7. Each story adds value without breaking previous stories

### Parallel Team Strategy

With multiple developers:

1. Developer A: Complete Foundational Phase 2
2. Once Foundational is done:
   - Developer A: User Story 1 (P1 - MVP)
   - Developer B: User Story 2 (P2)
   - Developer C: User Story 3 (P3) + User Story 4 (P3)
3. Complete Polish Phase together
4. Stories complete and integrate independently

---

## Notes

- **[P]** tasks = different files, no dependencies - can run in parallel
- **[Story]** label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Manual testing only per quickstart.md - 4 test scenarios
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Avoid: vague tasks, same file conflicts, cross-story dependencies that break independence

---

## Key Files Reference

| Area | Primary Files |
|------|---------------|
| Orchestration | app/Services/Orchestration/LegalOrchestrator.php |
| Phase Jobs | app/Jobs/ProcessPhase2Job.php, app/Jobs/ProcessPhase3Job.php |
| Agent Base | app/Services/Agents/Phase2/Phase2BaseAgent.php |
| Models | app/Models/LegalCase.php, app/Models/AgentExecution.php |
| Enums | app/Enums/CaseStatus.php, app/Enums/AgentStatus.php |
| Config | config/legal.php |
| Migration | database/migrations/2026_03_24_000001_add_pipeline_reliability_fields.php |
| Events | app/Services/CaseEventService.php |
| UI Components | resources/views/components/agent-timeline-live.blade.php, resources/views/components/case-insights.blade.php |
| UI Pages | resources/views/pages/cases/index.blade.php, resources/views/pages/cases/show.blade.php |
| Controller | app/Http/Controllers/CaseController.php |

---

## Summary

- **Total Task Count**: 35
- **Foundational Tasks**: 8 (Phase 2)
- **User Story 1 (P1) Tasks**: 5 (Phase 3) - MVP
- **User Story 2 (P2) Tasks**: 7 (Phase 4)
- **User Story 3 (P3) Tasks**: 4 (Phase 5)
- **User Story 4 (P3) Tasks**: 4 (Phase 6)
- **Polish Tasks**: 7 (Phase 7)
- **Parallelizable Tasks**: 14 (marked with [P])
- **Independent Test Criteria**: Each user story can be tested independently per quickstart.md scenarios
- **Suggested MVP Scope**: User Story 1 only (Pipeline Halts on Agent Failure) - implements core reliability fix per SKILL.md contract
