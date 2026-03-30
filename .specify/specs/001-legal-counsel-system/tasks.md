# Tasks: Legal-Counsel — AI-Powered Legal Case Management System

**Input**: Design documents from `/specs/001-legal-counsel-system/`
**Prerequisites**: plan.md (required), spec.md (required), research.md, data-model.md, contracts/

**Tests**: Not explicitly requested — omitting dedicated test tasks. Manual UI verification per Constitution Principle III after each phase.

**Organization**: Tasks grouped by user story to enable independent implementation and testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Laravel monolith**: `app/`, `resources/views/`, `routes/`, `config/`, `database/`
- **Agent outputs**: `storage/app/cases/{case_id}/outputs/`
- **Error memory**: `storage/app/cases/{case_id}/memory/errors_log.md`
- **Skill file**: `.agent/skills/legal-counsel/SKILL.md`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization — skill.md, database migration, new routes, enum updates

- [x] T001 Create SKILL.md at .agent/skills/legal-counsel/SKILL.md defining all 12 agent roles, prompts, inputs, outputs, behavioral rules, and anti-hallucination instructions per Constitution Principle V
- [x] T002 Create database migration 2026_03_21_000001_add_legal_counsel_fields.php in database/migrations/ — add resume_from_agent (tinyint nullable) to cases, output_type (string default 'primary') to case_outputs, corrections_count (tinyint default 0) and correction_details (json nullable) to agent_executions, law_registry_id (bigint nullable FK) and subject_area (string nullable) to required_laws
- [x] T003 [P] Add new routes in routes/web.php — POST /cases/{case}/rerun-from (cases.rerun-from), POST /cases/{case}/start-phase3 (cases.start-phase3), ensure GET /cases/{case}/pdf (cases.pdf) is routed
- [x] T004 [P] Add new error types to ErrorLog model or CaseStatus enum in app/ — add quote_mismatch, missing_dual_citation, self_correction_exhausted error types

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story — self-correction loop, error memory, orchestrator resume, gate rework, SSE events

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T005 [P] [US18] Implement file-based error memory system — create helper methods in app/Services/CaseEventService.php (or a new ErrorMemoryService) to read/write/append storage/app/cases/{case_id}/memory/errors_log.md using the structured entry format from research.md R-005
- [x] T006 [US19] Enhance Phase2BaseAgent in app/Services/Agents/Phase2/Phase2BaseAgent.php — add self-correction loop (3 retries): after LLM output, validate for confidence < 0.70, quote mismatches, missing dual citations, abrogated articles; on failure, re-run with error context appended to prompt; emit agent.correction SSE event on each retry; pause pipeline after 3 exhausted attempts with Arabic Retry/Cancel options
- [x] T007 [P] Rework GateValidator in app/Services/Orchestration/GateValidator.php — remove per-case law upload checks, add RAG database availability check (verify law_articles table has embeddings), gate Phase 2 on RAG law identification instead of CaseLaw uploads
- [x] T008 Rework LegalOrchestrator in app/Services/Orchestration/LegalOrchestrator.php — add resume-from-agent logic (skip agents whose output files exist on disk), add re-run-from-agent-N logic (delete outputs for agents N-9, then run pipeline normally), accept $startFromAgent parameter in runPhase2()
- [x] T009 [P] Update CaseEventService in app/Services/CaseEventService.php — add agent.correction event type (payload: agent_number, attempt, violation_type, violation_detail, action), add pipeline.paused event type (payload: case_id, failed_agent, reason in Arabic, options array)
- [x] T010 [P] Add SSE reconnection with exponential backoff in resources/views/components/agent-timeline-live.blade.php — wrap EventSource in SSEConnection class with reconnect delay: Math.min(1000 * 2^attempt, 30000), reset attempt counter on successful connection

**Checkpoint**: Foundation ready — user story implementation can now begin

---

## Phase 3: User Story 1 + 2 — Case Intake & RAG Law Identification (Priority: P1) 🎯 MVP

**Goal**: Lawyer uploads case documents → Phase 1 identifies relevant laws from RAG database → shows identified laws for confirmation → "Start Phase 2" button appears

