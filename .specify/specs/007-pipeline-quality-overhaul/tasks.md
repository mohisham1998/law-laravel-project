# Tasks: Production-Ready Agent Pipeline Quality Overhaul

**Input**: Design documents from `/specs/007-pipeline-quality-overhaul/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, quickstart.md

**Tests**: UI testing via Playwright MCP after each phase checkpoint (per constitution principle III).

**Organization**: Tasks grouped by user story. US1-US3 are P1 (critical, implement first). US4-US6 are P2. US7 is P3.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Add per-agent configuration and prepare the foundation for all downstream changes.

- [X] T001 Add per-agent `agents` config block (temperature, max_tokens) to `config/legal.php`
- [X] T002 Read and internalize SKILL.md structure at `.agent/skills/legal-counsel/SKILL.md` — identify heading patterns for section extraction

**Checkpoint**: Config values available via `config('legal.agents.{N}.temperature')` and `config('legal.agents.{N}.max_tokens')`.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Rewrite PromptBuilder and create OutputValidator — the two core services all agent changes depend on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T003 Rewrite `app/Services/Orchestration/PromptBuilder.php` — add `extractGeneralRules()` method that extracts text from `## General Rules` to the next `## ` heading in SKILL.md
- [X] T004 Add `extractAgentSection(int $agentNumber)` method to `app/Services/Orchestration/PromptBuilder.php` — extracts from `### Agent {N}:` to the next `---` separator
- [X] T005 Rewrite `buildPromptForAgent(int $agentNumber, string $context)` in `app/Services/Orchestration/PromptBuilder.php` — compose: General Rules + Agent Section + Context (no longer sends full SKILL.md)
- [X] T006 Add `buildOutputTemplate(int $agentNumber)` method to `app/Services/Orchestration/PromptBuilder.php` — returns the output format/schema section for each agent based on SKILL.md schemas
- [X] T007 [P] Create `app/Services/Orchestration/OutputValidator.php` — class with static validation methods: `validateJsonl()`, `validateStatuteIds()`, `validateQuotedText()`, `validateNoAbrogated()`, `validateConfidenceFloor()`
- [X] T008 [P] Add `validateBriefCitations(string $brief, string $statutesMap)` and `validateBriefStructure(string $brief)` methods to `app/Services/Orchestration/OutputValidator.php`
- [X] T009 Update `app/Services/Agents/Phase2/Phase2BaseAgent.php` — read temperature and max_tokens from `config('legal.agents.{N}')` instead of hardcoded values in `executeWithStreaming()` calls

**Checkpoint**: PromptBuilder produces focused prompts (< 200 lines per agent). OutputValidator validates JSONL, citations, and brief structure. Agents read config-driven temperature/max_tokens. System still functions with existing agent prompt code.

---

## Phase 3: User Story 1 — Agent-Specific Focused Prompts (Priority: P1) 🎯 MVP

**Goal**: Every agent receives a focused prompt with only its relevant SKILL.md section + General Rules, not the full 617-line file.

**Independent Test**: Submit a case. Check that agent outputs are structured and relevant (not random/generic). Verify prompt size is under 200 lines.

### Implementation for User Story 1

- [X] T010 [US1] Update `app/Services/Agents/Phase1AnalysisAgent.php` — use `PromptBuilder::buildPromptForAgent(0, $context)` with focused Agent 0 section, fix max_tokens from 150 to `config('legal.agents.0.max_tokens')` (4096)
- [X] T011 [P] [US1] Update `app/Services/Agents/Phase2/LeadCounselAgent.php` — replace hardcoded Arabic prompt block with `PromptBuilder::buildPromptForAgent(1, $context)` + output template from `buildOutputTemplate(1)`
- [X] T012 [P] [US1] Update `app/Services/Agents/Phase2/EvidenceManagerAgent.php` — use focused prompt from PromptBuilder for Agent 2
- [X] T013 [P] [US1] Update `app/Services/Agents/Phase2/ChainOfCustodyAgent.php` — use focused prompt from PromptBuilder for Agent 3
- [X] T014 [P] [US1] Update `app/Services/Agents/Phase2/TimelineExtractorAgent.php` — use focused prompt from PromptBuilder for Agent 4
- [X] T015 [P] [US1] Update `app/Services/Agents/Phase2/LawManagerAgent.php` — use focused prompt from PromptBuilder for Agent 5
- [X] T016 [P] [US1] Update `app/Services/Agents/Phase2/StatuteMatcherAgent.php` — use focused prompt from PromptBuilder for Agent 6
- [X] T017 [P] [US1] Update `app/Services/Agents/Phase2/DefenseStrategistAgent.php` — use focused prompt from PromptBuilder for Agent 7
- [X] T018 [P] [US1] Update `app/Services/Agents/Phase2/LegalDrafterAgent.php` — use focused prompt from PromptBuilder for Agent 8
- [X] T019 [P] [US1] Update `app/Services/Agents/Phase2/QualityAssuranceAgent.php` — use focused prompt from PromptBuilder for Agent 9
- [X] T020 [P] [US1] Update `app/Services/Agents/Phase3/JudgeAgent.php` — use focused prompt from PromptBuilder for Agent 10
- [X] T021 [P] [US1] Update `app/Services/Agents/Phase3/DevilsAdvocateAgent.php` — use focused prompt from PromptBuilder for Agent 11, fix temperature from 0.4 to `config('legal.agents.11.temperature')` (0.3)
- [X] T022 [P] [US1] Update `app/Services/Agents/Phase3/FortificationAgent.php` — use focused prompt from PromptBuilder for Agent 12

