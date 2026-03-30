# Data Model: AI-Powered Input Auditing Modal

**Branch**: `004-ai-audit-modal` | **Date**: 2026-03-24

## Overview

No new database tables or migrations are required. The audit is ephemeral (per-session, not persisted). Inline inputs that the user provides are persisted to existing entities (`LegalCase.intake_text` and `CaseDocument`) using established patterns.

## Existing Entities Used

### LegalCase (no schema changes)

| Field | Type | Relevance |
|-------|------|-----------|
| `id` | UUID | Route parameter for audit endpoint |
| `title` | string | Sent to LLM in audit prompt |
| `intake_text` | text | Primary input for audit; appended to when user provides inline text |
| `status` | CaseStatus enum | Must be `awaiting_laws` to show modal |
| `documents()` | HasMany CaseDocument | Document metadata sent to LLM; new inline uploads create additional records |
| `requiredLaws()` | HasMany RequiredLaw | Law references sent to LLM as context |
| `outputs()` | HasMany CaseOutput | Phase 1 output used to extract existing analysis context |

### CaseDocument (no schema changes)

| Field | Type | Relevance |
|-------|------|-----------|
| `case_id` | UUID FK | Links document to case |
| `filename` | string | Sent to LLM as document metadata |
| `file_path` | string | Storage path (not sent to LLM) |
| `mime_type` | string | Sent to LLM as document metadata |
| `file_size` | integer | Sent to LLM as document metadata |

## Ephemeral Structures (In-Memory / JSON Only)

### Audit Request Payload

Sent from JavaScript to `POST /cases/{case}/audit`:

```
{
  inline_inputs: {
    text: { [field_label]: "user-provided value", ... },
    files: [ document_id, document_id, ... ],
    selections: { [field_label]: "selected_option", ... }
  }
}
```

### Audit Response Payload

Returned from server to JavaScript:

```
{
  score: 45,
  projected_score: 92,
  summary: "Arabic text — 2-3 sentence assessment",
  feedback: {
    required: [
      {
        label: "وصف القضية التفصيلي",
        reason: "Arabic explanation of why this matters",
        input_type: "text",
        options: null
      }
    ],
    recommended: [
      {
        label: "نوع القضية",
        reason: "Arabic explanation",
        input_type: "selection",
        options: [
          { value: "criminal", label: "جنائية" },
          { value: "civil", label: "مدنية" },
          { value: "commercial", label: "تجارية" }
        ]
      }
    ],
    optional: [
      {
        label: "مستندات داعمة إضافية",
        reason: "Arabic explanation",
        input_type: "file",
        options: null
      }
    ]
  },
  passing_threshold: 70
}
```

### Feedback Item Structure

| Field | Type | Description |
|-------|------|-------------|
| `label` | string | Short Arabic label for the feedback item |
| `reason` | string | Plain-language Arabic explanation of why this matters |
| `input_type` | enum: text, file, selection | Determines which inline input widget to render |
| `options` | array or null | For selection type: list of `{ value, label }` objects. Null for text/file. |

### Modal Client State

Managed in vanilla JavaScript (not persisted):

| State Field | Type | Description |
|-------------|------|-------------|
| `phase` | enum: loading, soft-timeout, loaded, error, fallback | Current modal state |
| `score` | number (0-100) or null | Current completeness score |
| `projectedScore` | number (0-100) or null | Projected score if all gaps addressed |
| `summary` | string or null | AI summary assessment |
| `feedback` | object or null | Tiered feedback lists |
| `inlineInputs` | object | User-provided values keyed by field label |
| `passingThreshold` | number | Score threshold for CTA adaptation |
| `abortController` | AbortController or null | For cancelling in-flight requests |
| `debounceTimer` | timeout ID or null | For 800ms debounce |

## State Transitions

### Modal Lifecycle

```
[Modal Opens]
    │
    ▼
  LOADING ──(10s timeout)──► SOFT_TIMEOUT ──(30s total)──► FALLBACK
    │                              │
    │ (audit success)              │ (audit success)
    ▼                              ▼
  LOADED ◄─────────────────── LOADED
    │
    │ (inline edit + 800ms debounce)
    ▼
  LOADING ──(re-audit cycle repeats)──► LOADED
    │
    │ (audit failure at any point)
    ▼
  FALLBACK
```

### CTA State Derivation

```
IF phase == LOADING or SOFT_TIMEOUT:
  → Proceed button disabled (unless SOFT_TIMEOUT, then Proceed Anyway enabled)
ELSE IF phase == FALLBACK:
  → Standard "Proceed" button (no score context)
ELSE IF score >= passingThreshold:
  → Primary "Proceed" button
ELSE:
  → Secondary "Proceed Anyway" + inline warning
```

## Configuration Additions

### config/legal.php

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `audit_passing_threshold` | integer | 70 | Score at or above which "Proceed" is primary CTA |
| `audit_soft_timeout_seconds` | integer | 10 | Seconds before skeleton → "still analyzing" |
| `audit_hard_timeout_seconds` | integer | 30 | Seconds before fallback mode |
