---

description: "Task list for Arabic Output Quality & System Message Alignment"
---

# Tasks: Arabic Output Quality & System Message Alignment

**Input**: Design documents from `/specs/010-arabic-output-quality/`
**Branch**: `010-arabic-output-quality`

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: User story (US1=Court-Ready Brief, US2=Portal Messages, US3=RAG Context, US4=Playwright Test)

---

## Phase 1: Setup

**Purpose**: No new infrastructure needed — this feature is pure code modification + one new test file.

- [x] T001 Verify Docker app container is running and `php artisan` works via `docker compose exec -T app php artisan --version`
- [x] T002 Confirm `agent-system-messages.md` exists at project root (authoritative Arabic reference for all agent specs)

---

## Phase 2: Foundational — SKILL.md Arabic Conversion (Blocking Prerequisite)

**Purpose**: Convert `.agent/skills/legal-counsel/SKILL.md` from English to Arabic. This is the single foundation for all user stories — every LLM prompt currently embeds English from SKILL.md.

**⚠️ CRITICAL**: All user story work depends on this conversion being complete first.

- [x] T003 Read `.agent/skills/legal-counsel/SKILL.md` in full to understand current English structure (General Rules, Agent 0–12 sections, Anti-Hallucination Rules)
- [x] T004 Read `agent-system-messages.md` in full to extract Arabic behavioral specs for all 13 agents
- [x] T005 Rewrite `.agent/skills/legal-counsel/SKILL.md` in Arabic: translate `## General Rules` section to Arabic `## القواعد العامة`, translate `## Anti-Hallucination Rules` to `## قواعد منع الهلوسة`, and for each `### Agent N: Name` section write the full Arabic behavioral spec from `agent-system-messages.md`
- [x] T006 Verify SKILL.md contains zero English section headers or English keywords (grep for common English words: "you are", "must", "agent", "output", "rules")

**Checkpoint**: SKILL.md is fully Arabic — all LLM prompts will now carry Arabic instructions

---

## Phase 3: User Story 1 — Court-Ready Arabic Brief (Priority: P1) 🎯 MVP

**Goal**: Running the sample case produces a final brief matching the desired output sample in structure, citation richness, language purity, and courtroom readiness.

**Independent Test**: Run full 13-agent pipeline on sample case → read `13_final_brief_v3.md` → confirm: starts with بسم الله, contains appendix with timeline and cited articles, zero English in body prose, full decree citation format, Hijri dates in full Arabic words.

### Implementation for User Story 1

- [x] T007 [US1] Read `app/Services/Orchestration/PromptBuilder.php` in full (all 830+ lines) to understand current `templateAgent8()`, `getAgentPersona(8)`, `templatePhase3(12)`, and `validateBriefStructure()` signatures
- [x] T008 [US1] Read `app/Services/Orchestration/OutputValidator.php` in full to understand current validation logic and `validateBriefStructure()` method
- [x] T009 [US1] Update `PromptBuilder::templateAgent8()` in `app/Services/Orchestration/PromptBuilder.php`: add mandatory appendix block requiring (1) `## ملحق ١: مسرد الوقائع الزمني` with chronological events list and (2) `## ملحق ٢: المواد النظامية المستشهد بها` with full article text and decree citation for each cited article
- [x] T010 [US1] Update `PromptBuilder::templateAgent8()` to explicitly require: full decree citation format (`من المقرر نظاماً بصريح المادة (X) من نظام Y الصادر بالمرسوم الملكي رقم (م/Z) لعام [سنة هجرية بالكلمات] أنه: «...»`), document reference prose format (`وقد ثبت بالمستند الرسمي رقم (N) المستخرج من [الجهة] بتاريخ [التاريخ الهجري بالكلمات] أن [الواقعة]`), and Hijri dates in full Arabic words (no numerals)
- [x] T011 [US1] Update `PromptBuilder::templatePhase3(12)` (or equivalent Agent 12 template method) in `app/Services/Orchestration/PromptBuilder.php`: add instruction to preserve both appendix sections from Agent 8/10/11 output and enrich them if additional articles or events are discovered
- [x] T012 [US1] Update `OutputValidator::validateBriefStructure()` in `app/Services/Orchestration/OutputValidator.php`: change appendix from optional to required — add check that brief contains `ملحق` or `الملاحق` heading; flag as violation if absent
- [x] T013 [US1] Verify Agent 8 persona in `PromptBuilder::getAgentPersona(8)` already contains full Arabic behavioral spec matching `agent-system-messages.md` Agent 8 section; update if needed

