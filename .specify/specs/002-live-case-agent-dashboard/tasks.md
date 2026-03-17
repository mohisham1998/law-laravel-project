# Tasks: Live Case Agent Dashboard

**Input**: Design documents from `.specify/specs/002-live-case-agent-dashboard/`  
**Prerequisites**: plan.md, spec.md, data-model.md

**Organization**: UI first (static data), then SKILL.md update, then integration. Tasks grouped by user story where applicable.

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1–US6 maps to spec user stories
- Include exact file paths in descriptions

---

## Phase 1: Setup

**Purpose**: Ensure structure and static data source for UI development

- [x] T001 Create AgentDefinitions service with static list of all 12 agents (number, phase, name, name_en, outputs, inputs) in app/Services/AgentDefinitions.php per data-model.md
- [x] T002 [P] Create migration for case_metrics table (case_id, total_duration_seconds, total_tokens, statutes_matched, average_confidence, corrections_count, items_for_review) in database/migrations/
- [x] T003 [P] Create CaseMetrics model with fillable and LegalCase relationship in app/Models/CaseMetrics.php

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Data and definitions that UI components and backend depend on

**Checkpoint**: After this phase, UI components can be built using AgentDefinitions and case agent executions

- [x] T004 Run migration for case_metrics and register CaseMetrics in LegalCase as hasOne in app/Models/LegalCase.php
- [x] T005 Ensure CaseController show action passes $case with agentExecutions and requiredLaws loaded for resources/views/pages/cases/show.blade.php

---

## Phase 3: User Story 1 & 2 – Live Agent View + Status Overview (P1) – UI with Static Data

**Goal**: Dashboard shows all 12 agents with status, step indicator, progress bar, and output handoff. Independent test: Load case show page and see agent timeline with static/simulated data (one agent "processing", others completed/pending/locked).

- [x] T006 [P] [US1] [US2] Create agent-timeline Blade component with step indicator (الخطوة X من 12), progress bar, and 12 agent rows (status: completed/processing/pending/failed/locked) using AgentDefinitions and $case->agentExecutions or static array in resources/views/components/agent-timeline.blade.php
- [x] T007 [P] [US1] [US2] Create agent-output-panel Blade component (expandable, placeholder for typewriter output, syntax hints for CASE:/LAW:) in resources/views/components/agent-output-panel.blade.php
- [x] T008 [P] [US1] [US2] Create output-chain Blade component (collapsible "سلسلة المخرجات", diagram intake → Phase 1 → Agent 1…9 → PDF) using AgentDefinitions in resources/views/components/output-chain.blade.php
- [x] T009 [US1] [US2] Replace static "مراحل التحليل الذكي" block in resources/views/pages/cases/show.blade.php with @include of agent-timeline, agent-output-panel (for active agent), and output-chain; pass $case and use sample/static agent data when agentExecutions empty so UI is visible without backend
- [x] T010 [US1] [US2] Add sample agent execution data (e.g. 3 completed, 1 processing with sample output text, rest pending, Phase 3 locked) in CaseController show or view composer so show.blade.php renders full UI for verification

