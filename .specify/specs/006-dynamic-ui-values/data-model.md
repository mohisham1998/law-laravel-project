# Data Model - Dynamic UI Values Feature

## Overview

This document describes the existing data model entities used by the Dynamic UI Values feature. No new entities or modifications are required - the feature leverages existing models.

## Entities

### LegalCase

**Purpose**: Core entity representing a legal case

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `user_id` | foreignKey | Owner |
| `title` | string | Case title |
| `intake_text` | text | Case description |
| `status` | enum | CaseStatus (phase1_pending, phase1_processing, etc.) |
| `phase` | integer | Current phase (1, 2, 3) |
| `progress_percentage` | integer | Overall progress 0-100 |
| `model_used` | string | AI model identifier |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Relationships**:
- `documents()` - HasMany CaseDocument
- `agentExecutions()` - HasMany AgentExecution
- `caseLaws()` - HasMany CaseLaw
- `outputs()` - HasMany CaseOutput

### AgentExecution

**Purpose**: Tracks individual agent runs within a case

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `case_id` | foreignKey | LegalCase |
| `agent_number` | integer | 0-12 (13 agents) |
| `status` | enum | pending, running, completed, failed, paused |
| `progress_percentage` | integer | 0-100 |
| `started_at` | timestamp | |
| `completed_at` | timestamp | |
| `error_message` | text | If failed |

**Relationships**:
- `case()` - BelongsTo LegalCase

### CaseDocument

**Purpose**: Stores uploaded documents linked to cases

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `case_id` | foreignKey | LegalCase |
| `filename` | string | Original filename |
| `file_path` | string | Storage path |
| `file_size` | integer | Bytes |
| `mime_type` | string | |
| `created_at` | timestamp | |

**Relationships**:
- `case()` - BelongsTo LegalCase

### CaseOutput

**Purpose**: Contains analysis outputs from agents

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `case_id` | foreignKey | LegalCase |
| `agent_number` | integer | Source agent |
| `output_type` | string | e.g., "analysis", "brief", "timeline" |
| `filename` | string | Output file name |
| `content` | text | If small output |
| `created_at` | timestamp | |

**Relationships**:
- `case()` - BelongsTo LegalCase

### CaseLaw

**Purpose**: Links cases to matched laws/regulations

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `case_id` | foreignKey | LegalCase |
| `law_registry_id` | foreignKey | LawRegistry |
| `relevance_score` | float | 0-1 |
| `created_at` | timestamp | |

**Relationships**:
- `case()` - BelongsTo LegalCase
- `law()` - BelongsTo LawRegistry

## State Transitions

### Case Status
```
phase1_pending → phase1_processing → phase2_pending → phase2_processing → phase3_pending → phase3_processing → phase2_completed / phase3_completed
                              ↓                                ↓
                         phase1_failed                    phase2_failed
                              ↓                                ↓
                            paused                           paused
```

### Agent Execution Status
```
pending → running → completed
                ↓
              failed

pending → paused → running (resume)
```

## Queries Used by Feature

### AI Analysis Page Data
```php
// Get case with all related data
$case = LegalCase::with(['documents', 'agentExecutions', 'caseLaws'])
    ->findOrFail($caseId);

// Stage states from agent executions
$stages = $case->agentExecutions()
    ->orderBy('agent_number')
    ->get()
    ->groupBy('phase');
```

### Dashboard Statistics
```php
// Active cases count
$activeCases = LegalCase::whereIn('status', ['phase1_processing', 'phase2_processing', 'phase3_processing'])
    ->count();

// Monthly cases
$monthlyCases = LegalCase::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
    ->where('created_at', '>=', now()->subMonths(6))
    ->groupBy('month')
    ->get();
```

## No New Fields Required

The existing schema has all fields needed for this feature:
- `progress_percentage` on LegalCase
- `status` and `progress_percentage` on AgentExecution
- Document and law count relationships already exist