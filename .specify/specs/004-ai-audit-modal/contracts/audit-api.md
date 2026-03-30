# API Contract: Input Audit Endpoint

**Branch**: `004-ai-audit-modal` | **Date**: 2026-03-24

## POST /cases/{case}/audit

Triggers an AI audit of the case's input completeness. Returns a structured score and tiered feedback.

### Authentication

Same session-based authentication as all existing case endpoints. User must be authenticated.

### Request

**Content-Type**: `application/json`

**URL Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| `case` | UUID | The case ID (must have status `awaiting_laws`) |

**Body**:

```json
{
  "inline_inputs": {
    "text": {
      "وصف القضية التفصيلي": "user-provided additional text..."
    },
    "files": ["document-uuid-1", "document-uuid-2"],
    "selections": {
      "نوع القضية": "criminal"
    }
  }
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `inline_inputs` | object | No | User-provided additions from modal inline inputs. Omitted on initial audit (modal just opened). |
| `inline_inputs.text` | object | No | Key-value pairs where key is the feedback item label and value is user text. |
| `inline_inputs.files` | array of UUIDs | No | IDs of CaseDocuments uploaded via inline file inputs. |
| `inline_inputs.selections` | object | No | Key-value pairs where key is the feedback item label and value is the selected option value. |

### Response (Success — 200)

**Content-Type**: `application/json`
**Cache-Control**: `no-store`

```json
{
  "success": true,
  "data": {
    "score": 45,
    "projected_score": 92,
    "summary": "تقييم شامل للقضية — 2-3 جمل بالعربية تصف الحالة العامة للمدخلات",
    "feedback": {
      "required": [
        {
          "label": "وصف تفصيلي للوقائع",
          "reason": "يحتاج النظام إلى وصف تفصيلي للوقائع لتحليل القضية بدقة",
          "input_type": "text",
          "options": null
        }
      ],
      "recommended": [
        {
          "label": "نوع القضية",
          "reason": "تحديد نوع القضية يساعد في تضييق نطاق البحث القانوني",
          "input_type": "selection",
          "options": [
            { "value": "criminal", "label": "جنائية" },
            { "value": "civil", "label": "مدنية" },
            { "value": "commercial", "label": "تجارية" }
          ]
        }
      ],
      "optional": [
        {
          "label": "مستندات داعمة",
          "reason": "المستندات الإضافية تعزز قوة التحليل القانوني",
          "input_type": "file",
          "options": null
        }
      ]
    },
    "passing_threshold": 70
  }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `score` | integer (0-100) | Current completeness score. Capped at 60 if required items missing, 85 if recommended missing. |
| `projected_score` | integer (0-100) | Maximum achievable score if all gaps addressed. |
| `summary` | string | 2-3 sentence Arabic assessment. |
| `feedback.required` | array | Items that must be addressed (caps score at 60 if missing). |
| `feedback.recommended` | array | Items that should be addressed (caps score at 85 if missing). |
| `feedback.optional` | array | Items that would improve quality (score 86-100 range). |
| `feedback.*[].label` | string | Short Arabic label for the item. |
| `feedback.*[].reason` | string | Arabic explanation of why this item matters. |
| `feedback.*[].input_type` | string | One of: `text`, `file`, `selection`. |
| `feedback.*[].options` | array or null | For `selection` type: list of `{ value, label }`. Null for others. |
| `passing_threshold` | integer | Score threshold for CTA adaptation (from config). |

### Response (Validation Error — 422)

```json
{
  "success": false,
  "message": "Case is not in awaiting_laws status"
}
```

### Response (Server Error — 500)

```json
{
  "success": false,
  "message": "Audit service unavailable"
}
```

The client treats any non-200 response as an audit failure and degrades gracefully (fallback mode).

---

## POST /cases/{case}/audit/upload

Handles inline file uploads from the modal. Stores the file and returns the CaseDocument record for use in re-audit calls.

### Request

**Content-Type**: `multipart/form-data`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `file` | file | Yes | The uploaded file (same MIME/size restrictions as case creation). |

### Response (Success — 200)

```json
{
  "success": true,
  "document": {
    "id": "uuid-string",
    "filename": "contract.pdf",
    "mime_type": "application/pdf",
    "file_size": 245760
  }
}
```

### Response (Validation Error — 422)

```json
{
  "success": false,
  "message": "File type not allowed"
}
```

---

## Existing Endpoints (Preserved As-Is)

| Method | Endpoint | Behavior |
|--------|----------|----------|
| POST | `/cases/{case}/start-phase2` | Starts Phase 2 processing. Called when user clicks Proceed/Proceed Anyway. Before dispatching, the controller now also persists any inline text inputs to `intake_text`. |
| POST | `/cases/{case}/update-missing-info` | Appends additional_info to intake_text. Preserved for backward compatibility. |
| POST | `/cases/{case}/request-changes` | Records change request in error log. Preserved. |
