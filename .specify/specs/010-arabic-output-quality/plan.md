# Implementation Plan: Arabic Output Quality & System Message Alignment

**Branch**: `010-arabic-output-quality` | **Date**: 2026-03-28 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/010-arabic-output-quality/spec.md`

---

## Summary

Four root causes degrade output quality: (1) `SKILL.md` is entirely in English, contaminating every LLM prompt; (2) `getAgentPersona()` returns 2-3 sentence stubs for agents 0–7, 9–11, so the portal shows misleading system messages; (3) `ChainOfCustodyAgent::queryRAGForStatutes()` embeds English labels (`law_registry_id: LAW_001`) inside Arabic law text; (4) `templateAgent8()` does not require an appendix section and `validateBriefStructure()` explicitly marks الملاحق as optional.

The fix is a surgical four-pronged change: convert SKILL.md to Arabic, expand all `getAgentPersona()` stubs to full Arabic behavioral specs (using `agent-system-messages.md` as reference), strip English labels from the RAG format string, and add mandatory appendix requirements to Agent 8's template and the output validator. A Playwright E2E test is added to prevent regression.

---

## Technical Context

**Language/Version**: PHP 8.x / Laravel 11
**Primary Dependencies**: PromptBuilder (custom), OpenRouter API (Guzzle), Playwright (Node.js, MCP)
**Storage**: Local disk — `storage/app/cases/{id}/outputs/` (case outputs), SQLite (dev)
**Testing**: Playwright MCP (E2E), manual pipeline run for smoke test
**Target Platform**: Laravel application running in Docker (docker-compose)
**Project Type**: Web application (AI pipeline orchestration)
**Performance Goals**: Agent 8 produces valid brief on first attempt (SC-007: zero self-correction retries for clean case)
**Constraints**: All LLM prompt content in Arabic; zero English in final brief body; no new pages (Constitution VI)
**Scale/Scope**: 13-agent pipeline; changes affect 4 PHP files + 1 SKILL.md + 1 Playwright test file

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Real-Time First | ✅ PASS | No changes to SSE/streaming pipeline; agent execution flow unchanged |
| II. Zero-Cache UI | ✅ PASS | No new static assets; no cache configuration changes |
| III. Self-Testing After Every Change | ✅ PASS | Playwright E2E test suite required by FR-015; pipeline smoke test validates output |
| IV. Human-Readable Output | ✅ PASS | This feature directly improves output readability (pure Arabic, no JSON/emojis) |
| V. Agent Logic Comes From SKILL.md | ✅ PASS | SKILL.md is being updated first; all agent behavior derives from updated SKILL.md |
| VI. No New Pages | ✅ PASS | No new Blade views; portal editor already exists; Playwright test is a test file |
| VII. General Development Standards | ✅ PASS | Minimal changes; each file modified surgically |

**Verdict**: All gates pass. No violations.

---

## Project Structure

### Documentation (this feature)

```text
specs/010-arabic-output-quality/
├── plan.md              ← This file
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
├── quickstart.md        ← Phase 1 output
└── tasks.md             ← Phase 2 output (/speckit.tasks)
```

### Source Code (files modified by this feature)

```text
.agent/skills/legal-counsel/
└── SKILL.md                           ← Convert from English to Arabic

app/Services/Orchestration/
├── PromptBuilder.php                  ← Expand getAgentPersona() for agents 0–7, 9–11
└── OutputValidator.php                ← Make appendix (الملاحق) section mandatory

app/Services/Agents/Phase2/
└── ChainOfCustodyAgent.php            ← Remove English labels from queryRAGForStatutes()

app/Http/Controllers/
└── AgentSystemMessageController.php   ← Fix show() to return full behavioral spec