**Independent Test**: Upload intake.txt + supporting docs → verify case summary streams in real time → verify identified laws list shows official names, subject areas, and reasons from RAG DB → verify "Start Phase 2" button appears

### Implementation for User Stories 1 & 2

- [x] T011 [US1] Rework Phase1AnalysisAgent in app/Services/Agents/Phase1AnalysisAgent.php — replace any law-upload logic with RAG database queries via VectorSearchService; identify relevant laws by semantic search against case content; produce 00_required_laws.md listing each law with official name, subject area, relevance reason, and abrogation status; create RequiredLaw records with law_registry_id and subject_area fields
- [x] T012 [US1] Update ProcessPhase1Job in app/Jobs/ProcessPhase1Job.php — ensure it dispatches Phase1AnalysisAgent with RAG-based workflow; stream output via CaseEventService; set case status to awaiting_laws on completion
- [x] T013 [US2] Rework phase2-approval-modal in resources/views/components/phase2-approval-modal.blade.php — display RAG-identified laws (from RequiredLaw records with law_registry_id) showing official name, subject area, and relevance reason in clean Arabic prose; show Arabic warning if any required law is missing from RAG database; include "Start Phase 2" button gated on law identification completion
- [x] T014 [P] [US1] Update cases/create.blade.php in resources/views/pages/cases/create.blade.php — remove any law upload references or fields; keep intake.txt and supporting document upload only
- [x] T015 [US2] Update CaseController::startPhase2() in app/Http/Controllers/CaseController.php — gate on RAG availability (RequiredLaw records exist with valid law_registry_id), not on CaseLaw uploads; deprecate CaseLaw requirement

**Checkpoint**: Phase 1 intake + RAG law identification should work end-to-end with streaming

---

## Phase 4: User Story 3 — Pipeline Execution Mechanism (Priority: P1)

**Goal**: Click "Start Phase 2" → 9 agents run sequentially → each agent streams to its own UI panel → pipeline supports resume and re-run

**Independent Test**: Click "Start Phase 2" → verify agents activate sequentially → verify output streams with typewriter effect → verify re-run from Agent N works

### Implementation for User Story 3

- [x] T016 [US3] Update ProcessPhase2Job in app/Jobs/ProcessPhase2Job.php — integrate LegalOrchestrator resume/re-run logic; accept optional start_from_agent parameter; handle pipeline pause on exhausted self-correction
- [x] T017 [US3] Implement rerunFrom() method in CaseController in app/Http/Controllers/CaseController.php — validate agent_number (1-9), validate case status (phase2_completed/paused/failed), delete CaseOutput records and files for agents N-9, set resume_from_agent and status, dispatch ProcessPhase2Job
- [x] T018 [US3] Enhance agent-timeline-live.blade.php in resources/views/components/agent-timeline-live.blade.php — add per-agent "Re-run from here" button on completed agents; handle agent.correction events (show correction notification with attempt number and violation type); handle pipeline.paused event (show Arabic Retry/Cancel modal); show green indicator with token count and duration on agent completion
- [x] T019 [US3] Enhance agent-output-panel.blade.php in resources/views/components/agent-output-panel.blade.php — polish typewriter effect with smooth character-by-character animation; ensure Arabic RTL text streams correctly; show loading spinner while agent is running; display output_files list on completion

**Checkpoint**: Full pipeline execution mechanism with resume/re-run should work

---

## Phase 5: User Stories 4–12 — Agent Pipeline Rework (Priority: P1)

**Goal**: Rework all 9 Phase 2 agent prompts and output processing to match spec requirements — structured outputs, dual files, SKILL.md-driven behavior

**Independent Test**: Run each agent individually with upstream outputs → verify all specified output files are produced with correct format and content

### Implementation for User Stories 4–12