**Checkpoint**: All 13 agents use focused prompts from PromptBuilder. No agent receives full SKILL.md. Phase 1 produces meaningful output (4096 max_tokens). Test via Playwright: submit a case and verify Phase 1 completes with structured output.

---

## Phase 4: User Story 2 — Structured Output Templates with Few-Shot Examples (Priority: P1)

**Goal**: Critical agents (5, 6, 8) receive explicit output templates and few-shot examples so even budget models produce parseable structured output.

**Independent Test**: Submit a case. Verify Agent 6 output is valid JSONL with all required fields. Verify Agent 8 brief has all 8 mandatory sections.

### Implementation for User Story 2

- [X] T023 [US2] Add few-shot example for Agent 5 (Law Manager) to `PromptBuilder::buildOutputTemplate(5)` — show `05_issues_to_statutes.md` and `05_matching_guidelines.json` format per SKILL.md in `app/Services/Orchestration/PromptBuilder.php`
- [X] T024 [US2] Add few-shot example for Agent 6 (Statute Matcher) to `PromptBuilder::buildOutputTemplate(6)` — show `06_statutes_map.jsonl` format with 2 example JSONL lines per SKILL.md in `app/Services/Orchestration/PromptBuilder.php`
- [X] T025 [US2] Add few-shot example for Agent 8 (Legal Drafter) to `PromptBuilder::buildOutputTemplate(8)` — show the 8-section brief structure with citation format per SKILL.md in `app/Services/Orchestration/PromptBuilder.php`
- [X] T026 [P] [US2] Add output templates for Agents 1-4, 7, 9 to `PromptBuilder::buildOutputTemplate()` — show required output file structures per SKILL.md schemas in `app/Services/Orchestration/PromptBuilder.php`
- [X] T027 [P] [US2] Add output templates for Phase 3 Agents 10-12 to `PromptBuilder::buildOutputTemplate()` in `app/Services/Orchestration/PromptBuilder.php`
- [X] T028 [US2] Add context boundary instruction to `PromptBuilder::buildPromptForAgent()` — append "لا يجوز الاستشهاد بأي نظام أو مادة غير مذكورة في السياق أعلاه" (You may ONLY cite statutes listed above) in `app/Services/Orchestration/PromptBuilder.php`

**Checkpoint**: All agents receive output templates. Critical agents (5, 6, 8) have few-shot examples. Context boundary instruction prevents citations outside visible statutes. Test via Playwright: submit a case and verify structured output from agents.

---

## Phase 5: User Story 3 — Deterministic PHP Validation Between Agents (Priority: P1)

**Goal**: PHP validators cross-check agent output against upstream data. Hallucinated citations are caught deterministically and trigger re-runs.

**Independent Test**: Submit a case. After Agent 6 completes, verify all `statute_id` values exist in `03_statutes_index.jsonl`. After Agent 8 completes, verify all `LAW:{ref}` citations are in accepted matches.

### Implementation for User Story 3

