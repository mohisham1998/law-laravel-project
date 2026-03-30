# Data Model: Pipeline Reliability & Quality Enforcement

**Feature**: 005-pipeline-reliability
**Date**: 2026-03-24

---

## Schema Changes

### 1. `agent_executions` table — New columns

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `confidence_score` | decimal(5,4) | yes | null | Actual confidence score extracted from agent output (0.0000–1.0000) |
| `below_threshold` | boolean | no | false | Whether confidence_score < configured threshold (0.70) |
| `self_correction_exhausted` | boolean | no | false | Whether all correction attempts were used and output is best-effort |

**Rationale**: Currently, confidence is computed synthetically in `case-insights.blade.php` from completion ratios. These columns store the actual per-agent confidence from the LLM output, enabling accurate per-agent quality warnings.

### 2. `legal_cases` table — New columns

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `pipeline_started_at` | timestamp | yes | null | Wall-clock time when the pipeline (Phase 2) began executing |
| `retry_budget_used` | integer | no | 0 | Total agent-level retries consumed across the pipeline for this case |
| `retry_budget_max` | integer | no | 10 | Maximum agent-level retries allowed (from config, stored per-case for consistency) |
| `halted_at_agent` | integer | yes | null | Agent number where pipeline halted (failure or timeout) |
| `halt_reason` | string(50) | yes | null | Why the pipeline halted: 'agent_failure', 'timeout', 'retry_budget_exhausted' |

**Rationale**: `pipeline_started_at` enables wall-clock timeout checking. `retry_budget_*` fields track the shared retry budget per case. `halted_at_agent` and `halt_reason` provide precise halt context for the UI and resume logic.

### 3. `CaseStatus` enum — New values

| Value | Description |
|-------|-------------|
| `Halted` = `'halted'` | Pipeline stopped due to agent failure — retryable from halted agent |
| `TimedOut` = `'timed_out'` | Pipeline stopped due to execution time limit — retryable |

**Existing values preserved**: All current enum values remain unchanged.

### 4. `AgentStatus` enum — New value

| Value | Description |
|-------|-------------|
| `Skipped` = `'skipped'` | Agent was not started because pipeline halted at an earlier agent |

**Existing values preserved**: `Pending`, `InProgress`, `Completed`, `Failed`, `Retrying` remain unchanged.

---

## Entity Relationships (unchanged)

```
LegalCase 1──* AgentExecution
LegalCase 1──* CaseOutput
LegalCase 1──* RequiredLaw
AgentExecution *──1 LegalCase
```

No new tables or relationships are introduced. All changes are additive columns on existing tables and new enum values.

---

## State Transitions

### Case Status Transitions (updated)

```
Phase2Processing ──[agent fails after retries]──→ Halted
Phase2Processing ──[timeout exceeded]──→ TimedOut
Phase2Processing ──[retry budget exhausted]──→ Halted
Phase2Processing ──[all agents complete, some low-confidence]──→ CompletedWithWarnings
Phase2Processing ──[all agents complete, all confident]──→ Phase2Completed

Phase3Processing ──[agent fails after retries]──→ Halted
Phase3Processing ──[timeout exceeded]──→ TimedOut

Halted ──[user retries]──→ Phase2Processing (or Phase3Processing)
TimedOut ──[user retries]──→ Phase2Processing (or Phase3Processing)
```

### Agent Status Transitions (updated)

```
Pending ──[pipeline halts before this agent]──→ Skipped
InProgress ──[completes with low confidence]──→ Completed (with below_threshold=true)
InProgress ──[fails after retries]──→ Failed
Failed ──[pipeline halts]──→ (subsequent agents become Skipped)
```

---

## Configuration (config/legal.php additions)

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `pipeline_timeout_minutes` | integer | 30 | Maximum wall-clock minutes for entire pipeline |
| `retry_budget_per_case` | integer | 10 | Maximum agent-level retries across all agents |
| `job_retries` | integer | 2 | Queue job retry attempts (reduced from 5) |

**Existing config preserved**: `confidence_threshold` (0.70), `agent_timeout_seconds` (180), `agent_max_retries` (3).

---

## Migration Plan

Single migration file: `2026_03_24_000001_add_pipeline_reliability_fields.php`

- Add columns to `agent_executions`: `confidence_score`, `below_threshold`, `self_correction_exhausted`
- Add columns to `legal_cases`: `pipeline_started_at`, `retry_budget_used`, `retry_budget_max`, `halted_at_agent`, `halt_reason`
- All new columns have defaults or are nullable — migration is backward-compatible with existing data
- No data migration needed — existing records retain their current values
