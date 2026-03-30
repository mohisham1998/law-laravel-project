# Data Model: Case Output Page Redesign

**Date**: 2026-03-22 | **Branch**: `002-case-output-redesign`

> This feature is a pure UI redesign — no database schema changes, no new models, no migrations. This document describes the **view-layer data contracts**: what PHP variables are passed to each Blade component and their expected shapes.

---

## Blade Component Data Contracts

### `pipeline-tracker.blade.php` (NEW)

| Variable | Type | Source | Description |
|----------|------|--------|-------------|
| `$case` | `LegalCase` | Controller | Full case model with loaded `agentExecutions` relation |
| `$statusVal` | `string` | Computed in `show.blade.php` | `$case->status->value ?? $case->status` |

**Computed in component**:

```php
$definitions = AgentDefinitions::all();  // 13 agents, static
$executionsByAgent = $case->agentExecutions
    ->keyBy('agent_number')
    ->map(fn($e) => [
        'status'       => $e->status,
        'started_at'   => $e->started_at?->toISOString(),
        'completed_at' => $e->completed_at?->toISOString(),
    ]);
```

Serialized to JS as `const executionsByAgent = @json($executionsByAgent);`

**Tracker bubble states** (derived, not stored):

| Input Status | Display State | Icon | Color |
|-------------|--------------|------|-------|
| `completed` | Filled green | `check_circle` | emerald-600 |
| `in_progress` | Pulsing amber | `progress_activity` | amber-500 |
| `retrying` | Pulsing amber | `refresh` | amber-500 |
| `failed` | Red | `error` | red-500 |
| `pending` / absent | Grey | `schedule` | slate-400 |

---

### `show.blade.php` variables (unchanged)

| Variable | Type | Eager Loaded |
|----------|------|-------------|
| `$case` | `LegalCase` | `outputs`, `agentExecutions`, `requiredLaws`, `documents` |

No new controller variables required. The `pipeline-tracker` and all components derive everything from `$case`.

---

### `agent-timeline-live.blade.php` (modified — no new variables)

Existing `$outputsByAgent` (keyed by `agent_number`, markdown only) and `$definitions` remain unchanged. Collapsed/expanded logic change is JS-only.

---

### `pdf-export-button.blade.php` (modified — no new variables)

`$case` prop and `$status` / `$enabled` PHP variables remain identical. Change is in the rendered HTML (button element instead of anchor, onclick handler).

---

## Entity Relationships (read-only, no changes)

```
LegalCase
  ├── hasMany CaseOutput (keyed by agent_number, content_type)
  ├── hasMany AgentExecution (keyed by agent_number, status)
  └── hasMany RequiredLaw

AgentDefinitions::all()  →  static array (no DB table)
  returns: number, phase (1|2|3), name (Arabic), name_en (English), outputs[], inputs[]
```

## State Machine Reference (LegalCase.status — read-only)

```
pending
  → phase1_pending → phase1_processing
    → awaiting_laws
      → phase2_pending → phase2_processing
        → phase2_completed
          → phase3_pending → phase3_processing
            → phase3_completed
  (any) → failed | paused
```

Tracker phase grouping:
- Phase 1: agent 0 (statuses: `phase1_*`)
- Phase 2: agents 1–9 (statuses: `phase2_*`, `awaiting_laws`)
- Phase 3: agents 10–12 (statuses: `phase3_*`)
