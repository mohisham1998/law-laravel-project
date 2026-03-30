# Data Model: Legal-Counsel System

**Feature**: `001-legal-counsel-system` | **Date**: 2026-03-19

## Existing Models (14) — Changes Required

### LegalCase (cases table)

**Status**: MODIFY — add Phase 3 status values, add `resume_from_agent` field

| Field | Type | Change | Notes |
|-------|------|--------|-------|
| `id` | uuid | — | PK |
| `user_id` | bigint FK | — | → users |
| `title` | string(500) | — | |
| `intake_text` | text | — | |
| `status` | string (enum) | MODIFY | Add Phase 3 completion statuses (already exist in enum) |
| `phase` | tinyint | — | 1, 2, or 3 |
| `current_agent` | tinyint | — | Currently executing agent number |
| `progress_percentage` | tinyint | — | 0-100 |
| `resume_from_agent` | tinyint | ADD | Agent number to resume from (null = start fresh) |
| `skill_version` | string | — | |
| `skill_hash` | string(64) | — | |
| `model_used` | string | — | |
| `total_tokens` | bigint | — | |
| `total_cost_usd` | decimal(10,4) | — | |
| `started_at` | timestamp | — | |
| `completed_at` | timestamp | — | |
| `last_failed_phase` | string | — | Phase name for retry |
| `last_error_message` | text | — | |
| `deleted_at` | timestamp | — | Soft delete |

**Status transitions**:
```
phase1_pending → phase1_processing → phase1_completed → awaiting_laws
  → phase2_pending → phase2_processing → phase2_completed
    → phase3_pending → phase3_processing → phase3_completed
Any state → failed / paused / cancelled
failed / paused → phase*_pending (retry)
```

**Relationships**: user, documents, outputs, agentExecutions, metrics, errorLogs, requiredLaws, evidenceEntries

---

### CaseOutput (case_outputs table)

**Status**: MODIFY — add `output_type` for structured vs prose distinction

| Field | Type | Change | Notes |
|-------|------|--------|-------|
| `id` | bigint | — | PK |
| `case_id` | uuid FK | — | → cases |
| `agent_number` | tinyint | — | 0-12 |
| `filename` | string | MODIFY | Updated filenames per spec (e.g., `02_chunks.jsonl`) |
| `file_path` | string(500) | — | Disk path |
| `content_type` | string(20) | — | `json`, `jsonl`, `markdown`, `text` |
| `content` | text | — | Full content (prose outputs) |
| `content_json` | json | — | Structured content (JSON outputs) |
| `file_size` | bigint | — | |
| `output_type` | string(20) | ADD | `primary`, `supplementary`, `structured` |

**New filenames per agent**:

| Agent | Outputs (filenames) |
|-------|-------------------|
| 0 (Phase 1) | `00_required_laws.md` |
| 1 (Lead Counsel) | `01_lead_plan.md`, `01_acceptance_criteria.json` |
| 2 (Evidence) | `02_chunks.jsonl`, `02_ingestion_report.md` |
| 3 (Integrity) | `03_statutes_index.jsonl`, `03_conflict_warnings.md`, `03_chain_of_custody_summary.md` |
| 4 (Timeline) | `04_timeline.json`, `04_timeline.md`, `04_entities_index.md` |
| 5 (Law Lead) | `05_issues_to_statutes.md`, `05_procedural_notes.md`, `05_adversary_evidence_analysis.md`, `05_matching_guidelines.json` |
| 6 (Statute Matcher) | `06_statutes_map.jsonl`, `06_accepted_matches.md`, `06_rejections.md`, `06_gaps_and_todo.md` |
| 7 (Strategy) | `07_risk_matrix.md`, `07_defense_layers.md`, `07_charges_scenarios.json`, `07_mitigation_opportunities.md` |
| 8 (Drafter) | `08_defense_arguments.md`, `08_final_brief.md`, `08_arguments_index.json` |
| 9 (QA) | `09_QA_summary.md`, `09_violations.md`, `09_fixes_applied.json`, `09_todo_back_to_agents.md`, `09_final_brief_v2.md` |
| 10 (Judge) | `10_judge_notes.md` |
| 11 (Devil's Advocate) | `11_devils_advocate_notes.md` |
| 12 (Fortification) | `12_fortification_plan.md`, `12_responses_to_judge.md`, `12_counter_arguments.md`, `13_final_brief_v3.md` |

---

### AgentExecution (agent_executions table)

**Status**: MODIFY — add correction tracking

| Field | Type | Change | Notes |
|-------|------|--------|-------|
| all existing fields | — | — | Keep as-is |
| `corrections_count` | tinyint | ADD | Number of self-corrections performed (0-3) |
| `correction_details` | json | ADD | Array of correction descriptions |

---

### ErrorLog (error_logs table)

**Status**: MODIFY — add `lesson_learned` field (may already exist), ensure all spec error types supported

Current error types: `low_confidence`, `missing_reference`, `abrogated_statute`, `temporal_contradiction`, `gate_validation_failure`, `api_timeout`, `api_error`

**Additional error types needed**:
- `quote_mismatch` — quoted text doesn't match statute index
- `missing_dual_citation` — paragraph lacks CASE or LAW reference
- `self_correction_exhausted` — 3 retries failed

---

### RequiredLaw (required_laws table)

**Status**: MODIFY — add RAG source tracking

| Field | Type | Change | Notes |
|-------|------|--------|-------|
| all existing fields | — | — | Keep as-is |
| `law_registry_id` | bigint FK | ADD | → law_registry (links to RAG source) |
| `subject_area` | string | ADD | Law subject classification from RAG |
| `is_uploaded` | boolean | DEPRECATE | No longer used as pipeline gate; kept for backward compat |

---

### Models Unchanged

The following models require no schema changes:
- **User** — existing fields sufficient
- **CaseDocument** — existing fields sufficient
- **CaseLaw** — deprecated for pipeline but table preserved
- **CaseMetrics** — existing fields sufficient
- **EvidenceRepositoryEntry** — existing fields sufficient
- **LawRegistry** — existing fields sufficient
- **LawFile** — existing fields sufficient
- **LawArticle** — existing fields sufficient
- **LawEmbedding** — existing fields sufficient

## New File-Based Data (Not Database)

### Error Memory File

**Path**: `storage/app/cases/{case_id}/memory/errors_log.md`
**Purpose**: Persistent error memory readable by agent prompts
**Created**: Automatically on first error detection
**Format**: Markdown with structured entries (see research.md R-005)

### Agent Output Files

**Path**: `storage/app/cases/{case_id}/outputs/`
**Purpose**: Inter-agent data exchange files
**Files**: See CaseOutput table above for complete list per agent
**Lifecycle**: Overwritten on re-processing; checked for existence by pipeline resume

## Entity Relationship Summary

```
User (1) ──→ (N) LegalCase
LegalCase (1) ──→ (N) CaseDocument
LegalCase (1) ──→ (N) CaseOutput
LegalCase (1) ──→ (N) AgentExecution ──→ (N) ErrorLog
LegalCase (1) ──→ (1) CaseMetrics
LegalCase (1) ──→ (N) RequiredLaw ──→ (0..1) LawRegistry
LegalCase (1) ──→ (N) EvidenceRepositoryEntry ──→ (1) CaseDocument

LawRegistry (1) ──→ (N) LawFile ──→ (N) LawArticle ──→ (1) LawEmbedding
```

## Migration Plan

New migration file: `2026_03_19_000001_add_legal_counsel_fields.php`

```
Schema changes:
1. cases: add `resume_from_agent` (tinyint, nullable)
2. case_outputs: add `output_type` (string, default 'primary')
3. agent_executions: add `corrections_count` (tinyint, default 0)
4. agent_executions: add `correction_details` (json, nullable)
5. required_laws: add `law_registry_id` (bigint, nullable, FK → law_registry)
6. required_laws: add `subject_area` (string, nullable)
```

No tables dropped. No destructive changes. Fully backward compatible.
