# Quickstart: Pipeline Quality Overhaul

**Date**: 2026-03-26 | **Feature**: 007-pipeline-quality-overhaul

## Implementation Order

This feature is implemented in 4 phases, each leaving the system in a working state:

### Phase A: Foundation (PromptBuilder + Config)
1. Rewrite `PromptBuilder.php` — SKILL.md section extraction
2. Add per-agent config block to `config/legal.php`
3. Fix Phase 1 `max_tokens` from 150 to 4096
4. **Test**: Submit a case, verify Phase 1 produces meaningful output

### Phase B: Agent Prompt Refactoring (All 13 Agents)
5. Update `Phase2BaseAgent.php` — config-driven temperature/max_tokens
6. Refactor all Phase 2 agents (1-9) — focused prompts + output templates
7. Refactor Phase 3 agents (10-12) — focused prompts + temperature fixes
8. **Test**: Submit a case through full pipeline, verify output structure

### Phase C: Deterministic Validation
9. Create `OutputValidator.php` — all validation methods
10. Integrate OutputValidator into `Phase2BaseAgent::executeWithSelfCorrection()`
11. Update `GateValidator.php` — add deterministic phase-gate checks
12. **Test**: Submit a case, verify invalid citations are caught and re-run

### Phase D: RAG Context & Polish
13. Update RAG context delivery — statute subsetting, boundary instructions
14. Add few-shot examples to critical agents (5, 6, 8)
15. **Test**: Full end-to-end pipeline test via Playwright MCP

## Verification

After each phase, test via UI:
1. Navigate to http://localhost:8000/cases/create
2. Submit a case with test intake text
3. Monitor via http://localhost:8000/ai-analysis/{case_id}
4. Verify agent outputs in case detail view

## Key Files to Read First

1. `.agent/skills/legal-counsel/SKILL.md` — source of truth (617 lines)
2. `app/Services/Orchestration/PromptBuilder.php` — current generic prompt builder
3. `app/Services/Agents/Phase2/Phase2BaseAgent.php` — base agent with context/validation
4. `config/legal.php` — current config (no per-agent settings yet)