- [x] T020 [P] [US4] Rework LeadCounselAgent in app/Services/Agents/Phase2/LeadCounselAgent.php — read intake.txt + docs/ + memory/errors_log.md; produce 01_lead_plan.md (case summary, scope, acceptance criteria with confidence >= 0.70, expected output files per agent, strategic instructions for Agent 8 three-tier defense) and 01_acceptance_criteria.json; save both to CaseOutput with correct output_type; use prompts from SKILL.md
- [x] T021 [P] [US5] Rework EvidenceManagerAgent in app/Services/Agents/Phase2/EvidenceManagerAgent.php — read all docs/ files; produce 02_chunks.jsonl (chunk_id, source_path, section_id, start_line, end_line, text 1200-1800 chars, 200-char overlap) and 02_ingestion_report.md (files processed, errors, corrupted files flagged as _needs_review); log issues to memory/errors_log.md
- [x] T022 [P] [US6] Rework ChainOfCustodyAgent in app/Services/Agents/Phase2/ChainOfCustodyAgent.php — compute document fingerprints (first/last 64 chars, line count, char count); query RAG database via VectorSearchService for relevant legal articles; produce 03_statutes_index.jsonl (statute_id, title, article_no, content, file_label, local_ref, effective_year, supersedes, source: rag_database, law_registry_id), 03_conflict_warnings.md (abrogations/conflicts), and 03_chain_of_custody_summary.md; log abrogations to memory/errors_log.md
- [x] T023 [P] [US7] Rework TimelineExtractorAgent in app/Services/Agents/Phase2/TimelineExtractorAgent.php — read 02_chunks.jsonl; extract events with id, date (ISO or null), date_raw, place, parties, description, source_refs, confidence; merge duplicates; preserve Hijri dates; produce 04_timeline.json, 04_timeline.md (prose version), and 04_entities_index.md (named entities)
- [x] T024 [P] [US8] Rework LawManagerAgent in app/Services/Agents/Phase2/LawManagerAgent.php — read 02_chunks.jsonl, 04_timeline.json, 03_statutes_index.jsonl, 03_conflict_warnings.md; classify events into legal issues (strong/medium/weak); perform three-step adversary analysis (Fact → Legal Flaw → Effect); flag strongest contradiction as opening defense challenge; produce 05_issues_to_statutes.md, 05_procedural_notes.md (jurisdiction, standing, limitation, res judicata), 05_adversary_evidence_analysis.md, and 05_matching_guidelines.json
- [x] T025 [P] [US9] Rework StatuteMatcherAgent in app/Services/Agents/Phase2/StatuteMatcherAgent.php — read 02_chunks.jsonl, 05_matching_guidelines.json, 03_statutes_index.jsonl, 03_conflict_warnings.md; match articles with confidence >= 0.70 (max 5 per chunk); include literal quoted_text from index; verify non-abrogation; apply Logical Fallback for low-confidence matches (Islamic legal maxims); double-verify article numbers against statutes index; produce 06_statutes_map.jsonl, 06_accepted_matches.md, 06_rejections.md, and 06_gaps_and_todo.md; log abrogated article attempts to memory/errors_log.md
- [x] T026 [P] [US10] Rework DefenseStrategistAgent in app/Services/Agents/Phase2/DefenseStrategistAgent.php — read 06_statutes_map.jsonl, 04_timeline.json, 05_procedural_notes.md, 05_adversary_evidence_analysis.md; produce 07_risk_matrix.md (claim_id, law refs, penalty range, factors, gaps, aggregate confidence), 07_defense_layers.md (Primary/Alternative/Consequential defense lines), 07_charges_scenarios.json, and 07_mitigation_opportunities.md; flag claims with confidence < 0.70 for re-matching; instruct Agent 8 on burden-shifting strategy and three-part closing requests
- [x] T027 [P] [US11] Rework LegalDrafterAgent in app/Services/Agents/Phase2/LegalDrafterAgent.php — read all upstream files + memory/errors_log.md; produce 08_final_brief.md with mandatory structure (Islamic preamble "بسم الله الرحمن الرحيم", Introduction & Framing, Case Facts, Legal & Sharia Framework, Defense Arguments as legal syllogisms Major→Minor→Conclusion, Requests Primary/Alternative/Consequential, Closing & Prayer, Appendices); use dual CASE:{} and LAW:{} citations; mark unsupported paragraphs as "⚠️ غير مُسنَّدة"; use evidence challenge formula; no markdown tables; produce 08_defense_arguments.md and 08_arguments_index.json
- [x] T028 [P] [US12] Rework QualityAssuranceAgent in app/Services/Agents/Phase2/QualityAssuranceAgent.php — read 08_final_brief.md + all upstream outputs; execute full QA checklist (dual citations, LAW quotes match 06_statutes_map.jsonl, no date contradictions, confidence >= 0.70, no abrogated articles, article numbers match 03_statutes_index.jsonl, three-part requests, preamble present); convert [LAW:{...}] to Arabic prose, convert [CASE:{...}] to Arabic prose; delete confidence scores/agent headers/metadata; remove "⚠️ غير مُسنَّدة" paragraphs; produce 09_QA_summary.md, 09_violations.md, 09_fixes_applied.json, 09_todo_back_to_agents.md; produce 09_final_brief_v2.md ONLY if no critical violations remain

