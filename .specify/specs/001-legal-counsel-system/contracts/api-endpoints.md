# API Endpoint Contracts

**Feature**: `001-legal-counsel-system` | **Date**: 2026-03-19

## Existing Endpoints (require modification)

### POST /cases (cases.store)

**Change**: Remove law upload requirement from Phase 1. Phase 1 now queries RAG database.

**Request**: No change (title, description, client_name, category, attachments)

**Response**: Redirect to `cases.show` with `case_id`

**Side effect**: Dispatches `ProcessPhase1Job` which now queries RAG for relevant laws

---

### POST /cases/{case}/start-phase2 (cases.start-phase2)

**Change**: Gate condition changes from "laws uploaded" to "RAG laws identified and confirmed"

**Current gate**: `status === 'awaiting_laws'`
**New gate**: `status === 'awaiting_laws'` (unchanged — but the condition to reach this status changes)

**Request**: No body required
**Response**: Redirect to `cases.show`
**Side effect**: Dispatches `ProcessPhase2Job`

---

### POST /cases/{case}/retry-agent (cases.retry-agent)

**Change**: Support re-running from specific agent (not just retry failed)

**Request**:
```json
{
  "start_from_agent": 6  // optional, defaults to last failed agent
}
```

**Response**: `200 OK` with JSON `{"status": "resumed", "from_agent": 6}`

**Side effect**: Sets `resume_from_agent` on case, dispatches `ProcessPhase2Job`

---

### GET /cases/{case}/stream (cases.stream)

**Change**: Add new event types (`agent.correction`, `pipeline.paused`)

**Response**: SSE stream (see `contracts/sse-events.md`)

---

### GET /cases/{case}/pdf (cases.pdf)

**Change**: Implement actual PDF generation (currently stubbed)

**Gate**: `status in [phase2_completed, phase3_completed]`

**Response**: PDF file download
- Content-Type: `application/pdf`
- Content-Disposition: `attachment; filename="brief-{case_id}-{date}.pdf"`
- Arabic RTL layout, court-submission margins

---

## New Endpoints

### POST /cases/{case}/rerun-from (cases.rerun-from)

**Purpose**: Re-run pipeline from a specific agent forward

**Request**:
```json
{
  "agent_number": 6
}
```

**Validation**:
- `agent_number`: required, integer, 1-9 (Phase 2 agents only)
- Case status must be `phase2_completed` or `paused` or `failed`

**Response**: `200 OK`
```json
{
  "status": "rerunning",
  "from_agent": 6,
  "agents_to_run": [6, 7, 8, 9]
}
```

**Side effect**:
1. Deletes output files for agents 6-9
2. Deletes `CaseOutput` records for agents 6-9
3. Sets `resume_from_agent = 6`, `status = phase2_processing`
4. Dispatches `ProcessPhase2Job`

---

### POST /cases/{case}/start-phase3 (cases.start-phase3)

**Purpose**: Trigger Phase 3 judicial arbitration

**Gate**: `status === 'phase2_completed'` AND `09_final_brief_v2.md` exists

**Request**: No body required

**Response**: Redirect to `cases.show`

**Side effect**:
1. Sets `status = phase3_pending`, `phase = 3`
2. Dispatches `ProcessPhase3Job`

---

## Existing Endpoints (no changes needed)

- `GET /dashboard` — Dashboard stats
- `GET /cases` — Case listing
- `GET /cases/create` — New case form
- `GET /cases/{case}` — Case detail
- `POST /cases/{case}/abort` — Stop processing
- All `/documents/*` endpoints — Document management
- All `/laws/*` endpoints — Law registry CRUD
- All `/law-library/*` endpoints — RAG law library
- All `/settings/*` endpoints — User settings
- All `/api/*` endpoints — Models, cost estimation