- [X] T029 [US3] Integrate `OutputValidator::validateStatuteIds()` into `app/Services/Agents/Phase2/StatuteMatcherAgent.php` — run after output, feed violations into self-correction retry loop
- [X] T030 [US3] Integrate `OutputValidator::validateQuotedText()` into `app/Services/Agents/Phase2/StatuteMatcherAgent.php` — verify quoted_text is substring of source entry content
- [X] T031 [US3] Integrate `OutputValidator::validateNoAbrogated()` into `app/Services/Agents/Phase2/StatuteMatcherAgent.php` — check `supersedes` field and conflict warnings
- [X] T032 [US3] Integrate `OutputValidator::validateConfidenceFloor()` into `app/Services/Agents/Phase2/StatuteMatcherAgent.php` — reject matches below 0.70
- [X] T033 [US3] Integrate `OutputValidator::validateBriefCitations()` into `app/Services/Agents/Phase2/LegalDrafterAgent.php` — verify LAW:{ref} citations against `06_statutes_map.jsonl`
- [X] T034 [US3] Integrate `OutputValidator::validateBriefStructure()` into `app/Services/Agents/Phase2/LegalDrafterAgent.php` — verify 8 mandatory brief sections present
- [X] T035 [US3] Update `executeWithSelfCorrection()` in `app/Services/Agents/Phase2/Phase2BaseAgent.php` — run deterministic validation AFTER LLM-based validateOutput(), append specific violation details to retry prompt
- [X] T036 [US3] Add deterministic JSONL validation to `app/Services/Agents/Phase2/EvidenceManagerAgent.php` and `app/Services/Agents/Phase2/ChainOfCustodyAgent.php` — validate `02_chunks.jsonl` and `03_statutes_index.jsonl` are well-formed

**Checkpoint**: Agent 6 output has 100% validated statute_ids. Agent 8 brief has all citations cross-checked. Invalid citations trigger automatic re-runs with error context. Test via Playwright: submit a case, verify pipeline completes without hallucinated citations.

---

## Phase 6: User Story 4 — Phase 1 Meaningful Analysis (Priority: P2)

**Goal**: Phase 1 produces comprehensive case analysis with all relevant laws identified, not truncated to 150 tokens.

**Independent Test**: Submit a multi-document case. Verify Phase 1 output lists at least 3 relevant laws with structured entries.

### Implementation for User Story 4

- [X] T037 [US4] Verify Phase 1 uses focused prompt + output template + 4096 max_tokens in `app/Services/Agents/Phase1AnalysisAgent.php` (should already be done in T010, verify and fix any remaining issues)
- [X] T038 [US4] Add output template for Agent 0 showing expected `00_required_laws.md` structure in `PromptBuilder::buildOutputTemplate(0)` in `app/Services/Orchestration/PromptBuilder.php`

**Checkpoint**: Phase 1 identifies all relevant laws with official name, subject area, relevance reason, abrogation status, and key articles. Test via Playwright: submit a case and verify Phase 1 output is comprehensive.

---

## Phase 7: User Story 5 — Temperature and Token Configuration per Agent (Priority: P2)

**Goal**: All agents read temperature and max_tokens from centralized config, not hardcoded values.

**Independent Test**: Change a temperature value in config, restart queue worker, verify agent uses the new value.

### Implementation for User Story 5

- [X] T039 [US5] Verify all 13 agents use `config('legal.agents.{N}')` for temperature and max_tokens (should be done via T009 + Phase 3, audit for any remaining hardcoded values across all agent files)
- [X] T040 [US5] Verify Agent 6 uses temperature 0.3 (was 0.2) and Agent 11 uses temperature 0.3 (was 0.4) from config in their respective files

**Checkpoint**: All temperature/max_tokens values centralized. No hardcoded values in agent files. Test: inspect config and verify agents respect overrides.

---

## Phase 8: User Story 6 — Enhanced RAG Context Without Truncation (Priority: P2)

**Goal**: Agents receive relevant statute subsets instead of silently truncated full index. No citations outside visible statutes.

**Independent Test**: Submit a case with a large law library. Verify Agent 6 receives filtered statutes and doesn't cite any outside its visible set.

### Implementation for User Story 6

- [X] T041 [US6] Update `buildContext()` in `app/Services/Agents/Phase2/Phase2BaseAgent.php` — for agents after Agent 5, filter `03_statutes_index.jsonl` using `05_matching_guidelines.json` priority_statutes list instead of truncating the full index
- [X] T042 [US6] Increase statute index budget from 40K to 80K chars for agents before Agent 5 (that need the full index) in `app/Services/Agents/Phase2/Phase2BaseAgent.php` PER_FILE_CAPS
- [X] T043 [US6] When statute context is filtered/trimmed, append explicit count message: "تتضمن القائمة أعلاه {N} مادة نظامية. لا يجوز الاستشهاد بأي مادة غير مدرجة." to the context in `app/Services/Agents/Phase2/Phase2BaseAgent.php`

