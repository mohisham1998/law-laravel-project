# Data Model: Arabic Output Quality & System Message Alignment

**Branch**: `010-arabic-output-quality` | **Date**: 2026-03-28

---

## Schema Changes

**None.** This feature makes no database schema changes. All changes are to:
- Markdown content (SKILL.md)
- PHP string literals (PromptBuilder templates, format strings)
- PHP validation logic (OutputValidator)
- PHP controller logic (AgentSystemMessageController)
- TypeScript test code (Playwright)

---

## Existing Entities (Reference)

### LawArticle (law_articles table)

| Field | Type | Used By | Change |
|-------|------|---------|--------|
| `id` | bigint PK | — | None |
| `law_registry_id` | varchar | `queryRAGForStatutes()` format string | **Removed from format string** (still stored in DB) |
| `article_number` | varchar | `queryRAGForStatutes()` format string | Kept (used as Arabic article number) |
| `article_text` | text | `queryRAGForStatutes()` format string | Kept (first 500 chars) |
| `lawRegistry.name` | via relation | `queryRAGForStatutes()` format string | Kept (Arabic law name) |

### AgentSystemMessageOverride (if table exists)

| Field | Type | Used By | Change |
|-------|------|---------|--------|
| `agent_number` | int | `getAgentPersona()` override lookup | None |
| `system_message` | text | Returned when override exists | None |

The override mechanism is already functional. No schema changes required.

---

## State Transitions (Agent Pipeline — unchanged)

```
Phase 1: Agent 0 (Intake Analysis)
    → outputs: 00_intake_analysis.md

Phase 2: Agents 1–9 (sequential)
    → Agent 1: 01_chronology.md
    → Agent 2: 02_legal_issues.md
    → Agent 3: 03_chain_of_custody.md   ← RAG context fix applies here
    → Agent 4: 04_witness_analysis.md
    → Agent 5: 05_evidence_matrix.md
    → Agent 6: 06_legal_strategy.md
    → Agent 7: 07_legal_arguments.md
    → Agent 8: 08_final_brief.md         ← Template appendix fix applies here
    → Agent 9: 09_violations.md

Phase 3: Agents 10–12 (manual trigger)
    → Agent 10: 10_enhanced_brief.md
    → Agent 11: 11_refined_brief.md
    → Agent 12: 12_fortified_brief.md    ← Agent 12 template fix applies here
    → Agent 12: 13_final_brief_v3.md
```

---

## Prompt Content Structure (Post-Fix)

### System Prompt (what agent sees as system role)

```
[SKILL.md General Rules — Arabic after fix]
[SKILL.md Agent N section — Arabic after fix]
[Anti-Hallucination Rules — already Arabic]
[Output Template — already Arabic, appendix added for Agent 8]
```

### Portal Display (what operator sees)

```
buildSystemPrompt(N) = getAgentPersona(N) + CoT rules
                     = [full Arabic behavioral spec — expanded after fix]
                     + [chain-of-thought reasoning rules]
```

### RAG Law Context (what Agent 3 sees — post-fix)

```
Before: - **نظام الإثبات** المادة 71 (law_registry_id: LAW_001): نص المادة...
After:  - **نظام الإثبات** المادة 71: نص المادة...
```

---

## Output Quality Checklist (Final Brief Requirements)

The following is the validation state after this feature:

| Check | Validator | Status After Fix |
|-------|-----------|-----------------|
| Starts with بسم الله | `validateBriefStructure()` | Required (unchanged) |
| Ordinal sections (أولاً → سادساً) OR named sections | `validateBriefStructure()` | Required (unchanged) |
| Three-tier requests (أصلية / احتياطية / تبعية) | `validateBriefStructure()` | Required (unchanged) |
| Appendix section (الملاحق) | `validateBriefStructure()` | **Changed from optional to required** |
| Zero English in body prose | Manual / Playwright test | Enforced by improved prompts |
| Zero emojis | Manual / Playwright test | Enforced by improved prompts |
| Full decree citation format | Agent 8 template | Enforced by expanded template |
| Hijri dates in full Arabic words | Agent 8 template | Enforced by expanded template |
