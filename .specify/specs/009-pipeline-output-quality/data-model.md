# Data Model: 009-pipeline-output-quality

**Date**: 2026-03-27

## Existing Entities (No Schema Changes)

This feature modifies behavior, not data structures. No new database tables or columns are required.

### CaseStatus Enum (existing — used for quality gate)

| Value | Usage in This Feature |
|-------|----------------------|
| `phase2_completed` | Set when Phase 2 passes quality gate |
| `phase3_completed` | Set when Phase 3 passes quality gate |
| `completed_with_warnings` | Set when pipeline completes but quality gate fails |
| `halted` | Set when critical agent (6, 8, 9) exhausts self-correction |

### CaseOutput Model (existing — used for brief storage)

| Field | Type | Usage in This Feature |
|-------|------|----------------------|
| `content` | text | Stores post-processed Arabic brief (markdown outputs) |
| `content_type` | string | Used to filter: `markdown` for display |
| `agent_number` | int | Identifies which agent produced the output |
| `filename` | string | e.g., `09_final_brief_v2.md`, `13_final_brief_v3.md` |

### AgentExecution Model (existing — used for tracking)

| Field | Type | Usage in This Feature |
|-------|------|----------------------|
| `confidence_score` | float | Quality gate checks this value |
| `self_correction_exhausted` | boolean | Triggers pipeline halt for critical agents |
| `corrections_count` | int | Tracked for quality metrics |

## New Service Classes (No DB Impact)

### BriefPostProcessor

**Purpose**: Deterministic PHP cleanup of brief output
**Input**: Raw brief string (markdown)
**Output**: Cleaned brief string (pure Arabic markdown)
**Operations**:
- Strip `CASE:{ref}` and `LAW:{ref}` markers → replace with Arabic fallback text
- Remove confidence scores (regex pattern)
- Remove agent metadata headers
- Remove `⚠️ غير مُسنَّدة` paragraphs
- Validate Arabic character ratio ≥ 95%
- Validate 8-section structure
- Ensure preamble (بسم الله الرحمن الرحيم) as first line

### FinalArabicBriefComposer

**Purpose**: Select best brief version and produce final output
**Input**: Case model with outputs
**Output**: Single clean Arabic brief
**Logic**: Select v3 (Agent 12) > v2 (Agent 9) > v1 (Agent 8), apply BriefPostProcessor

## State Transitions

```
Phase 2 Complete:
  IF quality_gate_passes → CaseStatus::Phase2Completed
  IF quality_gate_fails  → CaseStatus::CompletedWithWarnings

Phase 3 Complete:
  IF quality_gate_passes → CaseStatus::Phase3Completed
  IF quality_gate_fails  → CaseStatus::CompletedWithWarnings

Critical Agent Exhaustion (6, 8, 9):
  IF self_correction_exhausted → CaseStatus::Halted
  (pipeline stops, no downstream agents execute)
```
