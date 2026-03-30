# Tasks: Pipeline Output Quality Overhaul

**Input**: Design documents from `/specs/009-pipeline-output-quality/`
**Prerequisites**: plan.md (required), spec.md (required), research.md, data-model.md, quickstart.md

**Tests**: Included per user request — each task is independently testable via Playwright UI MCP for end-to-end validation.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing. Within each story, grouped by component as requested.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Create new directories and foundational service stubs needed by multiple user stories.

- [x] T001 Create `app/Services/Output/` directory for new post-processing services
- [x] T002 [P] Create stub `app/Services/Output/BriefPostProcessor.php` with class skeleton and `process(string $brief): string` method signature
- [x] T003 [P] Create stub `app/Services/Output/FinalArabicBriefComposer.php` with class skeleton and `compose(LegalCase $case): ?string` method signature

**Checkpoint**: New directory and service stubs exist. No behavior changes yet.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure changes that MUST be complete before ANY user story can be implemented. These changes are backward-compatible — existing pipeline behavior is preserved.

**CRITICAL**: No user story work can begin until this phase is complete.

### Group 1: PromptBuilder Refactor

- [x] T004 Add `buildSystemPrompt(int $agentNumber): string` method to `app/Services/Orchestration/PromptBuilder.php` — extracts agent persona + General Rules + agent-specific section + anti-hallucination rules + output template from SKILL.md. Returns empty string for now (stub). Keep existing `buildPromptForAgent()` unchanged.
  - **Test**: Call `buildSystemPrompt(8)` — returns a string. Call `buildPromptForAgent(8, $ctx)` — returns same result as before (backward compat).

- [x] T005 Add `buildUserPrompt(int $agentNumber, string $context): string` method to `app/Services/Orchestration/PromptBuilder.php` — returns context boundary instruction + case context. Stub initially.
  - **Test**: Call `buildUserPrompt(8, $ctx)` — returns a string containing context.

- [x] T006 Implement `buildSystemPrompt()` fully in `app/Services/Orchestration/PromptBuilder.php` — parse SKILL.md agent sections, compose system prompt with: Arabic agent persona (2-3 sentences), General Rules, agent-specific section, anti-hallucination rules (for agents 3,5,6,7,8,9), output template. All Arabic for Arabic-output agents.
  - **Test**: `buildSystemPrompt(0)` through `buildSystemPrompt(12)` each return non-empty Arabic content. Each includes the agent's role description.

- [x] T007 Implement `buildUserPrompt()` fully in `app/Services/Orchestration/PromptBuilder.php` — returns context boundary instruction (for agents 5,6,7,8,9) + case context data.
  - **Test**: `buildUserPrompt(8, $ctx)` returns string containing context. `buildUserPrompt(1, $ctx)` returns context without boundary instruction.

- [x] T008 Update `buildPromptForAgent()` in `app/Services/Orchestration/PromptBuilder.php` to internally call `buildSystemPrompt()` + separator + `buildUserPrompt()` — backward-compatible wrapper.
  - **Test**: Existing callers of `buildPromptForAgent()` produce identical or improved results. No regression.

### Group 2: OutputValidator New Methods

- [x] T009 [P] Add `validateArabicFinalBrief(string $brief): array` to `app/Services/Orchestration/OutputValidator.php` — checks Arabic char ratio >= 95%, no JSON blocks, no CASE/LAW markers, no technical English terms (statute_id, chunk_id, confidence, match_type, abrogated). Returns violations array.
  - **Test**: Pass pure Arabic text — returns empty array. Pass text with `LAW:LABOR_ART_80` — returns violation. Pass text with `confidence: 0.85` — returns violation.

- [x] T010 [P] Add `validateNoEnglishLeak(string $brief): array` to `app/Services/Orchestration/OutputValidator.php` — checks for 3+ consecutive ASCII words (excluding proper nouns, single words, abbreviations). Returns violations array.
  - **Test**: Arabic text with "Google" (proper noun) — passes. Arabic text with "the statute was applied" — returns violation.

