# Implementation Plan: Pipeline Reliability & Quality Enforcement

**Branch**: `005-pipeline-reliability` | **Date**: 2026-03-24 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/005-pipeline-reliability/spec.md`

## Summary

Fix critical reliability and quality issues in the legal case agent pipeline by enforcing strict halt-on-failure behavior, surfacing low-confidence outputs to users, adding pipeline-level timeouts, and unifying the four independent retry systems under a shared budget. The core change is converting the pipeline from "continue on failure" to "halt on failure with clear user communication," aligning the implementation with the existing SKILL.md contract.

## Technical Context

**Language/Version**: PHP 8.x / Laravel 11
**Primary Dependencies**: Laravel queues (database driver), OpenRouter API (Guzzle HTTP), Redis (SSE events), Blade + Alpine.js + Tailwind CSS (frontend)
**Storage**: SQLite (dev) / MySQL (prod) + local disk (case output files)
**Testing**: PHPUnit (existing `tests/Feature/CaseDashboardTest.php` + manual testing)
**Target Platform**: Linux server (Docker) / Windows (dev)
**Project Type**: Web application (Laravel monolith)
**Performance Goals**: Pipeline completes within 30 minutes; SSE events delivered within 100ms
**Constraints**: Must preserve existing self-correction, cumulative context passing, error logging, and SSE streaming
**Scale/Scope**: Single-user to small-team usage; 9 agents in Phase 2, 3 in Phase 3

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Real-Time First | PASS | All halt/timeout/warning events use existing SSE infrastructure. New events (`pipeline.halted`, `agent.low_confidence`, `pipeline.timeout_warning`) follow established patterns. No polling introduced. |
| II. Zero-Cache UI | PASS | No new static assets. UI changes are in Blade templates served dynamically. |
| III. Self-Testing After Every Change | PASS | Quickstart.md includes 4 manual test scenarios. Implementation phases will verify each change. |
| IV. Human-Readable Output Always | PASS | All user-facing messages are in Arabic (العربية). Error messages explain what went wrong and what the user can do (retry/review). No raw JSON or stack traces exposed. |
| V. Agent Logic From SKILL.md | PASS | This feature CORRECTS the implementation to match SKILL.md line 47: "After 3 exhausted attempts, pause the pipeline." No agent logic is being invented. |
| VI. No New Pages | PASS | All UI changes enhance existing pages: `cases/show.blade.php`, `cases/index.blade.php`, `agent-timeline-live.blade.php`, `case-insights.blade.php`. No new page files created. |
| VII. General Development Standards | PASS | Simple, maintainable approach: additive migration, minimal new code, existing patterns followed. Each implementation phase ends with a working state. Config via environment variables. |

**Post-Phase 1 re-check**: All gates still pass. No new pages introduced. All events use SSE. All messages in Arabic.

## Project Structure

### Documentation (this feature)

```text
specs/005-pipeline-reliability/
├── plan.md              # This file
├── research.md          # Phase 0 output — 8 research decisions
├── data-model.md        # Phase 1 output — schema changes
├── quickstart.md        # Phase 1 output — setup & testing guide
├── contracts/
│   └── sse-events.md    # Phase 1 output — new/modified SSE event contracts
└── tasks.md             # Phase 2 output (created by /speckit.tasks)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── CaseStatus.php          # Add Halted, TimedOut values
│   └── AgentStatus.php         # Add Skipped value
├── Models/
│   ├── LegalCase.php           # Add new fields, update canRetry()
│   └── AgentExecution.php      # Add confidence_score, below_threshold, self_correction_exhausted
├── Services/
│   ├── Orchestration/
│   │   └── LegalOrchestrator.php   # Core: halt-on-failure, timeout, retry budget
│   ├── Agents/Phase2/
│   │   └── Phase2BaseAgent.php     # Persist confidence score, signal exhaustion
│   ├── CaseEventService.php        # New events: pipeline.halted, agent.low_confidence, pipeline.timeout_warning
│   └── OpenRouter/
│       └── OpenRouterService.php   # No changes (API retries preserved as-is)
├── Jobs/
│   ├── ProcessPhase2Job.php        # Reduce job retries to 2, set pipeline_started_at
│   └── ProcessPhase3Job.php        # Apply same halt-on-failure pattern
├── Http/Controllers/
│   └── CaseController.php         # Update retry action for new halt/timeout statuses
config/
└── legal.php                      # Add pipeline_timeout_minutes, retry_budget_per_case
database/migrations/
└── 2026_03_24_000001_add_pipeline_reliability_fields.php
resources/views/
├── components/
│   ├── agent-timeline-live.blade.php   # Confidence warnings per agent
│   └── case-insights.blade.php         # Real confidence data instead of synthetic
├── pages/cases/
│   ├── index.blade.php                 # Status badges for halted/timed_out/warnings
│   └── show.blade.php                  # Halt/timeout banner with retry button
```

**Structure Decision**: Existing Laravel monolith structure — all changes are modifications to existing files plus one new migration. No new directories or architectural patterns introduced.

## Complexity Tracking

No constitution violations. No complexity justifications needed.

## Design Decisions

### D-001: Halt Strategy — Pre-agent Check vs. Post-failure Halt

**Chosen**: Post-failure halt. When an agent fails after exhausting retries, the orchestrator sets the case to `Halted` and returns immediately. No subsequent agents are started.

**Why not pre-agent check**: The gate validator already checks for upstream outputs before each agent. But the current issue is that failures are caught and the loop continues. The fix is in the catch block, not the pre-check.

### D-002: Timeout Enforcement — Wall-clock Check Before Each Agent

**Chosen**: Check elapsed time before starting each new agent. If `now - pipeline_started_at > timeout`, halt with `TimedOut` status. The currently running agent is NOT killed mid-execution (the per-agent PCNTL timeout handles that).

**Why not mid-agent kill**: The per-agent timeout (180s) already handles runaway agents. The pipeline timeout only needs to prevent starting NEW agents when time is up.

### D-003: Confidence Score Extraction — From Self-Correction Result

**Chosen**: Extract confidence score in `Phase2BaseAgent::executeWithSelfCorrection()` during validation. The score is already parsed via regex (`"confidence": <float>`). Store it in the return array and persist to `AgentExecution`.

**Why not separate scoring step**: The score is already computed as part of self-correction validation. Adding a separate step would duplicate the regex parsing.

### D-004: CompletedWithWarnings — When All Agents Succeed But Some Are Low-Confidence

**Chosen**: After all agents complete, if any `AgentExecution` has `below_threshold = true`, set case status to `CompletedWithWarnings` instead of `Phase2Completed`. This status already exists in the enum.

**Why not a separate "quality check" step**: The data is already available after the agent loop. A simple query (`where below_threshold = true`) determines the final status.

### D-005: Retry Budget — Orchestrator-Level Counter

**Chosen**: The orchestrator tracks `retry_budget_used` on the `LegalCase` model. Each time an agent retry is attempted (not API retries, not self-correction retries), the counter increments. When `retry_budget_used >= retry_budget_max`, no more agent retries are allowed — the pipeline halts.

**Why not a separate budget service**: A counter on the case model is simpler and survives job restarts. No need for a service class for a single integer.

### D-006: Phase 3 — Same Pattern as Phase 2

**Chosen**: Apply the same halt-on-failure pattern to `ProcessPhase3Job`. Phase 3 agents (10, 11, 12) are sequential and depend on Phase 2 outputs + each other. The same logic applies: halt on failure, mark subsequent agents as skipped.

**Why not treat Phase 3 differently**: Phase 3 agents (Judge, Devil's Advocate, Fortification) build on each other's output. A missing judge review means the adversary attack has no target. Same dependency chain as Phase 2.

## Implementation Phases

### Phase A: Data Layer (Migration + Enums + Models)

**Files**: Migration, `CaseStatus.php`, `AgentStatus.php`, `LegalCase.php`, `AgentExecution.php`, `config/legal.php`

1. Create migration adding new columns to `agent_executions` and `legal_cases`
2. Add `Halted` and `TimedOut` to `CaseStatus` enum
3. Add `Skipped` to `AgentStatus` enum
4. Update `LegalCase` model: add new fields to `$fillable` and `$casts`, update `canRetry()` to include `Halted` and `TimedOut`
5. Update `AgentExecution` model: add new fields to `$fillable` and `$casts`
6. Add config keys: `pipeline_timeout_minutes`, `retry_budget_per_case`

**Verification**: Run migration, confirm columns exist, confirm enums compile.

### Phase B: Orchestrator Core Logic

**Files**: `LegalOrchestrator.php`, `Phase2BaseAgent.php`, `CaseEventService.php`

1. **LegalOrchestrator::runPhase2()** — Replace continue-on-failure with halt:
   - After agent retries exhausted → set case to `Halted`, set `halted_at_agent`, emit `pipeline.halted`, mark remaining agents as `Skipped`, return
   - Before each agent → check wall-clock timeout, halt with `TimedOut` if exceeded
   - Before each agent retry → check retry budget, halt if exhausted
   - At 80% timeout → emit `pipeline.timeout_warning`
   - After all agents complete → check for `below_threshold` executions → set `CompletedWithWarnings` if any

2. **Phase2BaseAgent::executeWithSelfCorrection()** — Persist confidence:
   - Extract confidence score from validation result
   - Return `confidence_score` and `below_threshold` in result array
   - When exhausted, set `self_correction_exhausted = true` in result

3. **CaseEventService** — Add new event methods:
   - `emitPipelineHalted($caseId, $agentNumber, $agentName, $reason, $completedAgents, $skippedAgents)`
   - `emitLowConfidence($caseId, $agentNumber, $agentName, $score, $threshold)`
   - `emitTimeoutWarning($caseId, $elapsedMin, $timeoutMin, $remainingMin, $currentAgent)`

**Verification**: Submit a test case, simulate failure, confirm pipeline halts and SSE events fire.

### Phase C: Phase 3 + Job Updates

**Files**: `ProcessPhase3Job.php`, `ProcessPhase2Job.php`

1. **ProcessPhase3Job** — Apply same halt-on-failure pattern:
   - Replace try/catch-continue with halt on failure
   - Mark subsequent agents as `Skipped`
   - Set case to `Halted` with `halted_at_agent`

2. **ProcessPhase2Job** — Update job config:
   - Reduce `$tries` from 5 to 2
   - Set `pipeline_started_at` before calling orchestrator
   - Initialize `retry_budget_max` from config

**Verification**: Test Phase 3 failure halts correctly. Test job retry reduction.

### Phase D: UI — Confidence Warnings & Status Badges

**Files**: `agent-timeline-live.blade.php`, `case-insights.blade.php`, `cases/index.blade.php`, `cases/show.blade.php`

1. **agent-timeline-live.blade.php** — Add confidence warning:
   - For agents with `below_threshold = true`: show amber warning icon, confidence score, threshold
   - For agents with `self_correction_exhausted = true`: show "best-effort" indicator

2. **case-insights.blade.php** — Replace synthetic confidence:
   - Use actual `confidence_score` from `AgentExecution` records
   - Show count of low-confidence agents
   - Display overall quality indicator

3. **cases/index.blade.php** — Status badges:
   - `Halted`: Red badge with "متوقف" (stopped)
   - `TimedOut`: Orange badge with "انتهت المهلة" (timed out)
   - `CompletedWithWarnings`: Amber badge with "مكتمل مع تحذيرات" (completed with warnings)

4. **cases/show.blade.php** — Halt/timeout banner:
   - When status is `Halted` or `TimedOut`: show prominent banner explaining what happened, which agent, and a retry button
   - Arabic text explaining the halt reason and next steps

**Verification**: Visual check of all 4 views with test data covering each status.

### Phase E: Resume & Retry Updates

**Files**: `CaseController.php`, `LegalCase.php`

1. **CaseController** — Update retry action:
   - Handle `Halted` and `TimedOut` statuses (in addition to existing `Failed`)
   - Set `resume_from_agent` to `halted_at_agent`
   - Reset `retry_budget_used` for new attempt
   - Clear halt fields

2. **LegalCase::canRetry()** — Include new statuses:
   - Allow retry from `Halted` and `TimedOut` (not just `Failed`)

**Verification**: Retry a halted case, confirm it resumes from the correct agent.
