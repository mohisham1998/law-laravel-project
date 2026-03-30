# Research: Pipeline Reliability & Quality Enforcement

**Feature**: 005-pipeline-reliability
**Date**: 2026-03-24

---

## R-001: Current Pipeline Failure Behavior

**Decision**: Pipeline must halt on agent failure (not continue silently).

**Rationale**: The current implementation in `LegalOrchestrator::runPhase2()` explicitly continues to the next agent when one fails (line 287: "Do NOT pause or return"). This directly contradicts SKILL.md line 47 which states: "After 3 exhausted attempts, **pause the pipeline** and emit an SSE event." The implementation must be corrected to match the SKILL.md contract. Additionally, `ProcessPhase3Job` has the same pattern (line 126: "Do NOT throw").

**Alternatives considered**:
- Continue-with-warning: Rejected because downstream agents depend on all prior outputs as cumulative context; missing context produces unreliable analysis.
- Partial halt (skip non-critical agents): Rejected because agent dependency is linear — every agent's input is the sum of all prior agents' outputs.

---

## R-002: Low-Confidence Output Visibility

**Decision**: Surface confidence warnings through existing AgentExecution records + UI components.

**Rationale**: `Phase2BaseAgent` already tracks `self_correction_exhausted`, `corrections_count`, and `correction_details` in the agent result. The orchestrator already saves `corrections_count` and `correction_details` to `AgentExecution`. However, the confidence score itself is NOT persisted to the database — it's only checked during self-correction validation. The `case-insights.blade.php` component computes a synthetic confidence from completion ratio, not from actual agent confidence scores. The fix requires: (1) persisting actual confidence scores to `AgentExecution`, (2) adding a `below_threshold` boolean flag, (3) displaying per-agent warnings in the timeline and case detail views.

**Alternatives considered**:
- Separate warnings table: Rejected — AgentExecution already has the right granularity (one row per agent per case).
- Toast/notification only: Rejected — warnings must persist and be visible on revisit, not just at completion time.

---

## R-003: Pipeline Execution Timeout

**Decision**: Add a pipeline-level wall-clock timeout of 30 minutes (configurable), checked before each agent starts.

**Rationale**: Currently there are two timeout layers: (1) per-agent PCNTL timeout of 180 seconds in `executeWithTimeout()`, (2) per-job queue timeout of 600 seconds in ProcessPhase2Job. Neither bounds total pipeline execution time across all agents. A case with 9 agents, each taking 170 seconds, would run for ~25 minutes without hitting either timeout. With retries, it could exceed 60 minutes. The pipeline-level timeout should be checked before starting each new agent. If exceeded, the pipeline halts gracefully — no need to kill a running agent (the per-agent timeout handles that).

**Alternatives considered**:
- Increase job timeout: Rejected — job timeout is a blunt instrument that kills the entire process, losing all progress.
- Per-phase timeout: Rejected — adds unnecessary complexity; a single pipeline timeout is simpler and sufficient.

---

## R-004: Retry System Unification

**Decision**: Consolidate into a 3-tier retry hierarchy with a shared case-level budget.

**Rationale**: Four independent retry systems currently operate:

| Layer | Current Config | Location |
|-------|---------------|----------|
| Queue job retries | 5 attempts, 60s backoff | ProcessPhase2Job `$tries` |
| Agent retries (orchestrator) | 3 attempts, no backoff | LegalOrchestrator `$maxRetries` |
| Self-correction retries | 3 attempts, sequential | Phase2BaseAgent `MAX_CORRECTION_ATTEMPTS` |
| API retries | 3 attempts, 2s delay | OpenRouterService `$retryAttempts` |

Worst case for a single agent: 5 × 3 × 3 × 4 = 180 API calls. Across 9 agents: 1,620 API calls.

The unified policy keeps all 4 layers but adds a shared budget:
- API retries (layer 4) remain unchanged — transparent HTTP-level resilience.
- Self-correction retries (layer 3) remain unchanged — quality validation mechanism.
- Agent retries (layer 2) draw from a case-level budget (default: 10 total).
- Job retries (layer 1) are reduced to 2 — only for catastrophic failures (OOM, process crash), not for recoverable agent errors.

**Alternatives considered**:
- Single retry layer: Rejected — each layer handles a different failure mode (network, quality, agent logic, process crash).
- Remove job retries entirely: Rejected — needed for process-level crashes that the orchestrator cannot catch.

---

## R-005: Case Status Model Changes

**Decision**: Add `halted` and `timed_out` case statuses; add `skipped` agent status.

**Rationale**: Current `CaseStatus` enum has `Failed` but no distinction between "failed due to agent error" and "failed due to timeout" or "halted for quality reasons." The user needs to see different messaging and retry options for each. The `AgentStatus` enum needs `Skipped` for agents that were never started due to an upstream halt.

Current statuses preserved:
- `Phase1Processing`, `Phase2Processing`, `Phase3Processing` — still used during normal execution
- `Failed` — still used for catastrophic failures (credentials, network down)
- `CompletedWithWarnings` — repurposed for cases that complete but have low-confidence outputs

New statuses:
- `Halted` — pipeline stopped due to agent failure (retryable)
- `TimedOut` — pipeline stopped due to execution time limit (retryable)

---

## R-006: Resume-from-Failure Mechanism

**Decision**: Extend existing `resume_from_agent` field to work for halt and timeout scenarios.

**Rationale**: The `LegalCase` model already has `resume_from_agent` (integer, nullable) and `LegalOrchestrator::runPhase2()` already supports `$startFromAgent` parameter. The orchestrator already cleans up stale outputs when resuming. This mechanism just needs to be set correctly when a halt or timeout occurs, and the retry UI needs to trigger it.

**Alternatives considered**:
- Checkpoint system with snapshots: Rejected — over-engineered. The existing file-based output system already serves as checkpoints.

---

## R-007: UI Warning Display Strategy

**Decision**: Enhance existing `agent-timeline-live.blade.php` and `case-insights.blade.php` with confidence warnings; add status badges to `cases/index.blade.php`.

**Rationale**: Constitution Principle VI mandates "No New Pages." The timeline already shows corrections count. Adding confidence score and threshold warning is a natural extension. The case list already shows status — adding visual distinction for halted/timed-out/warnings is a badge color change.

**Key UI changes**:
- Timeline: Show amber warning icon + confidence score on low-confidence agents
- Case insights: Replace synthetic confidence with actual per-agent confidence data
- Case list: Amber badge for `CompletedWithWarnings`, red badge for `Halted`/`TimedOut`
- Case show page: Banner explaining halt/timeout with retry button

---

## R-008: SKILL.md Compliance

**Decision**: The implementation will align with SKILL.md's stated behavior for self-correction exhaustion.

**Rationale**: SKILL.md line 47 states: "After 3 exhausted attempts, **pause the pipeline** and emit an SSE event with Arabic Retry/Cancel options." The current implementation ignores this and continues. This feature brings the implementation into compliance with the authoritative agent behavior specification.

No SKILL.md changes are needed — the code must match what SKILL.md already defines.