**Checkpoint**: All 9 Phase 2 agents should produce correct output files when run sequentially

---

## Phase 6: User Stories 13–16 — Phase 3 Judicial Arbitration (Priority: P2)

**Goal**: After Phase 2 completes, optionally run Phase 3 — Judge reviews brief, Devil's Advocate attacks it, Fortification Agent produces hardened v3 brief

**Independent Test**: After 09_final_brief_v2.md exists → "Start Phase 3" button appears → click it → Judge + Devil's Advocate + Fortification agents run → 13_final_brief_v3.md is produced

### Implementation for User Stories 13–16

- [x] T029 [US13] Add Phase 3 gate UI in resources/views/pages/cases/show.blade.php — show "Start Phase 3 — Judicial Arbitration" button only when status is phase2_completed AND 09_final_brief_v2.md exists; hide when Phase 2 is not complete; never auto-start Phase 3
- [x] T030 [US13] Implement startPhase3() method in CaseController in app/Http/Controllers/CaseController.php — validate gate (status === phase2_completed AND 09_final_brief_v2.md exists); set status to phase3_pending, phase to 3; dispatch ProcessPhase3Job
- [x] T031 [US14] [P] Enhance JudgeAgent in app/Services/Agents/Phase3/JudgeAgent.php — read all output files including 09_final_brief_v2.md; produce 10_judge_notes.md covering: formal requirements check, substantive argument critique, procedural objections, questions likely asked in session, fatal weaknesses, preliminary leaning (accept/reject with confidence); use prompts from SKILL.md
- [x] T032 [US15] [P] Enhance DevilsAdvocateAgent in app/Services/Agents/Phase3/DevilsAdvocateAgent.php — read all output files including 09_final_brief_v2.md; produce 11_devils_advocate_notes.md covering: counter-evidence for each cited proof, stronger articles opponent could cite, internal contradictions, procedural defenses available to opponent, likely opponent evidence, overall success probability assessment; use prompts from SKILL.md
- [x] T033 [US16] Create FortificationAgent in app/Services/Agents/Phase3/FortificationAgent.php — read 09_final_brief_v2.md, 10_judge_notes.md, 11_devils_advocate_notes.md, and all upstream files; classify observations as critical/important/routine; for critical issues include correction instruction in prompt and produce corrected content directly (per research.md R-007); embed Legal Dilemma (Catch-22) paragraphs; apply full AI erasure; produce 12_fortification_plan.md, 12_responses_to_judge.md, 12_counter_arguments.md, and 13_final_brief_v3.md; log newly discovered errors to memory/errors_log.md
- [x] T034 [US16] Create ProcessPhase3Job in app/Jobs/ProcessPhase3Job.php — run JudgeAgent → DevilsAdvocateAgent → FortificationAgent sequentially; stream output via CaseEventService; set case status to phase3_completed on success

**Checkpoint**: Full Phase 3 judicial arbitration should work end-to-end

---

## Phase 7: User Story 17 — PDF Export (Priority: P1)