- [x] T011 Update `validateBriefCitations()` in `app/Services/Orchestration/OutputValidator.php` — remove LAW:{ref} pattern check, add check for Arabic citation pattern ("المادة [number] من [law_name]") matching statute names in index.
  - **Test**: Brief with Arabic prose citations matching known statutes — passes. Brief with no citations — returns violation.

### Group 3: Context Budget Increase

- [x] T012 [P] Update `LAW_CONTEXT_MAX_CHARS` from `50_000` to `100_000` in `app/Services/Agents/Phase2/Phase2BaseAgent.php`
  - **Test**: Verify constant value is 100000.

- [x] T013 [P] Update `PER_FILE_CAPS['03_statutes_index.jsonl']` from `80_000` to `120_000` in `app/Services/Agents/Phase2/Phase2BaseAgent.php`
  - **Test**: Verify cap value is 120000.

**Checkpoint**: Foundation ready — PromptBuilder has new methods (backward-compatible), OutputValidator has Arabic validators, context budgets increased. Pipeline still works as before.

---

## Phase 3: User Story 1 — Pure Arabic Legal Brief Output (Priority: P1)

**Goal**: Final briefs are entirely in formal legal Arabic — no English, no JSON, no markers, no confidence scores.

**Independent Test**: Submit a case via Playwright UI, run full pipeline, verify final brief output is pure Arabic with 8-section structure and بسم الله الرحمن الرحيم preamble.

### SKILL.md & Prompt Changes

- [x] T014 [US1] Update `.agent/skills/legal-counsel/SKILL.md` Agent 8 section — remove all CASE:{ref} and LAW:{ref} marker instructions, remove "mark unsupported paragraphs with غير مُسنَّدة" instruction, add Arabic prose citation rules (DA-4 from plan.md).
  - **Test**: Read SKILL.md Agent 8 section — no instances of `CASE:` or `LAW:` patterns. Contains Arabic citation rules.
  - **Files**: `.agent/skills/legal-counsel/SKILL.md`

- [x] T015 [US1] Update `.agent/skills/legal-counsel/SKILL.md` Agent 9 section — remove "dual citation format" check, add "Arabic-only content" check. Agent 9 no longer performs AI Erasure.
  - **Test**: Read SKILL.md Agent 9 section — no AI Erasure references. Contains Arabic-only check.
  - **Files**: `.agent/skills/legal-counsel/SKILL.md`

- [x] T016 [US1] Update `.agent/skills/legal-counsel/SKILL.md` Agent 12 section — remove AI Erasure pass. Agent 12 focuses on fortification only.
  - **Test**: Read SKILL.md Agent 12 section — no erasure references.
  - **Files**: `.agent/skills/legal-counsel/SKILL.md`

- [x] T017 [US1] Update PromptBuilder Agent 8 template in `app/Services/Orchestration/PromptBuilder.php` — align with SKILL.md changes (remove marker-related template instructions).
  - **Test**: `buildPromptForAgent(8, $ctx)` output contains no CASE/LAW marker instructions.
  - **Files**: `app/Services/Orchestration/PromptBuilder.php`
  - **Depends on**: T014

### BriefPostProcessor Implementation

