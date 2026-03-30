# Research: Pipeline Quality Overhaul

**Date**: 2026-03-26 | **Feature**: 007-pipeline-quality-overhaul

## R1: How to extract agent-specific sections from SKILL.md

**Decision**: Parse SKILL.md by `### Agent N:` headings to extract per-agent sections. Include the `## General Rules` section (lines 15–49) in every prompt alongside the agent-specific block.

**Rationale**: SKILL.md has a consistent structure — each agent has a `### Agent N: Name (Arabic)` heading followed by a table and `**Behavior:**` section, terminated by `---`. This can be parsed reliably with string/regex operations. The General Rules section (citations, confidence, anti-hallucination) applies universally and must always be included.

**Alternatives considered**:
- Hardcode prompts per agent → violates Constitution V ("Agent Logic Comes From SKILL.md")
- Parse by line ranges → brittle, breaks when SKILL.md is edited
- Use markdown parser library → over-engineered for heading-based extraction

**Implementation approach**:
1. `PromptBuilder::extractGeneralRules()` — extracts text from `## General Rules` to the next `## ` heading
2. `PromptBuilder::extractAgentSection(int $agentNumber)` — extracts from `### Agent {N}:` to the next `---` separator
3. `PromptBuilder::buildPromptForAgent()` — composes: General Rules + Agent Section + Context + Output Template
4. Cache parsed sections in memory per request (SKILL.md won't change mid-request)

---

## R2: Deterministic validation strategy

**Decision**: Create `OutputValidator` class with static methods for each validation type. Run after each critical agent (6, 8, 9) and at phase gates. Validators read actual upstream files from disk, not from LLM context.

**Rationale**: LLM-based QA (Agent 9) cannot reliably cross-check 100+ citations against a truncated index. PHP code can do exact string matching, JSON parsing, and set membership checks deterministically.

**Alternatives considered**:
- Enhance Agent 9's prompt to be more thorough → still LLM-based, still unreliable
- Add a separate validation agent → adds cost and latency, still LLM-based
- Post-pipeline validation only → errors propagate through downstream agents

**Validation checks to implement**:

| Check | Agent | Method |
|-------|-------|--------|
| `statute_id` exists in index | After Agent 6 | `validateStatuteIds($statutesMap, $statutesIndex)` |
| `quoted_text` is substring of source | After Agent 6 | `validateQuotedText($statutesMap, $statutesIndex)` |
| No abrogated articles cited | After Agent 6 | `validateNoAbrogated($statutesMap, $conflictWarnings)` |
| `LAW:{ref}` citations exist in accepted matches | After Agent 8 | `validateBriefCitations($brief, $statutesMap)` |
| Brief has all 8 mandatory sections | After Agent 8 | `validateBriefStructure($brief)` |
| Confidence >= 0.70 on all matches | After Agent 6 | `validateConfidenceFloor($statutesMap)` |
| Valid JSONL format | After Agents 2, 3, 6 | `validateJsonl($content)` |

---

## R3: Per-agent temperature and max_tokens calibration

**Decision**: Add `agents` config block to `config/legal.php` with per-agent overrides. Each agent reads its config at runtime via `config("legal.agents.{$agentNumber}")`.

**Rationale**: Current values are hardcoded in each agent class. Centralizing in config allows tuning without code changes, environment-specific overrides, and a single reference for all agent parameters.

**Calibrated values**:

| Agent | Role | Temperature | Max Tokens | Rationale |
|-------|------|-------------|------------|-----------|
| 0 | Phase 1 Analysis | 0.3 | 4096 | Was 150 — catastrophically low. 4096 allows full law identification. |
| 1 | Lead Counsel | 0.3 | 8192 | Strategic planning benefits from deterministic output. |
| 2 | Evidence Manager | 0.2 | 8192 | Chunking is mechanical — low creativity needed. |
| 3 | Chain of Custody | 0.2 | 8192 | Fingerprinting + statute retrieval — deterministic. |
| 4 | Timeline Extractor | 0.3 | 8192 | Date extraction benefits from moderate creativity. |
| 5 | Law Manager | 0.3 | 8192 | Issue mapping requires moderate interpretation. |
| 6 | Statute Matcher | 0.3 | 8192 | Was 0.2 — too rigid for creative matching. 0.3 balances accuracy with discovery. |
| 7 | Defense Strategist | 0.3 | 8192 | Strategy building needs moderate creativity. |
| 8 | Legal Drafter | 0.3 | 16384 | Long-form brief generation needs high token budget. |
| 9 | Quality Assurance | 0.2 | 16384 | QA needs deterministic rigor + space for full report. |
| 10 | Judge | 0.3 | 8192 | Judicial review — balanced. |
| 11 | Devil's Advocate | 0.3 | 8192 | Was 0.4 — too creative for rigorous legal argument. |
| 12 | Fortification | 0.3 | 16384 | Produces final brief — needs high token budget. |

---

## R4: RAG context delivery strategy

**Decision**: Instead of dumping and truncating the full `03_statutes_index.jsonl`, filter to relevant statutes using Agent 5's `05_matching_guidelines.json` priority list. If guidelines not yet available (for agents before Agent 5), use the full index but with a higher budget (80K chars instead of 40K). Always append a boundary instruction: "You may ONLY cite statutes listed above."

**Rationale**: Silent truncation causes agents to hallucinate statutes outside the visible window. Filtering by relevance ensures agents see the statutes they need. The boundary instruction makes the constraint explicit.

**Alternatives considered**:
- Pass all statutes without truncation → may exceed context window for large law libraries
- Use RAG search per-agent → duplicates work, inconsistent results
- Chunk the index into pages → agents can't cross-reference across pages

---

## R5: Few-shot example strategy

**Decision**: Embed one concrete few-shot example directly in the agent prompt for critical agents (5, 6, 8). Examples are short (5-15 lines), use placeholder data matching the schema from SKILL.md.

**Rationale**: Few-shot examples are the highest-leverage intervention for budget models. A single concrete example dramatically improves format compliance without consuming much context.

**Example for Agent 6 (Statute Matcher)**:
```jsonl
{"chunk_id":"DOC01_C003","statute_id":"LABOR_LAW_ART_80","article_no":"80","quoted_text":"يحق لصاحب العمل فسخ العقد دون مكافأة أو إشعار...","confidence":0.85,"match_type":"direct","abrogated":false}
{"chunk_id":"DOC01_C005","statute_id":"LABOR_LAW_ART_77","article_no":"77","quoted_text":"إذا أنهي العقد لسبب غير مشروع...","confidence":0.78,"match_type":"direct","abrogated":false}
```

**Example for Agent 8 (Legal Drafter)** — first 3 sections of brief structure showing citation format.

---

## R6: Self-correction integration with deterministic validation

**Decision**: Modify `executeWithSelfCorrection()` in Phase2BaseAgent to run deterministic validation AFTER the LLM-based `validateOutput()`. Deterministic violations are treated as critical and trigger re-runs with the specific violation details appended to the prompt.

**Rationale**: Current self-correction only catches surface formatting issues. Adding deterministic checks before accepting output ensures hallucinated citations trigger re-runs with clear error messages (e.g., "statute_id LABOR_LAW_ART_999 does not exist in 03_statutes_index.jsonl").

**Flow**:
1. Agent produces output (streaming)
2. `validateOutput()` — existing LLM-format checks (JSONL validity, confidence)
3. `OutputValidator::validateAgent{N}()` — deterministic cross-checks
4. If violations found → append to prompt, retry (up to 3 total attempts)
5. If 3 attempts exhausted → pause pipeline per SKILL.md rules

---

## R7: Backward compatibility approach

**Decision**: All changes are additive — new config keys have defaults matching current behavior. The `OutputValidator` runs alongside existing validation, not replacing it. PromptBuilder changes only affect how prompts are assembled, not the agent execution flow.

**Rationale**: Existing case data (stored outputs, case records) must remain valid. No database schema changes. No output file naming changes.