**Checkpoint**: Agent 8 and 12 templates require appendix; validator enforces it; pipeline will produce compliant brief

---

## Phase 4: User Story 2 — System Messages Match SKILL.md in Portal (Priority: P2)

**Goal**: The portal system message editor shows the full Arabic behavioral specification for each agent, not a 2-3 sentence stub.

**Independent Test**: Open portal agent editor for Agent 0 and Agent 8 → each displays the complete Arabic behavioral spec including citation rules and output structure instructions — not a short persona sentence.

### Implementation for User Story 2

- [x] T014 [US2] Read `app/Http/Controllers/AgentSystemMessageController.php` in full to understand current `show()` method
- [x] T015 [US2] Expand `PromptBuilder::getAgentPersona()` in `app/Services/Orchestration/PromptBuilder.php` for agents 0–7 and 9–11: replace 2-3 sentence stubs with full Arabic behavioral specifications from `agent-system-messages.md` for each agent (case 0 through case 11, skipping 8 which is already expanded)
- [x] T016 [US2] Fix `AgentSystemMessageController::show()` in `app/Http/Controllers/AgentSystemMessageController.php`: change return from `getAgentPersona($agentNumber)` to `buildSystemPrompt($agentNumber)` so the portal displays the complete system prompt (persona + CoT rules)
- [x] T017 [US2] Verify Agent 12 persona in `PromptBuilder::getAgentPersona(12)` matches the updated `agent-system-messages.md` Agent 12 section; update if needed

**Checkpoint**: Portal editor shows full Arabic spec for all agents 0–12

---

## Phase 5: User Story 3 — RAG Context Pure Arabic (Priority: P3)

**Goal**: The law context passed to Agent 3 contains no English field names or internal identifiers.

**Independent Test**: Run pipeline → inspect Agent 3 input context → confirm no `law_registry_id`, `LAW_00x`, or English labels in the Arabic law text block.

### Implementation for User Story 3

- [x] T018 [US3] Read `app/Services/Agents/Phase2/ChainOfCustodyAgent.php` — specifically `queryRAGForStatutes()` method — to confirm the exact format string with English labels
- [x] T019 [US3] Update `ChainOfCustodyAgent::queryRAGForStatutes()` in `app/Services/Agents/Phase2/ChainOfCustodyAgent.php`: change format string from `"- **%s** المادة %s (law_registry_id: %s): %s\n"` to `"- **%s** المادة %s: %s\n"` and remove the `$article->law_registry_id` argument from the `sprintf()` call
- [x] T020 [US3] Verify no other methods in `ChainOfCustodyAgent.php` or other agent files embed English labels in Arabic law context (grep for `law_registry_id`, `LAW_`, `file_label` in agent service files)

**Checkpoint**: RAG context contains zero English labels; Agent 3 receives pure Arabic law articles

---

## Phase 6: User Story 4 — Playwright E2E Test (Priority: P4)

**Goal**: A Playwright test suite creates a case from the sample case, runs all 13 agents, and asserts Arabic quality criteria on the final brief.

**Independent Test**: Run the Playwright test suite → it passes, confirming sample case produces a final brief matching the Arabic quality checklist.

### Implementation for User Story 4

- [x] T021 [US4] Check if `tests/playwright/` or `tests/e2e/` directory exists; if not, create `tests/playwright/` directory
- [x] T022 [US4] Read `sample case/intake.txt`, `sample case/documents/` directory listing, and `sample case/laws/` directory listing to know exact file names and content for test fixtures
- [x] T023 [US4] Read the portal case creation form (`resources/views/pages/cases/create.blade.php`) and the case show page (`resources/views/pages/cases/show.blade.php`) to understand UI element IDs, form fields, and SSE event patterns
- [x] T024 [US4] Write `tests/playwright/arabic-quality.spec.ts` with the following test flow:
  1. Navigate to case creation form
  2. Fill in case title and description from sample case intake
  3. Upload all documents from `sample case/documents/`
  4. Upload all laws from `sample case/laws/`
  5. Submit form and wait for Phase 1 agent to complete (watch for SSE event or UI indicator)
  6. Wait for Phase 2 agents 1–9 to complete
  7. Trigger Phase 3 via POST to the manual endpoint
  8. Wait for Phase 3 agents 10–12 to complete
  9. Assert final brief file exists and starts with `بسم الله الرحمن الرحيم`
  10. Assert brief contains ordinal Arabic section headings (أولاً through سادساً)
  11. Assert brief contains appendix section heading (`ملحق` or `الملاحق`)
  12. Assert brief contains three-tier requests (الطلبات الأصلية, الطلبات الاحتياطية, الطلبات التبعية)
  13. Assert brief body prose contains zero English words (regex: no ASCII word characters in prose sections)
