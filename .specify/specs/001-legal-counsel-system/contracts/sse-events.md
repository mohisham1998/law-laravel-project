# SSE Event Contracts

**Feature**: `001-legal-counsel-system` | **Date**: 2026-03-19

## Transport

- **Endpoint**: `GET /cases/{case}/stream`
- **Content-Type**: `text/event-stream`
- **Cache-Control**: `no-cache, no-store`
- **Polling interval**: 200ms (Redis queue drain)
- **Idle timeout**: 30 seconds
- **Max duration**: 5 minutes
- **Reconnection**: Client-side exponential backoff (1s → 30s max)

## Event Format

All events are sent as SSE `data:` lines containing JSON:

```
data: {"type": "<event_type>", "payload": {...}, "timestamp": "<ISO 8601>"}
```

## Event Types

### connection.established

Sent immediately on successful SSE connection.

```json
{
  "type": "connection.established",
  "payload": {
    "case_id": "uuid",
    "current_status": "phase2_processing"
  }
}
```

### agent.started

Sent when an agent begins execution.

```json
{
  "type": "agent.started",
  "payload": {
    "agent_number": 1,
    "agent_name": "Lead Counsel",
    "phase": 2
  }
}
```

### agent.output

Streamed chunks of agent LLM response (buffered: 20 chars or 100ms).

```json
{
  "type": "agent.output",
  "payload": {
    "agent_number": 1,
    "chunk": "بعد مراجعة وثائق القضية...",
    "sequence": 42
  }
}
```

### agent.correction

Sent when an agent self-corrects a content error.

```json
{
  "type": "agent.correction",
  "payload": {
    "agent_number": 6,
    "attempt": 2,
    "violation_type": "low_confidence",
    "violation_detail": "Article match confidence 0.55 below threshold 0.70",
    "action": "Re-running with error context"
  }
}
```

### agent.completed

Sent when an agent finishes successfully.

```json
{
  "type": "agent.completed",
  "payload": {
    "agent_number": 1,
    "agent_name": "Lead Counsel",
    "duration_ms": 45200,
    "prompt_tokens": 3200,
    "completion_tokens": 8500,
    "total_tokens": 11700,
    "corrections_count": 0,
    "output_files": ["01_lead_plan.md", "01_acceptance_criteria.json"]
  }
}
```

### agent.failed

Sent when an agent fails after all retry/correction attempts.

```json
{
  "type": "agent.failed",
  "payload": {
    "agent_number": 6,
    "agent_name": "Statute Matcher",
    "error": "Self-correction exhausted after 3 attempts",
    "error_type": "self_correction_exhausted",
    "can_retry": true
  }
}
```

### case.status_changed

Sent when overall case status changes.

```json
{
  "type": "case.status_changed",
  "payload": {
    "case_id": "uuid",
    "old_status": "phase2_processing",
    "new_status": "phase2_completed",
    "phase": 2
  }
}
```

### pipeline.paused

Sent when the pipeline pauses due to unrecoverable error (3 correction attempts exhausted).

```json
{
  "type": "pipeline.paused",
  "payload": {
    "case_id": "uuid",
    "failed_agent": 6,
    "reason": "لم يتمكن الوكيل من تصحيح الخطأ بعد 3 محاولات",
    "options": ["retry", "cancel"]
  }
}
```

### connection.timeout

Sent when SSE connection reaches max duration.

```json
{
  "type": "connection.timeout",
  "payload": {
    "reason": "max_duration_exceeded",
    "reconnect_after_ms": 1000
  }
}
```