**Goal**: Single-click export of the final brief as a properly formatted Arabic RTL PDF

**Independent Test**: After final brief exists → click export → verify PDF downloads with RTL Arabic layout, court margins, no AI traces, correct Arabic filename

### Implementation for User Story 17

- [x] T035 [US17] Install mpdf via composer and create PdfExportService in app/Services/PdfExportService.php — accept markdown content, convert to HTML, render as Arabic RTL PDF with court-submission margins, embed Arabic fonts (Amiri or Cairo), strip any remaining AI traces or metadata, generate filename as case-name-date.pdf in Arabic
- [x] T036 [US17] Implement CaseController::pdf() in app/Http/Controllers/CaseController.php — gate on status in [phase2_completed, phase3_completed]; determine latest brief (13_final_brief_v3.md if exists, else 09_final_brief_v2.md); call PdfExportService; return PDF download with Content-Type application/pdf and Content-Disposition attachment with Arabic filename
- [x] T037 [US17] Add PDF export button in resources/views/pages/cases/show.blade.php — show export button only when phase2_completed or phase3_completed; trigger download without page reload (AJAX or direct link); ensure button is visible alongside Phase 3 gate button when applicable

**Checkpoint**: PDF export should produce correct Arabic RTL court-ready document

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: UI improvements, consistency, and validation across all user stories

- [x] T038 [P] Update cases/index.blade.php in resources/views/pages/cases/index.blade.php — update status labels to include Phase 3 statuses (phase3_pending, phase3_processing, phase3_completed); ensure all labels are Arabic
- [x] T039 [P] Update dashboard.blade.php in resources/views/pages/dashboard.blade.php — ensure metrics display includes Phase 3 completion counts and PDF export counts if tracked
- [x] T040 [P] Polish smooth animations across agent panels in resources/views/components/ — ensure typewriter effect uses CSS transitions for character appearance; add fade-in for agent card activation; add smooth progress bar animation; ensure correction event notifications animate in/out
- [x] T041 Audit all Arabic error messages across app/Services/ and resources/views/ — ensure FR-023 compliance (no raw JSON, internal IDs, error stack traces in UI); verify all user-facing messages are in Arabic prose
- [x] T042 End-to-end manual pipeline validation per Constitution Principle III — upload test case documents, verify Phase 1 RAG identification, Phase 2 full pipeline with streaming, Phase 3 judicial arbitration, and PDF export all work together

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion — BLOCKS all user stories
- **US-01+02 (Phase 3)**: Depends on Foundational phase completion
- **US-03 (Phase 4)**: Depends on Foundational phase completion; can run in parallel with Phase 3
- **US-04–12 (Phase 5)**: Depends on Foundational phase completion; all agents can be reworked in parallel (separate files)
- **US-13–16 (Phase 6)**: Depends on Phase 5 completion (needs agents 1-9 working)
- **US-17 (Phase 7)**: Depends on at least Phase 5 completion (needs 09_final_brief_v2.md)
- **Polish (Phase 8)**: Depends on all story phases being complete

### User Story Dependencies

- **US-01 + US-02 (P1)**: Can start after Foundational — No dependencies on other stories
- **US-03 (P1)**: Can start after Foundational — Integrates with US-01/02 for end-to-end but can be tested independently
- **US-04–12 (P1)**: Each agent can be reworked independently (separate files) — but testing requires sequential upstream outputs
- **US-13–16 (P2)**: Requires US-12 complete (needs 09_final_brief_v2.md as input)
- **US-17 (P1)**: Requires US-12 complete (needs final brief to export)
- **US-18 (P1)**: Infrastructure in Foundational phase; tested via any agent error
- **US-19 (P1)**: Infrastructure in Foundational phase; tested via self-correction trigger

### Within Each User Story

- Models/migrations before services
- Services before controllers
- Controllers before views
- Core implementation before integration
- Story complete before moving to next priority

### Parallel Opportunities