**Checkpoint**: Case show page displays full agent timeline and output chain with static data; manual check that styling matches existing dashboard (primary #006b34, Cairo, RTL, rounded-xl)

---

## Phase 4: User Story 3 – PDF Export (P1)

**Goal**: User can download final brief as Arabic RTL PDF. Independent test: With a completed case, click "تصدير PDF" and receive a valid PDF; with incomplete case, button disabled and tooltip shown.

- [x] T011 [P] [US3] Create pdf-export-button Blade component (enabled only when case status is phase2_completed or phase3_completed, disabled with tooltip otherwise, loading state) in resources/views/components/pdf-export-button.blade.php
- [x] T012 [US3] Create PdfExportService that reads 09_final_brief_v2.md (or equivalent) for a case and generates Arabic RTL PDF via mPDF with Cairo font in app/Services/PdfExportService.php
- [x] T013 [US3] Add PDF export route and controller method (e.g. GET cases/{case}/pdf) that calls PdfExportService and returns file download; add pdf-export-button to resources/views/pages/cases/show.blade.php with correct enabled/disabled logic

**Checkpoint**: Completed case shows enabled PDF button; incomplete case shows disabled button with tooltip; clicking export downloads PDF

---

## Phase 5: User Story 4 – Case Insights and Metrics (P2)

**Goal**: After completion, user sees metrics panel (total time, statutes matched, confidence, corrections, items for review). Independent test: Open a completed case and see "رؤى القضية" with correct or sample metrics.

- [x] T014 [P] [US4] Create case-insights Blade component (رؤى القضية: total duration, statutes_matched, average_confidence gauge/bar, corrections_count, items_for_review list) in resources/views/components/case-insights.blade.php
- [x] T015 [US4] Include case-insights in resources/views/pages/cases/show.blade.php when status is phase2_completed or phase3_completed; pass $case->metrics or new CaseMetrics with sample data
- [x] T016 [US4] Implement CaseMetrics creation/update when Phase 2 or Phase 3 completes (aggregate from AgentExecutions, set statutes_matched from Agent 6 output if available) in job or orchestrator that finishes phase

**Checkpoint**: Completing a case shows insights panel with aggregated metrics

---

## Phase 6: User Story 5 – Settings Model Display (P2)

**Goal**: User sees which AI model is used for the case. Independent test: Case show page displays model_used for the case.

- [x] T017 [P] [US5] Display case model_used (e.g. "النموذج: claude-3.5-sonnet") in case header or agent timeline header in resources/views/pages/cases/show.blade.php or resources/views/components/agent-timeline.blade.php

**Checkpoint**: Dashboard shows model used for the case

---

## Phase 7: User Story 6 – SKILL.md Portal Integration (P2)

**Goal**: SKILL.md documents portal event contract and agent display names/outputs so dashboard and backend stay aligned. Independent test: SKILL.md contains Portal Integration section and agent definitions include Arabic names and output files.

- [x] T018 [US6] Add "Portal Integration (Dashboard Visualization)" section to .agent/skills/legal-counsel/SKILL.md with event types (agent.started, agent.output, agent.completed, agent.failed, agent.correction), event payload structure (case_id, agent_number, agent_name, event_type, content, timestamp, metrics), and portal-aware rules (emit started before processing, stream output incrementally, emit completed with metrics, emit blocked on gate failure)
- [x] T019 [US6] Ensure each agent definition in SKILL.md has Arabic display name and expected output files list so dashboard output chain matches

**Checkpoint**: SKILL.md is source of truth for dashboard event contract and agent labels

---

## Phase 8: Integration – SSE, Live Data, Pause/Retry/Abort (FR-016), Concurrent Limit (FR-017)

**Purpose**: Connect UI to real-time events and real case data; implement failure handling (pause after 3 retries, retry/abort actions) and enforce max 3 concurrent cases per user

- [x] T020 Create CaseStreamController with stream(LegalCase $case) method returning SSE response (Content-Type text/event-stream), reading from Redis list case:{id}:events and flushing events to response in app/Http/Controllers/CaseStreamController.php
- [x] T021 Add GET /cases/{case}/stream route (auth middleware) in routes/web.php
- [x] T022 Add emitEvent(string $type, array $data) to Phase2BaseAgent (or base used by Phase 2 agents) that RPUSHes JSON event to Redis key case:{case_id}:events in app/Services/Agents/Phase2/Phase2BaseAgent.php
- [x] T023 Invoke emitEvent(agent.started) at start and emitEvent(agent.completed, metrics) at end in each Phase 2 agent; emit agent.output with content chunks during output generation where feasible
- [x] T024 Emit agent.started and agent.completed (and agent.output if applicable) from Phase 1 analysis agent in app/Services/Agents/Phase1AnalysisAgent.php or equivalent
- [x] T025 Add frontend JavaScript on case show page: EventSource to /cases/{id}/stream, onmessage parse JSON and update agent status (updateAgentStatus), append output (appendOutput with typewriter effect 50–100 chars/sec), show retry on agent.failed; ensure only one SSE connection when case is in processing state in resources/views/pages/cases/show.blade.php (e.g. @push('scripts'))
- [x] T029 [FR-017] Enforce max 3 concurrent cases in processing per user: before starting Phase 1/2 (e.g. in CaseController store or job dispatcher), count user's cases in processing (phase1_running, phase2_running); if >= 3 return 422 or redirect with clear Arabic error message; document in spec edge case
- [x] T030 [FR-016] Backend: on agent failure after 3 retries, set case status to paused and stop further agent execution in orchestrator/job; ensure no further agents run until user retries or aborts
- [x] T031 [FR-016] Add web routes/actions for "retry failed agent" (e.g. POST cases/{case}/retry-agent) and "abort case" (e.g. POST cases/{case}/abort); implement in CaseController or dedicated controller; call from dashboard
- [x] T032 [FR-016] In agent-timeline or agent-output-panel, when agent status is failed, render "إعادة المحاولة" and "إلغاء" buttons and wire to retry/abort actions from T031; ensure only one SSE connection when case is in processing state

**Checkpoint**: Creating a case and running Phase 1 + Phase 2 updates the dashboard in real time via SSE; typewriter effect on output; status changes visible within 2 seconds; on failure user can retry or abort; max 3 concurrent cases per user enforced

---

## Phase 9: Polish and Validation

**Purpose**: Conformance to spec and full-cycle verification

- [ ] T026 Run manual UI test: load case show with static data, verify all 12 agents, status colors (green/amber/gray/red/locked), output chain diagram, PDF button state per spec
- [ ] T027 Run end-to-end validation per spec Implementation Validation: create case → Phase 1 → Phase 2 (all 9 agents) → verify dashboard at each step → PDF export → verify PDF Arabic RTL and content; confirm dashboard shows correct state throughout
- [ ] T028 Verify new components follow existing dashboard styling (primary #006b34, Cairo font, RTL, rounded-xl, shadow-sm) and no style regressions in resources/views/components/ and resources/views/pages/cases/show.blade.php

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: None – start immediately.
- **Phase 2 (Foundational)**: Depends on T001–T003; T004–T005 depend on Phase 1. Blocks all story implementation.
- **Phase 3 (US1+US2)**: Depends on Phase 2. Delivers UI with static data.
- **Phase 4 (US3)**: Depends on Phase 2. Can overlap with Phase 3 after T009.
- **Phase 5 (US4)**: Depends on Phase 2 and T002/T003 (CaseMetrics). Can follow Phase 3.
- **Phase 6 (US5)**: Depends on Phase 3 (show page). Single small task.
- **Phase 7 (US6)**: No code dependency; can run in parallel with Phase 4/5/6.
- **Phase 8 (Integration)**: Depends on Phase 3 (UI), Phase 7 (SKILL.md). Connects SSE, Redis, agents, frontend; includes FR-016 (pause/retry/abort) and FR-017 (max 3 concurrent cases).
- **Phase 9 (Polish)**: Depends on Phase 8 and Phase 4 (PDF), Phase 5 (insights).

### User Story Completion Order

- **US1 + US2 (P1)**: After Phase 2; implement together as Phase 3 (UI first).
- **US3 (P1)**: After Phase 2; PDF button + service in Phase 4.
- **US4 (P2)**: After Phase 2; insights component + metrics in Phase 5.
- **US5 (P2)**: After Phase 3; display model in Phase 6.
- **US6 (P2)**: SKILL.md in Phase 7; can run in parallel with 4–6.
- **Integration**: Phase 8 after UI and SKILL.md.

### Parallel Opportunities

- T002 and T003 can run in parallel (migration and model).
- T006, T007, T008 can run in parallel (three Blade components).
- T011 (PDF button) can run in parallel with T014 (case-insights).
- T017 (model display) and T018–T019 (SKILL.md) can run in parallel with each other.

---

## Implementation Strategy

### MVP First (UI with Static Data)

1. Complete Phase 1 and Phase 2.
2. Complete Phase 3 (T006–T010): full agent timeline and output chain with static data on case show.
3. **Validate**: Open a case, confirm 12 agents, step indicator, progress bar, output chain, and styling.
4. Optionally add Phase 4 (PDF button + service) for a second deliverable.

### Incremental Delivery

1. Phase 1 + 2 → foundation.
2. Phase 3 → dashboard UI (static) – demo.
3. Phase 4 → PDF export – demo.
4. Phase 5 → insights panel – demo.
5. Phase 6 + 7 → model display + SKILL.md – no UI change.
6. Phase 8 → live SSE and typewriter – full live dashboard.
7. Phase 9 → E2E and styling check – release.

### Suggested Task Order for Single Developer

T001 → T002, T003 → T004 → T005 → T006, T007, T008 → T009 → T010 → (verify UI) → T011 → T012 → T013 → T014 → T015 → T016 → T017 → T018 → T019 → T020 → T021 → T022 → T023 → T024 → T025 → T029 → T030 → T031 → T032 → T026 → T027 → T028.

---

## Notes

- [P] tasks use different files and can be parallelized.
- [USn] maps to spec user stories for traceability.
- UI is built first with static/simulated data; integration (SSE, events) comes in Phase 8.
- All new UI must follow existing dashboard styling (FR-018, FR-019).
- E2E validation (T027) is required per spec before considering the feature complete.
- FR-016 (pause, retry, abort) is covered by T030–T032; FR-017 (max 3 concurrent cases) by T029.