tests/Playwright/ (new file)
└── arabic-quality.spec.ts             ← E2E test: sample case → final brief quality
```

---

## Phase 0: Research

> All unknowns resolved. No `NEEDS CLARIFICATION` markers in spec.

### Decision Log

**D-001: SKILL.md conversion strategy**
- Decision: Rewrite SKILL.md in Arabic using `agent-system-messages.md` as the authoritative reference for content, preserving the same section structure (`## General Rules`, `### Agent N: Name`, `## Anti-Hallucination Rules`)
- Rationale: `agent-system-messages.md` already contains the desired Arabic behavioral specs for all 13 agents; SKILL.md's English content is embedded verbatim into every LLM prompt by `PromptBuilder::buildPromptForAgent()`
- Alternative rejected: Keeping SKILL.md in English and translating in `buildPromptForAgent()` — too complex, would break the single-source-of-truth principle (Constitution V)

**D-002: Portal system message fix strategy**
- Decision: Expand `getAgentPersona()` for all agents (0–12) to return the full Arabic behavioral spec; fix `AgentSystemMessageController::show()` to return `buildSystemPrompt(N)` instead of just `getAgentPersona(N)` — though since persona is being expanded, `show()` can remain on `getAgentPersona(N)` as long as persona is full
- Rationale: `buildSystemPrompt(N)` combines persona + CoT rules; the portal editor should show what the agent actually sees. Expanding `getAgentPersona()` also enriches the built prompt for all agents.
- Alternative rejected: Creating a separate `getFullSystemMessage()` method — unnecessary complexity; expanding the existing method is simpler

**D-003: RAG format string fix**
- Decision: Remove `(law_registry_id: %s)` entirely from the format string in `queryRAGForStatutes()`; format as `- **{law_name}** المادة {article_number}: {article_text}\n`
- Rationale: English labels serve no purpose for the LLM; the law name and article number in Arabic are sufficient identifiers
- Alternative rejected: Translating `law_registry_id` to Arabic — the ID itself (LAW_001) is still English; cleaner to remove entirely

**D-004: Appendix requirement strategy**
- Decision: Add mandatory appendix block to `templateAgent8()` with explicit instructions for two sections: (1) مسرد الوقائع الزمني and (2) المواد النظامية المستشهد بها. Update `validateBriefStructure()` to require presence of appendix heading. Update `templatePhase3(12)` to instruct Agent 12 to preserve and enrich appendix sections.
- Rationale: The desired output sample has two named appendix sections. Appendix was explicitly marked "optional" in the validator — changing to required is the direct fix.
- Alternative rejected: Relying on Agent 8 to include appendix without template guidance — the current output proves this doesn't happen without explicit instruction

**D-005: Playwright test approach**
- Decision: Use Playwright MCP tools to write a spec file at `tests/playwright/arabic-quality.spec.ts` (or `tests/e2e/arabic-quality.spec.ts` if the directory exists). The test: creates a case via form, uploads sample case documents, runs the pipeline (Phase 1 auto-runs; Phase 2 auto-runs; Phase 3 triggered via POST), waits for completion, reads final brief via API/filesystem, asserts quality criteria.
- Rationale: Playwright is already available via MCP; the portal has existing UI for case creation
- Alternative rejected: PHPUnit integration test — doesn't test the actual UI flow or real LLM output quality

---

## Phase 1: Design & Data Model

### Data Model

No new database schema changes are required. This feature modifies:
- Text content in PHP methods (prompt templates)
- A Markdown file (SKILL.md)
- A TypeScript test file (Playwright)

### Key Entities (unchanged, documented for reference)

| Entity | Storage | Relevant Fields | Change |
|--------|---------|-----------------|--------|
| Agent System Message | In-memory (PromptBuilder) | persona string, system prompt string | `getAgentPersona()` expanded |
| RAG Law Article | `law_articles` table | `law_registry_id`, `article_number`, `article_text`, `lawRegistry.name` | Format string in `queryRAGForStatutes()` changed |
| Case Output | `storage/app/cases/{id}/outputs/` | `13_final_brief_v3.md` | Improved content from better prompts |
| Agent Override | `agent_system_message_overrides` table (if exists) | `agent_number`, `system_message` | No schema change; override already respected |

### Interface Contracts