- All Setup tasks marked [P] can run in parallel (T003, T004)
- All Foundational tasks marked [P] can run in parallel (T005, T007, T009, T010)
- Once Foundational phase completes, Phases 3, 4, and 5 can start in parallel
- All 9 agent rework tasks (T020–T028) can run in parallel — they are in separate files with no cross-dependencies
- Phase 3 agent enhancements (T031, T032) can run in parallel

---

## Parallel Example: Phase 5 Agent Rework

```bash
# All 9 agent reworks can run in parallel (separate files):
Task: "T020 [P] [US4] Rework LeadCounselAgent in app/Services/Agents/Phase2/LeadCounselAgent.php"
Task: "T021 [P] [US5] Rework EvidenceManagerAgent in app/Services/Agents/Phase2/EvidenceManagerAgent.php"
Task: "T022 [P] [US6] Rework ChainOfCustodyAgent in app/Services/Agents/Phase2/ChainOfCustodyAgent.php"
Task: "T023 [P] [US7] Rework TimelineExtractorAgent in app/Services/Agents/Phase2/TimelineExtractorAgent.php"
Task: "T024 [P] [US8] Rework LawManagerAgent in app/Services/Agents/Phase2/LawManagerAgent.php"
Task: "T025 [P] [US9] Rework StatuteMatcherAgent in app/Services/Agents/Phase2/StatuteMatcherAgent.php"
Task: "T026 [P] [US10] Rework DefenseStrategistAgent in app/Services/Agents/Phase2/DefenseStrategistAgent.php"
Task: "T027 [P] [US11] Rework LegalDrafterAgent in app/Services/Agents/Phase2/LegalDrafterAgent.php"
Task: "T028 [P] [US12] Rework QualityAssuranceAgent in app/Services/Agents/Phase2/QualityAssuranceAgent.php"
```

---

## Implementation Strategy

### MVP First (Phase 1 Intake → Agent Pipeline → Brief Output)

1. Complete Phase 1: Setup (T001–T004)
2. Complete Phase 2: Foundational (T005–T010) — CRITICAL
3. Complete Phase 3: US-01+02 Case Intake (T011–T015)
4. Complete Phase 4: US-03 Pipeline Mechanism (T016–T019)
5. Complete Phase 5: US-04–12 Agent Rework (T020–T028)
6. **STOP and VALIDATE**: Test full pipeline end-to-end (Phase 1 → Phase 2 → Brief output)

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. US-01+02 → Test Phase 1 intake independently
3. US-03 → Test pipeline execution mechanism independently
4. US-04–12 → Test each agent → Full Phase 2 pipeline
5. US-17 → PDF export → **Deployable MVP!**
6. US-13–16 → Phase 3 judicial arbitration → Enhanced product
7. Polish → Production-ready

### Single Developer Strategy

1. Complete Setup + Foundational sequentially
2. Implement US-01+02, then US-03, then US-04–12 (agents can be done in any order within Phase 5)
3. After Phase 2 pipeline works end-to-end: add PDF export (US-17)
4. Then Phase 3 (US-13–16) as enhancement
5. Polish pass at the end

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- SKILL.md (T001) is the highest-priority task — Constitution Principle V requires it before any agent logic changes
- All agent reworks (T020–T028) depend on SKILL.md content for prompts
- Manual UI verification after each phase per Constitution Principle III
- Avoid: vague tasks, same file conflicts, cross-story dependencies that break independence

## Summary

| Metric | Value |
|--------|-------|
| **Total tasks** | 42 |
| **Phase 1 (Setup)** | 4 tasks |
| **Phase 2 (Foundational)** | 6 tasks |
| **Phase 3 (US-01+02)** | 5 tasks |
| **Phase 4 (US-03)** | 4 tasks |
| **Phase 5 (US-04–12)** | 9 tasks |
| **Phase 6 (US-13–16)** | 6 tasks |
| **Phase 7 (US-17)** | 3 tasks |
| **Phase 8 (Polish)** | 5 tasks |
| **Parallel opportunities** | 20 tasks marked [P] |
| **MVP scope** | Phases 1–5 + Phase 7 (31 tasks) |
| **User stories covered** | All 19 (US-01 through US-19) |