- [x] T018 [US1] Implement `BriefPostProcessor::process()` in `app/Services/Output/BriefPostProcessor.php` — full 10-step cleanup: strip CASE/LAW markers, remove confidence scores, remove agent headers, remove غير مُسنَّدة paragraphs, remove JSON fences, remove English-only lines, ensure بسم الله preamble, normalize whitespace (per DA-6).
  - **Test**: Pass a brief with CASE:DOC01, LAW:ART_80, `confidence: 0.9`, ```json blocks → all removed. Output starts with بسم الله الرحمن الرحيم. Arabic ratio >= 95%.
  - **Files**: `app/Services/Output/BriefPostProcessor.php`

### FinalArabicBriefComposer Implementation

- [x] T019 [US1] Implement `FinalArabicBriefComposer::compose()` in `app/Services/Output/FinalArabicBriefComposer.php` — select best brief (v3 > v2 > v1 from case outputs), apply BriefPostProcessor, return clean Arabic string (per DA-7).
  - **Test**: Case with v3 brief → returns processed v3. Case with only v1 → returns processed v1. Case with no briefs → returns null.
  - **Files**: `app/Services/Output/FinalArabicBriefComposer.php`
  - **Depends on**: T018

### Pipeline Integration

- [x] T020 [US1] Integrate BriefPostProcessor after Agent 9 in `app/Services/Orchestration/LegalOrchestrator.php` — after Agent 9 saves `09_final_brief_v2.md`, run `BriefPostProcessor::process()` on the content and update the saved output.
  - **Test**: After Agent 9 completes, `09_final_brief_v2.md` content has no markers, no English artifacts.
  - **Files**: `app/Services/Orchestration/LegalOrchestrator.php`
  - **Depends on**: T018

- [x] T021 [US1] Integrate BriefPostProcessor after Agent 12 in `app/Jobs/ProcessPhase3Job.php` — after Agent 12 saves `13_final_brief_v3.md`, run `BriefPostProcessor::process()` and update saved output.
  - **Test**: After Agent 12 completes, `13_final_brief_v3.md` content is pure Arabic.
  - **Files**: `app/Jobs/ProcessPhase3Job.php`
  - **Depends on**: T018

- [x] T022 [US1] Integrate FinalArabicBriefComposer at pipeline end in `app/Jobs/ProcessPhase3Job.php` (or `LegalOrchestrator.php` if Phase 3 not run) — call `compose()` to produce the definitive final output.
  - **Test**: After pipeline completion, the final displayed brief is the best available version, post-processed.
  - **Files**: `app/Jobs/ProcessPhase3Job.php`, `app/Services/Orchestration/LegalOrchestrator.php`
  - **Depends on**: T019, T020, T021

### End-to-End Validation

- [ ] T023 [US1] Playwright UI test: Create a new case with Arabic intake text, upload a document, run full pipeline, verify final brief output contains only Arabic (no English terms, no JSON, no markers), starts with بسم الله, has 8-section structure.
  - **Test**: Playwright navigates to case creation, submits case, monitors pipeline, opens final output, asserts Arabic-only content.
  - **Depends on**: T014-T022

**Checkpoint**: Final briefs are pure Arabic. BriefPostProcessor cleans all artifacts. Quality validators enforce Arabic-only output.

---

## Phase 4: User Story 2 — Stronger Legal Reasoning & Analysis Depth (Priority: P1)

**Goal**: Agents produce deep, case-specific analysis with structured syllogisms, grounded in specific statute articles.

**Independent Test**: Submit a case, verify defense arguments contain legal syllogisms referencing specific statutes, not generic template text.

### Chain-of-Thought Instructions

- [x] T024 [P] [US2] Add chain-of-thought Arabic instructions to Agent 5 (Law Manager) system prompt in `app/Services/Orchestration/PromptBuilder.php` — per DA-12: structured thinking section before output (key legal issues, applicable statutes, strengths/weaknesses, opponent arguments).
  - **Test**: `buildSystemPrompt(5)` contains "مرحلة التفكير المنظم" section.
  - **Files**: `app/Services/Orchestration/PromptBuilder.php`
  - **Depends on**: T006

- [x] T025 [P] [US2] Add chain-of-thought Arabic instructions to Agent 7 (Defense Strategist) system prompt in `app/Services/Orchestration/PromptBuilder.php`.
  - **Test**: `buildSystemPrompt(7)` contains "مرحلة التفكير المنظم" section.
  - **Files**: `app/Services/Orchestration/PromptBuilder.php`
  - **Depends on**: T006

- [x] T026 [P] [US2] Add chain-of-thought Arabic instructions to Agent 8 (Legal Drafter) system prompt in `app/Services/Orchestration/PromptBuilder.php`.
  - **Test**: `buildSystemPrompt(8)` contains "مرحلة التفكير المنظم" section.
  - **Files**: `app/Services/Orchestration/PromptBuilder.php`
  - **Depends on**: T006

### System Message Architecture (Agent Message Construction)

- [x] T027 [US2] Update `Phase2BaseAgent::executeWithStreaming()` in `app/Services/Agents/Phase2/Phase2BaseAgent.php` — change from single `[user]` message to `[system, user]` messages using `buildSystemPrompt()` and `buildUserPrompt()` (per DA-2).
  - **Test**: LLM calls now include 2 messages: first with `role: 'system'`, second with `role: 'user'`. Pipeline still completes successfully.
  - **Files**: `app/Services/Agents/Phase2/Phase2BaseAgent.php`
  - **Depends on**: T006, T007

- [x] T028 [US2] Update `Phase1AnalysisAgent::execute()` in `app/Services/Agents/Phase1AnalysisAgent.php` — use system + user messages instead of single user message.
  - **Test**: Agent 0 LLM call includes system message with agent persona. Output unchanged or improved.
  - **Files**: `app/Services/Agents/Phase1AnalysisAgent.php`
  - **Depends on**: T006, T007

- [x] T029 [P] [US2] Update `JudgeAgent` in `app/Services/Agents/Phase3/JudgeAgent.php` — use system + user messages.
  - **Test**: Agent 10 LLM call includes system message defining judge persona.
  - **Files**: `app/Services/Agents/Phase3/JudgeAgent.php`
  - **Depends on**: T006, T007

- [x] T030 [P] [US2] Update `DevilsAdvocateAgent` in `app/Services/Agents/Phase3/DevilsAdvocateAgent.php` — use system + user messages.
  - **Test**: Agent 11 LLM call includes system message.
  - **Files**: `app/Services/Agents/Phase3/DevilsAdvocateAgent.php`
  - **Depends on**: T006, T007

- [x] T031 [P] [US2] Update `FortificationAgent` in `app/Services/Agents/Phase3/FortificationAgent.php` — use system + user messages.
  - **Test**: Agent 12 LLM call includes system message.
  - **Files**: `app/Services/Agents/Phase3/FortificationAgent.php`
  - **Depends on**: T006, T007

### Arabic System Prompts for All 13 Agents

- [x] T032 [US2] Write Arabic system prompts for all 13 agents (0-12) in `app/Services/Orchestration/PromptBuilder.php` — each agent gets a unique 2-3 sentence Arabic persona defining its role as a specific Saudi legal expert (per DA-1).
  - **Test**: `buildSystemPrompt(N)` for N=0..12 each returns unique Arabic persona text. Agent 8 persona is a legal drafter. Agent 10 persona is a judge.
  - **Files**: `app/Services/Orchestration/PromptBuilder.php`
  - **Depends on**: T006

### End-to-End Validation

- [ ] T033 [US2] Playwright UI test: Create a case, run pipeline, verify Agent 8 output contains structured syllogisms (legal rule, case facts, conclusion), and agents demonstrate reasoning before producing output.
  - **Test**: Open case output, check defense arguments contain syllogistic structure and reference specific statute articles.
  - **Depends on**: T024-T032

**Checkpoint**: All agents use system + user messages. Chain-of-thought active for agents 5, 7, 8. Arabic personas defined for all 13 agents.

---

## Phase 5: User Story 3 — Accurate Law Identification via RAG (Priority: P2)

**Goal**: Agent 0 searches the RAG database for relevant statutes before identifying laws, grounding law identification in actual knowledge base.

**Independent Test**: Submit a labor dispute case, verify Agent 0 performs RAG search and identifies correct laws from seeded database.

### RAG Integration

- [x] T034 [US3] Add `VectorSearchService` as constructor dependency to `app/Services/Agents/Phase1AnalysisAgent.php` — inject via Laravel service container.
  - **Test**: `Phase1AnalysisAgent` can be instantiated with `VectorSearchService`. No errors on construction.
  - **Files**: `app/Services/Agents/Phase1AnalysisAgent.php`

- [x] T035 [US3] Implement keyword extraction from intake text in `app/Services/Agents/Phase1AnalysisAgent.php` — split Arabic text on whitespace, keep words > 3 chars, filter against legal keywords (per DA-3).
  - **Test**: Given Arabic intake text about labor dispute, extracts relevant keywords (عمل, فصل, عقد, etc.).
  - **Files**: `app/Services/Agents/Phase1AnalysisAgent.php`
  - **Depends on**: T034

- [x] T036 [US3] Add RAG search step to `Phase1AnalysisAgent::execute()` in `app/Services/Agents/Phase1AnalysisAgent.php` — after base context build, call `VectorSearchService::searchMultiple(keywords, topK=15, minSimilarity=0.60)`, format results as "المواد القانونية المرشحة من قاعدة المعرفة" section, append to context (per DA-3).
  - **Test**: Agent 0 context includes RAG results section. Law identification references statutes found via RAG search.
  - **Files**: `app/Services/Agents/Phase1AnalysisAgent.php`
  - **Depends on**: T035

### End-to-End Validation

- [ ] T037 [US3] Playwright UI test: Ensure RAG law library is seeded (`php artisan db:seed --class=LawLibrarySeeder`), create a labor dispute case, verify Agent 0 output references laws found in the RAG database.
  - **Test**: Case processing completes. Agent 0 output includes نظام العمل with specific articles from the seeded database.
  - **Depends on**: T034-T036

**Checkpoint**: Agent 0 uses RAG search for law identification. Downstream agents receive better law context.

---

## Phase 6: User Story 4 — Quality Gate Before Publication (Priority: P2)

**Goal**: Pipeline enforces programmatic quality checks before marking case "completed." Failed quality gate results in "completed_with_warnings" status.

**Independent Test**: Run pipeline with quality gate enabled, verify passing cases get correct status and failing cases get "completed_with_warnings."

### Quality Gate Implementation

- [x] T038 [US4] Add quality gate check in `app/Services/Orchestration/LegalOrchestrator.php` after Phase 2 Agent 9 — run `validateBriefStructure()`, `validateArabicFinalBrief()`, `validateNoEnglishLeak()` on post-processed brief. If all pass → status remains Phase2Completed. If any fail → log violations, set low_quality flag (per DA-8).
  - **Test**: Brief passing all checks → Phase2Completed status. Brief with English leak → violations logged.
  - **Files**: `app/Services/Orchestration/LegalOrchestrator.php`
  - **Depends on**: T009, T010, T020

- [x] T039 [US4] Add quality gate check in `app/Jobs/ProcessPhase3Job.php` after Agent 12 — run `FinalArabicBriefComposer::compose()`, run quality checks on composed brief. If all pass → `CaseStatus::Phase3Completed`. If any fail → `CaseStatus::CompletedWithWarnings` (per DA-8).
  - **Test**: Clean brief → Phase3Completed. Brief with violations → CompletedWithWarnings status.
  - **Files**: `app/Jobs/ProcessPhase3Job.php`
  - **Depends on**: T009, T010, T019, T021

### Critical Agent Halt Logic

- [x] T040 [US4] Implement critical agent halt logic in `app/Services/Orchestration/LegalOrchestrator.php` — for agents 6 (StatuteMatcher), 8 (LegalDrafter), 9 (QualityAssurance): on `self_correction_exhausted` → set `CaseStatus::Halted`, emit `pipeline.halted` SSE event, log violations, return early. Non-critical agents (1,2,3,4,5,7) keep current best-effort behavior (per DA-9).
  - **Test**: Simulate Agent 8 self-correction exhaustion → case status is Halted, pipeline stops, no Agent 9 execution.
  - **Files**: `app/Services/Orchestration/LegalOrchestrator.php`

### Agent 9 Fallback

- [x] T041 [US4] Implement Agent 9 fallback in `app/Services/Agents/Phase2/QualityAssuranceAgent.php` — if `---FINAL_BRIEF_V2---` marker not found in output, check for `08_final_brief.md`, copy as base v2, apply fixes from `09_fixes_applied.json`, run `BriefPostProcessor::process()`, save as `09_final_brief_v2.md` (per DA-10).
  - **Test**: Agent 9 output missing v2 marker + Agent 8 brief exists → v2 brief is constructed from v1 + fixes.
  - **Files**: `app/Services/Agents/Phase2/QualityAssuranceAgent.php`
  - **Depends on**: T018

### End-to-End Validation

- [ ] T042 [US4] Playwright UI test: Create and run a full case, verify case status is `phase3_completed` (not `completed_with_warnings`) when output is clean. Optionally verify logging of quality gate results.
  - **Test**: Pipeline completes, case shows correct final status.
  - **Depends on**: T038-T041

**Checkpoint**: Quality gate enforced at both Phase 2 and Phase 3 boundaries. Critical agents halt on failure. Agent 9 has fallback for missing markers.

---

## Phase 7: User Story 5 — Consistent Agent Persona & Behavior (Priority: P3)

**Goal**: Every agent has a dedicated Arabic legal expert identity via system messages, producing consistent formal Arabic output.

**Independent Test**: Run same case, verify all 13 agents use system messages and output consistent Arabic legal prose.

### Verification & Polish

- [x] T043 [US5] Verify all 13 agent system prompts are unique and role-appropriate in `app/Services/Orchestration/PromptBuilder.php` — Agent 0 (case analyst), Agent 1 (evidence examiner), Agent 2 (timeline builder), Agent 3 (statute researcher), Agent 4 (chain of custody), Agent 5 (law manager), Agent 6 (statute matcher), Agent 7 (defense strategist), Agent 8 (legal drafter), Agent 9 (QA), Agent 10 (judge), Agent 11 (devil's advocate), Agent 12 (fortification).
  - **Test**: Each `buildSystemPrompt(N)` for N=0..12 contains unique persona text. No two agents share the same system prompt.
  - **Files**: `app/Services/Orchestration/PromptBuilder.php`
  - **Depends on**: T032

- [x] T044 [US5] Add logging in `app/Services/Agents/Phase2/Phase2BaseAgent.php` to log message structure (system + user roles) for each agent LLM call — helps verify system messages are being sent.
  - **Test**: Laravel logs show each agent's call with `system` and `user` role messages.
  - **Files**: `app/Services/Agents/Phase2/Phase2BaseAgent.php`
  - **Depends on**: T027

- [ ] T045 [US5] Playwright UI test: Create and process a case, check Laravel logs during processing — verify each agent logs its model and message structure showing both system and user roles.
  - **Test**: All 13 agents log system + user message structure.
  - **Depends on**: T043, T044

**Checkpoint**: All agents have unique Arabic personas. Message structure is logged and verifiable.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Cross-story testing, provider compatibility, and final validation.

- [ ] T046 [P] Test system message architecture with both OpenRouter and Puter providers — create a case using each provider, verify pipeline completes without errors and system messages are handled correctly by both.
  - **Test**: OpenRouter case → completes. Puter case → completes. Both produce Arabic-only output.
  - **Depends on**: T027, T028, T029-T031

- [ ] T047 [P] Test BriefPostProcessor on existing case outputs — retrieve existing case briefs from database, run through `BriefPostProcessor::process()`, verify cleanup is correct and no content is lost.
  - **Test**: Existing briefs are cleaned without losing Arabic legal content.
  - **Depends on**: T018

- [ ] T048 Full end-to-end Playwright UI test: Create a case with Arabic intake, upload document, run Phase 1 → approve laws → Phase 2 → Phase 3, verify final output is pure Arabic, has 8 sections, starts with بسم الله, contains legal syllogisms, case status is `phase3_completed`.
  - **Test**: Complete pipeline from case creation to final brief display. All quality criteria met.
  - **Depends on**: All previous tasks

- [ ] T049 Run quickstart.md validation — follow all steps in `.specify/specs/009-pipeline-output-quality/quickstart.md` to verify the feature works as documented.
  - **Test**: All quickstart steps pass without issues.
  - **Depends on**: T048

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories
- **US1 (Phase 3)**: Depends on Foundational (T004-T013)
- **US2 (Phase 4)**: Depends on Foundational (T004-T008 specifically)
- **US3 (Phase 5)**: Depends on Foundational (T004-T008)
- **US4 (Phase 6)**: Depends on US1 (T009-T011, T018-T021)
- **US5 (Phase 7)**: Depends on US2 (T027, T032)
- **Polish (Phase 8)**: Depends on all user stories complete

### User Story Dependencies

- **US1 (P1 — Arabic Output)**: Can start after Foundational → independent
- **US2 (P1 — Reasoning Depth)**: Can start after Foundational → independent of US1
- **US3 (P2 — RAG Integration)**: Can start after Foundational → independent of US1/US2
- **US4 (P2 — Quality Gate)**: Depends on US1 (needs validators and post-processor)
- **US5 (P3 — Agent Persona)**: Depends on US2 (needs system messages implemented)

### Within Each User Story

- SKILL.md changes before PromptBuilder changes
- Service implementations before pipeline integration
- Pipeline integration before end-to-end testing
- Core implementation before validation tasks

### Parallel Opportunities

**Phase 2 parallel tasks**: T009, T010 (validators), T012, T013 (context caps) can all run in parallel

**US1 + US2 + US3 can start in parallel after Phase 2**:
- US1: SKILL.md + BriefPostProcessor + integration
- US2: Chain-of-thought + system messages + personas
- US3: RAG integration into Agent 0

**Within US2**: T024, T025, T026 (chain-of-thought for agents 5, 7, 8) can run in parallel. T029, T030, T031 (Phase3 agent updates) can run in parallel.

---

## Parallel Example: User Story 1

```bash
# SKILL.md changes (can run in parallel — different sections):
Task T014: "Update SKILL.md Agent 8 section"
Task T015: "Update SKILL.md Agent 9 section"
Task T016: "Update SKILL.md Agent 12 section"

