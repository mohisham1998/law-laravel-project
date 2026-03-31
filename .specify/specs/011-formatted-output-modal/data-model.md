# Data Model: 011-formatted-output-modal

## Schema Changes

**None.** This feature is purely a UI/UX change. It reads from existing tables.

## Entities Read

### CaseOutput (existing)

| Field | Type | Used by modal |
|---|---|---|
| `agent_number` | int | Order outputs 1→9 |
| `content` | text | Markdown text to render |
| `content_type` | string | Filter: only `markdown` / `md` |
| `case_id` | int | Scoped to current case |

Already available on page via `dbOutputsByAgent` JS variable (injected by `agent-timeline-live.blade.php`).

### Case (existing)

| Field | Type | Used by modal |
|---|---|---|
| `id` | int | Route key for button |
| `status` | enum | Determine if button/modal enabled |
| `title` | string | Modal header title |

## JS Data Contract

The modal reads from the global `dbOutputsByAgent` object already on the page:

```javascript
// Structure injected by agent-timeline-live.blade.php
const dbOutputsByAgent = {
  "1": [{ filename: "...", content: "## Header\n- item" }],
  "2": [...],
  // ... agents 1-9
};
```

The modal builds its content by:
1. Iterating keys `[1,2,3,4,5,6,7,8,9]`
2. Collecting `content` from each agent group
3. Joining with `\n\n---\n\n` dividers
4. Passing to `marked.parse()`