No new public APIs. The portal system message editor endpoint (`GET /agent-system-messages/{agent}`) already exists; its response payload changes from a 2-3 sentence stub to a full Arabic behavioral spec.

---

## Implementation Phases

### Phase A: SKILL.md Arabic Conversion (Foundation)

Convert `.agent/skills/legal-counsel/SKILL.md` from English to Arabic using `agent-system-messages.md` as reference. This is the foundation — all other prompt improvements build on this.

**Files**: `.agent/skills/legal-counsel/SKILL.md`
**Risk**: Low — content translation only; pipeline logic unchanged
**Verification**: Read the converted file; confirm no English section headers remain

### Phase B: PromptBuilder Persona Expansion

Expand `getAgentPersona()` in `PromptBuilder.php` for agents 0–7, 9–11. Each agent case should return a full Arabic behavioral specification matching the corresponding agent section in `agent-system-messages.md`. Agents 8 and 12 already have expanded personas — verify they match the updated `agent-system-messages.md`.

**Files**: `app/Services/Orchestration/PromptBuilder.php`
**Risk**: Medium — prompt changes affect all agent outputs; test with sample case
**Verification**: Portal shows full Arabic spec for Agent 8; pipeline run produces improved output

### Phase C: AgentSystemMessageController Fix

Fix `AgentSystemMessageController::show()` to return `buildSystemPrompt(N)` instead of `getAgentPersona(N)`, so the portal displays the complete system prompt the agent actually uses.

**Files**: `app/Http/Controllers/AgentSystemMessageController.php`
**Risk**: Low — UI display only; no pipeline logic change
**Verification**: Open portal agent editor for Agent 0; verify full spec shown

### Phase D: RAG Context Decontamination

In `ChainOfCustodyAgent::queryRAGForStatutes()`, remove `(law_registry_id: %s)` from the format string. New format: `- **{name}** المادة {number}: {text}\n`

**Files**: `app/Services/Agents/Phase2/ChainOfCustodyAgent.php`
**Risk**: Low — format string change; no logic change
**Verification**: Inspect Agent 3 input context in pipeline run; confirm no English labels

### Phase E: Agent 8 Template + Validator Update

1. In `PromptBuilder::templateAgent8()`: add mandatory appendix block requiring (1) مسرد الوقائع الزمني and (2) المواد النظامية المستشهد بها كاملةً
2. In `OutputValidator::validateBriefStructure()`: change appendix from optional to required (add check for `الملاحق` heading)
3. In `PromptBuilder::templatePhase3(12)`: add instruction to preserve and enrich appendix sections

**Files**: `app/Services/Orchestration/PromptBuilder.php`, `app/Services/Orchestration/OutputValidator.php`
**Risk**: Medium — template change affects Agent 8 output; validator change makes appendix a hard requirement
**Verification**: Final brief contains appendix sections; validator passes without triggering correction loop

### Phase F: Playwright E2E Test

Write Playwright test at `tests/playwright/arabic-quality.spec.ts` (or appropriate directory). Test flow:
1. Navigate to create case form
2. Fill in case details using sample case intake.txt
3. Upload all documents from `sample case/documents/`
4. Upload all laws from `sample case/laws/`
5. Submit and wait for Phase 1 completion (SSE)
6. Wait for Phase 2 completion (SSE)
7. Trigger Phase 3 via POST endpoint
8. Wait for Phase 3 completion
9. Assert final brief: starts with بسم الله, contains ordinal sections, contains appendix, zero English in body prose, three-tier requests

**Files**: `tests/playwright/arabic-quality.spec.ts` (new)
**Risk**: Medium — depends on running application and real LLM calls; assertions on brief content
**Verification**: Test passes in CI

---

## Dependency Order

```
Phase A (SKILL.md) → Phase B (PromptBuilder personas) → Phase C (Controller fix)
                                                       → Phase D (RAG fix)
                                                       → Phase E (Template + Validator)
                                                       → Phase F (Playwright test)
```

Phases C, D, E, F can run in parallel after B. Phase F validates all other phases.
