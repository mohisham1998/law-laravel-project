# Research: Arabic Output Quality & System Message Alignment

**Branch**: `010-arabic-output-quality` | **Date**: 2026-03-28

---

## Research Questions Resolved

### R-001: How does PromptBuilder assemble the full LLM prompt?

**Finding**: `PromptBuilder::buildPromptForAgent(N, context)` assembles prompts in this order:
1. `## General Rules` section from SKILL.md (English)
2. `### Agent N: [Name]` section from SKILL.md (English)
3. Anti-hallucination rules (hardcoded in `buildAntiHallucinationRules()` — Arabic)
4. Output template from `templateAgentN()` (Arabic)
5. Context variables (Arabic)

SKILL.md content is read from `.agent/skills/legal-counsel/SKILL.md` via `getSkillContent()` and inserted verbatim. This means every English word in SKILL.md appears in the prompt sent to the LLM.

**Decision**: Convert SKILL.md to Arabic entirely.

---

### R-002: What does the portal system message editor currently show?

**Finding**: `AgentSystemMessageController::show()` calls `PromptBuilder::getAgentPersona(N)` which returns:
- Agents 0–7, 9–11: A 2-3 sentence Arabic persona stub (e.g., "أنت محلل قانوني متخصص في...")
- Agent 8: Multi-paragraph Arabic persona with citation examples
- Agent 12: Multi-paragraph Arabic persona

The `show()` method does NOT call `buildSystemPrompt()` which would return `getAgentPersona() + "\n\n" + buildCoTRules()`. The portal shows an incomplete view.

**Decision**: Fix `show()` to return `buildSystemPrompt(N)` so the portal reflects the complete prompt header.

---

### R-003: What English labels appear in the RAG law context?

**Finding**: `ChainOfCustodyAgent::queryRAGForStatutes()` (lines 108-113) uses this format:
```
- **{lawRegistry.name}** المادة {article_number} (law_registry_id: {law_registry_id}): {article_text}
```

Example actual output:
```
- **نظام الإثبات** المادة 71 (law_registry_id: LAW_001): من المقرر نظاماً أنه...
```

The `(law_registry_id: LAW_001)` substring is entirely English and appears in the context block passed to Agent 3.

**Decision**: Remove `(law_registry_id: %s)` from format string. New format:
```
- **{lawRegistry.name}** المادة {article_number}: {article_text}
```

---

### R-004: What does the desired output appendix look like?

**Finding**: `28-3-update/desired-output-sample.md` contains two appendix sections:
```
## ملحق ١: مسرد الوقائع الزمني
[Chronological event list with Hijri dates in full Arabic words]

## ملحق ٢: المواد النظامية المستشهد بها
[Each cited article with full decree citation + full article text]
```

The current `templateAgent8()` template has no appendix block. The validator comment says "الملاحق (appendices) is optional".

**Decision**: Add mandatory appendix block to `templateAgent8()`. Update validator to require `الملاحق` heading. Update Phase 3 (Agent 12) template to preserve appendix sections.

---

### R-005: What is the structure of agent-system-messages.md?

**Finding**: `agent-system-messages.md` at project root is a comprehensive Arabic reference with full behavioral specs for all 13 agents (0–12). Each agent section contains:
- Role description (الدور)
- Behavioral rules
- Output format requirements
- Citation format examples (for relevant agents)

This file was created in a prior session as the authoritative reference for Arabic system messages. It is NOT currently used by any code.

**Decision**: Use `agent-system-messages.md` as the source of truth for expanding `getAgentPersona()` for agents 0–7, 9–11.

---

### R-006: Does an override mechanism exist for agent system messages?

**Finding**: `AgentSystemMessageController` has `store()` and `update()` methods that save overrides to database (table `agent_system_message_overrides` or similar). `PromptBuilder::getAgentPersona()` checks for saved overrides before returning the default. This means operator overrides already work at the code level — the only issue is that the portal display was showing an incomplete default.

**Decision**: No changes needed to the override mechanism. Fixing `show()` to return `buildSystemPrompt(N)` is sufficient.

---

### R-007: Where should the Playwright test file live?

**Finding**: No `tests/playwright/` or `tests/e2e/` directory exists currently. The project has `tests/` with PHPUnit tests. Playwright is available via MCP server. The test should be a standalone Node.js/TypeScript file that uses Playwright directly, placed in a new `tests/playwright/` directory.

**Decision**: Create `tests/playwright/arabic-quality.spec.ts`. The test uses Playwright to drive the browser UI end-to-end.

---

## Summary of All Decisions

| ID | Decision | Files Affected |
|----|----------|----------------|
| D-001 | Convert SKILL.md to Arabic | `.agent/skills/legal-counsel/SKILL.md` |
| D-002 | Expand `getAgentPersona()` for agents 0–7, 9–11 | `app/Services/Orchestration/PromptBuilder.php` |
| D-003 | Fix `show()` to return `buildSystemPrompt(N)` | `app/Http/Controllers/AgentSystemMessageController.php` |
| D-004 | Remove English labels from RAG format string | `app/Services/Agents/Phase2/ChainOfCustodyAgent.php` |
| D-005 | Add mandatory appendix to Agent 8 template | `app/Services/Orchestration/PromptBuilder.php` |
| D-006 | Make appendix required in validator | `app/Services/Orchestration/OutputValidator.php` |
| D-007 | Update Agent 12 template to preserve appendix | `app/Services/Orchestration/PromptBuilder.php` |
| D-008 | Create Playwright E2E test | `tests/playwright/arabic-quality.spec.ts` |
