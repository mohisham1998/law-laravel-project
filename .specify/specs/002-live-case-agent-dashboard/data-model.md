# Data Model: Live Case Agent Dashboard

**Feature**: 002-live-case-agent-dashboard  
**Date**: 2026-03-16

---

## Existing Models (No Changes Required)

### AgentExecution

Already exists and tracks agent execution state.

| Field | Type | Description |
|-------|------|-------------|
| id | uuid | Primary key |
| case_id | uuid | FK to legal_cases |
| agent_number | integer | 0-11 (Phase 1 = 0, Phase 2 = 1-9, Phase 3 = 10-11) |
| agent_name | string | Arabic display name |
| status | enum | pending, processing, completed, failed |
| started_at | timestamp | When agent started |
| completed_at | timestamp | When agent finished |
| tokens_used | integer | OpenRouter tokens consumed |
| output_file | string | Path to output file |
| error_message | text | Error details if failed |
| retry_count | integer | Number of retry attempts |
| confidence_score | decimal | 0.00 to 1.00 |
| created_at | timestamp | Record creation |
| updated_at | timestamp | Last update |

### CaseOutput

Already exists and stores generated files.

| Field | Type | Description |
|-------|------|-------------|
| id | uuid | Primary key |
| case_id | uuid | FK to legal_cases |
| agent_number | integer | Which agent produced this |
| filename | string | e.g., "01_lead_plan.md" |
| content_type | string | MIME type |
| file_path | string | Storage path |
| file_size | integer | Bytes |
| created_at | timestamp | When generated |

### LegalCase

Existing fields used by dashboard:

| Field | Type | Used For |
|-------|------|----------|
| status | enum | Determine which agents are active/locked |
| phase | integer | 1, 2, or 3 |
| progress_percentage | integer | Overall progress bar |
| skill_version | string | Track SKILL.md version |
| skill_hash | string | Detect SKILL.md changes |
| model_used | string | AI model for this case |

---

## New Model: CaseMetrics

Aggregated metrics for the insights panel.

| Field | Type | Description |
|-------|------|-------------|
| id | uuid | Primary key |
| case_id | uuid | FK to legal_cases (unique) |
| total_duration_seconds | integer | Sum of all agent durations |
| total_tokens | integer | Sum of all agent tokens |
| statutes_matched | integer | From Agent 6 output |
| average_confidence | decimal | Average of all agent confidence scores |
| corrections_count | integer | Number of self-correction loops |
| items_for_review | json | Array of low-confidence items |
| created_at | timestamp | When metrics calculated |
| updated_at | timestamp | Last update |

### Migration

```php
Schema::create('case_metrics', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('case_id')->unique()->constrained('legal_cases')->cascadeOnDelete();
    $table->integer('total_duration_seconds')->default(0);
    $table->integer('total_tokens')->default(0);
    $table->integer('statutes_matched')->default(0);
    $table->decimal('average_confidence', 3, 2)->default(0);
    $table->integer('corrections_count')->default(0);
    $table->json('items_for_review')->nullable();
    $table->timestamps();
});
```

---

## Redis Event Queue Structure

Events are stored temporarily in Redis for SSE streaming.

**Key Pattern**: `case:{case_id}:events`  
**Type**: List (RPUSH/LPOP)  
**TTL**: Auto-cleared after consumption

**Event Schema**:
```json
{
  "case_id": "uuid-string",
  "agent_number": 3,
  "agent_name": "النزاهة والفهرسة",
  "event_type": "agent.output|agent.started|agent.completed|agent.failed",
  "content": "Streaming text content...",
  "timestamp": "2026-03-16T12:00:00.000Z",
  "metrics": {
    "tokens_used": 1234,
    "confidence": 0.85,
    "duration_ms": 5000
  }
}
```

---

## Agent Definition Array

Static array defining all 12 agents for UI rendering:

```php
// app/Services/AgentDefinitions.php

class AgentDefinitions
{
    public static function all(): array
    {
        return [
            ['number' => 0, 'phase' => 1, 'name' => 'تحليل القضية', 'name_en' => 'Case Analysis', 'outputs' => ['00_required_laws.md'], 'inputs' => ['intake.txt', 'docs/*']],
            ['number' => 1, 'phase' => 2, 'name' => 'قائد القضية', 'name_en' => 'Lead Counsel', 'outputs' => ['01_lead_plan.md', '01_acceptance_criteria.json'], 'inputs' => ['intake.txt', 'docs/*']],
            ['number' => 2, 'phase' => 2, 'name' => 'مدير الأدلة', 'name_en' => 'Evidence', 'outputs' => ['02_ingestion_report.md', '02_chunks.jsonl'], 'inputs' => ['docs/*', '01_acceptance_criteria.json']],
            ['number' => 3, 'phase' => 2, 'name' => 'النزاهة والفهرسة', 'name_en' => 'Indexing', 'outputs' => ['03_chain_of_custody.jsonl', '03_statutes_index.jsonl', '03_conflict_warnings.md'], 'inputs' => ['02_chunks.jsonl', 'laws/*']],
            ['number' => 4, 'phase' => 2, 'name' => 'الجدول الزمني', 'name_en' => 'Timeline', 'outputs' => ['04_timeline.json', '04_timeline.md', '04_entities_index.md'], 'inputs' => ['02_chunks.jsonl']],
            ['number' => 5, 'phase' => 2, 'name' => 'مدير القانون', 'name_en' => 'Law Lead', 'outputs' => ['05_issues_to_statutes.md', '05_procedural_notes.md', '05_matching_guidelines.json'], 'inputs' => ['02_chunks.jsonl', '04_timeline.json', '03_statutes_index.jsonl']],
            ['number' => 6, 'phase' => 2, 'name' => 'المطابقة النظامية', 'name_en' => 'Matcher', 'outputs' => ['06_statutes_map.jsonl', '06_accepted_matches.md', '06_gaps_and_todo.md'], 'inputs' => ['02_chunks.jsonl', '05_matching_guidelines.json', '03_statutes_index.jsonl']],
            ['number' => 7, 'phase' => 2, 'name' => 'فريق الدفاع', 'name_en' => 'Defense', 'outputs' => ['07_defense_skeleton.md'], 'inputs' => ['04_timeline.json', '05_issues_to_statutes.md', '06_statutes_map.jsonl']],
            ['number' => 8, 'phase' => 2, 'name' => 'صائغ المذكرة', 'name_en' => 'Drafter', 'outputs' => ['08_draft_brief.md'], 'inputs' => ['07_defense_skeleton.md', '06_accepted_matches.md', '04_timeline.md']],
            ['number' => 9, 'phase' => 2, 'name' => 'المراجعة النهائية', 'name_en' => 'Final', 'outputs' => ['09_final_brief_v2.md'], 'inputs' => ['08_draft_brief.md', '01_acceptance_criteria.json']],
            ['number' => 10, 'phase' => 3, 'name' => 'القاضي', 'name_en' => 'Judge', 'outputs' => ['10_judge_review.md'], 'inputs' => ['09_final_brief_v2.md']],
            ['number' => 11, 'phase' => 3, 'name' => 'محامي الشيطان', 'name_en' => "Devil's Advocate", 'outputs' => ['11_final_hardened_brief.md'], 'inputs' => ['09_final_brief_v2.md', '10_judge_review.md']],
        ];
    }
}
```

---

## Relationships

```
LegalCase
    ├── hasMany AgentExecution
    ├── hasMany CaseOutput
    └── hasOne CaseMetrics

AgentExecution
    └── belongsTo LegalCase

CaseOutput
    └── belongsTo LegalCase

CaseMetrics
    └── belongsTo LegalCase
```