**Checkpoint**: Agent 6 receives relevant statute subset based on Agent 5's guidelines. No silent truncation. Test via Playwright: submit a case and verify no out-of-index citations.

---

## Phase 9: User Story 7 — Strengthened Gate Validation in Orchestrator (Priority: P3)

**Goal**: Gate validation includes deterministic checks at phase boundaries to prevent broken output from propagating.

**Independent Test**: If Agent 6 output has invalid statute_ids, the gate blocks Phase 3 from starting and triggers re-processing.

### Implementation for User Story 7

- [X] T044 [US7] Add deterministic check to `validatePhase2Gate()` in `app/Services/Orchestration/GateValidator.php` — verify all statute_ids in `06_statutes_map.jsonl` exist in `03_statutes_index.jsonl`
- [X] T045 [US7] Add deterministic check to `validatePhase3Gate()` in `app/Services/Orchestration/GateValidator.php` — verify `09_final_brief_v2.md` has all 8 mandatory sections and no `⚠️ غير مُسنَّدة` markers remain
- [X] T046 [US7] Update `app/Services/Orchestration/LegalOrchestrator.php` — when gate validation fails with critical violations, re-run the offending agent with violation details before proceeding

**Checkpoint**: Phase boundaries enforce deterministic quality checks. Broken output triggers re-processing. Test via Playwright: full end-to-end pipeline run.

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Final validation and cleanup across all changes.

- [ ] T047 Run full end-to-end pipeline test via Playwright MCP — submit a new case, monitor through all 3 phases, verify final brief quality
- [ ] T048 Verify backward compatibility — existing cases still load and display correctly in the UI
- [ ] T049 Audit all agent files for any remaining references to full SKILL.md or hardcoded temperature/max_tokens values

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 — BLOCKS all user stories
- **US1 (Phase 3)**: Depends on Phase 2 — focused prompts for all 13 agents
- **US2 (Phase 4)**: Depends on Phase 2 — output templates and few-shot examples
- **US3 (Phase 5)**: Depends on Phase 2 — deterministic validation integration
- **US4 (Phase 6)**: Depends on US1 (Phase 3) — verifies Phase 1 fix
- **US5 (Phase 7)**: Depends on US1 (Phase 3) — verifies config-driven params
- **US6 (Phase 8)**: Depends on US1 (Phase 3) — RAG context improvements
- **US7 (Phase 9)**: Depends on US3 (Phase 5) — gate validation uses OutputValidator
- **Polish (Phase 10)**: Depends on all above

### User Story Dependencies

- **US1 (Focused Prompts)**: After Foundational — no other story dependencies
- **US2 (Output Templates)**: After Foundational — can run in parallel with US1 (different methods in PromptBuilder)
- **US3 (Deterministic Validation)**: After Foundational — can run in parallel with US1/US2 (OutputValidator is separate)
- **US4 (Phase 1 Fix)**: After US1 (T010 must be done first)
- **US5 (Config)**: After US1 (T009 must be done first)
- **US6 (RAG Context)**: After US1 (buildContext changes depend on focused prompts)
- **US7 (Gate Validation)**: After US3 (gate checks use OutputValidator)

### Parallel Opportunities

- T007 and T008 (OutputValidator methods) can run in parallel with T003-T006 (PromptBuilder methods)
- T011-T022 (all 13 agent updates) can run in parallel after T005
- T023-T027 (output templates) can run in parallel with T029-T036 (validation integration)
- T041-T043 (RAG context) can run in parallel with T044-T046 (gate validation)

---

## Implementation Strategy

### MVP First (User Stories 1-3 = Phase 3-5)

1. Complete Phase 1: Setup (config)
2. Complete Phase 2: Foundational (PromptBuilder + OutputValidator)
3. Complete Phase 3: US1 — Focused prompts for all agents
4. **STOP and VALIDATE**: Test via Playwright — submit a case, verify improved output
5. Complete Phase 4: US2 — Output templates + few-shot examples
6. Complete Phase 5: US3 — Deterministic validation
7. **STOP and VALIDATE**: Test via Playwright — verify zero hallucinated citations

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. US1 (Focused Prompts) → Test → Immediate quality improvement (MVP!)
3. US2 (Templates) → Test → Better structured output
4. US3 (Validation) → Test → Zero hallucinations guaranteed
5. US4-US7 → Test → Production-ready polish

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each checkpoint includes Playwright MCP UI testing per constitution principle III
- All prompt content MUST trace back to SKILL.md per constitution principle V
- No new UI pages — all changes are backend services per constitution principle VI
- Total: 49 tasks across 10 phases