- [x] T025 [US4] Configure Playwright test runner: check if `playwright.config.ts` exists at project root; if not, create minimal config pointing to `tests/playwright/` with `baseURL` from `APP_URL` env variable and timeout of 5 minutes per test (pipeline is slow)

**Checkpoint**: Playwright test suite exists and is executable

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Smoke test and final verification

- [x] T026 [P] Run full pipeline manually on sample case via Docker: create test case, start pipeline, verify `13_final_brief_v3.md` output meets all acceptance criteria from spec.md US1
- [x] T027 [P] Verify zero English words remain in updated SKILL.md using grep
- [x] T028 [P] Verify portal agent editor displays full Arabic spec for Agent 0 (briefest agent) and Agent 8 (most detailed) via Playwright MCP browser navigation

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2 — SKILL.md)**: Depends on Phase 1 — **BLOCKS all user stories**
- **US1 (Phase 3)**: Depends on Phase 2 (SKILL.md) — highest priority, do first
- **US2 (Phase 4)**: Depends on Phase 2 (SKILL.md) — can run in parallel with US1 after Phase 2
- **US3 (Phase 5)**: Independent of US1/US2 — can run in parallel after Phase 2
- **US4 (Phase 6)**: Depends on US1, US2, US3 all complete — validates all fixes
- **Polish (Phase 7)**: Depends on all user stories complete

### User Story Dependencies

- **US1 (P1)**: Start after SKILL.md converted (Phase 2)
- **US2 (P2)**: Start after SKILL.md converted (Phase 2), independent of US1
- **US3 (P3)**: Independent of US1/US2, only needs Phase 2 complete
- **US4 (P4)**: Must come last — tests all previous stories end-to-end

### Parallel Opportunities

After Phase 2 completes:
- T009–T013 (US1 template changes) can run with T014–T017 (US2 portal changes) — **different files**
- T018–T020 (US3 RAG fix) can run in parallel with US1 and US2 — **different files**
- T021–T025 (US4 Playwright) starts only after US1, US2, US3 are done

---

## Parallel Example: After Phase 2 (SKILL.md converted)

```bash
# US1: PromptBuilder template changes
Task T009: Update templateAgent8() appendix requirement
Task T010: Update templateAgent8() citation format

# US2: PromptBuilder persona expansion (different method, same file — do sequentially within US)
Task T015: Expand getAgentPersona() for agents 0–7, 9–11

# US3: ChainOfCustodyAgent RAG fix (different file — parallel with US1 and US2)
Task T019: Fix queryRAGForStatutes() format string
```

---

## Implementation Strategy

### MVP First (User Story 1 + 3 Only)

1. Complete Phase 1: Setup verification
2. Complete Phase 2: SKILL.md conversion (CRITICAL)
3. Complete Phase 3: US1 — Agent 8 template + appendix (core output quality)
4. Complete Phase 5: US3 — RAG context fix (quick, one-line change)
5. **STOP and VALIDATE**: Run pipeline on sample case, check final brief

### Incremental Delivery

1. Phase 1 + 2 → SKILL.md in Arabic
2. Phase 3 (US1) → Brief has appendix, full citations → Core value delivered
3. Phase 4 (US2) → Portal shows correct messages → Operator transparency
4. Phase 5 (US3) → RAG context pure Arabic → Context quality fix
5. Phase 6 (US4) → Playwright test → Regression prevention

---

## Notes

- [P] tasks = different files, no dependencies on in-progress tasks
- SKILL.md conversion (Phase 2) is the critical path — all improvements flow from it
- US1 (Agent 8 template) has the most direct impact on output quality
- US3 (RAG fix) is a one-line change with no risk
- US4 (Playwright) is last because it validates everything else end-to-end
- Avoid: modifying `PromptBuilder.php` simultaneously in two tasks — do US1 and US2 changes to that file sequentially
