# SSE Event Contracts: Pipeline Reliability

**Feature**: 005-pipeline-reliability
**Date**: 2026-03-24

---

## New SSE Events

### `pipeline.halted`

Emitted when the pipeline stops due to an agent failure, timeout, or retry budget exhaustion.

```json
{
  "case_id": "uuid",
  "event_type": "pipeline.halted",
  "agent_number": 5,
  "agent_name": "LawManagerAgent",
  "halt_reason": "agent_failure | timeout | retry_budget_exhausted",
  "phase": 2,
  "message_ar": "توقف التحليل عند الوكيل رقم 5 (مدير القوانين) بسبب خطأ في المعالجة.",
  "completed_agents": [1, 2, 3, 4],
  "skipped_agents": [6, 7, 8, 9],
  "can_retry": true,
  "resume_from_agent": 5,
  "timestamp": "2026-03-24T14:30:00Z"
}
```

### `agent.low_confidence`

Emitted when an agent completes with output below the confidence threshold.

```json
{
  "case_id": "uuid",
  "event_type": "agent.low_confidence",
  "agent_number": 3,
  "agent_name": "StatuteMatcherAgent",
  "confidence_score": 0.58,
  "threshold": 0.70,
  "corrections_attempted": 3,
  "message_ar": "أنتج الوكيل رقم 3 (مطابق الأنظمة) نتائج بثقة منخفضة (58%). الحد الأدنى المقبول هو 70%.",
  "timestamp": "2026-03-24T14:25:00Z"
}
```

### `pipeline.timeout_warning`

Emitted when the pipeline is approaching the timeout limit (at 80% of budget).

```json
{
  "case_id": "uuid",
  "event_type": "pipeline.timeout_warning",
  "elapsed_minutes": 24,
  "timeout_minutes": 30,
  "remaining_minutes": 6,
  "current_agent": 7,
  "message_ar": "تحذير: اقتربت مدة المعالجة من الحد الأقصى. متبقي 6 دقائق.",
  "timestamp": "2026-03-24T14:28:00Z"
}
```

## Modified SSE Events

### `agent.completed` (extended)

Existing event with new optional fields.

```json
{
  "case_id": "uuid",
  "event_type": "agent.completed",
  "agent_number": 3,
  "agent_name": "StatuteMatcherAgent",
  "metrics": {
    "duration_ms": 12500,
    "tokens": 4200,
    "cost_usd": 0.0084
  },
  "confidence_score": 0.85,
  "below_threshold": false,
  "self_correction_exhausted": false,
  "timestamp": "2026-03-24T14:20:00Z"
}
```

## Existing Events (unchanged)

- `agent.started` — no changes
- `agent.output` — no changes (streaming chunks)
- `agent.failed` — no changes
- `agent.correction` — no changes