# After SKILL.md changes, service + integration:
Task T018: "Implement BriefPostProcessor"
Task T017: "Update PromptBuilder Agent 8 template" (parallel with T018)

# After T018, integration tasks:
Task T020: "Integrate post-processor after Agent 9"
Task T021: "Integrate post-processor after Agent 12" (parallel with T020)
```

## Parallel Example: User Story 2

```bash
# Chain-of-thought instructions (all parallel — same file but different sections):
Task T024: "Add CoT to Agent 5 system prompt"
Task T025: "Add CoT to Agent 7 system prompt"
Task T026: "Add CoT to Agent 8 system prompt"

# Phase3 agent updates (all parallel — different files):
Task T029: "Update JudgeAgent"
Task T030: "Update DevilsAdvocateAgent"
Task T031: "Update FortificationAgent"
```

---

## Implementation Strategy

### MVP First (US1 + US2)

1. Complete Phase 1: Setup (T001-T003)
2. Complete Phase 2: Foundational (T004-T013)
3. Complete Phase 3: US1 — Pure Arabic Output (T014-T023)
4. **STOP and VALIDATE**: Run Playwright E2E test, verify Arabic-only output
5. Complete Phase 4: US2 — Reasoning Depth (T024-T033)
6. **STOP and VALIDATE**: Verify system messages + chain-of-thought working

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. Add US1 → Test → Arabic output working (MVP!)
3. Add US2 → Test → Reasoning depth improved
4. Add US3 → Test → RAG-grounded law identification
5. Add US4 → Test → Quality gate enforced
6. Add US5 → Test → Agent personas consistent
7. Polish → Full E2E validation

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story is independently testable after completion
- Playwright UI MCP used for E2E validation tasks (T023, T033, T037, T042, T045, T048)
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- All SKILL.md changes must happen before corresponding PromptBuilder changes
