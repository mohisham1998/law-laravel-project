# API Contracts: LLM Provider Switch — OpenRouter & Puter

**Branch**: `008-puter-provider-switch`
**Date**: 2026-03-26

---

## Existing Routes (unchanged)

All existing Settings routes remain identical:

| Method | Path | Handler | Notes |
|--------|------|---------|-------|
| GET | `/settings` | `SettingsController@index` | Extended to pass `llmProvider`, `puterModel`, `puterDisclosureAcknowledged` to view |
| POST | `/settings` | `SettingsController@update` | Extended to accept new fields |
| GET | `/settings/check-openrouter` | `SettingsController@checkOpenRouter` | Unchanged |
| GET | `/api/v1/settings/models` | `SettingsController@getModels` | Unchanged (OpenRouter models) |

---

## New Routes

### GET /api/v1/settings/puter-models

**Purpose**: Return the list of AI models available through Puter with pricing info.
**Auth**: Session (same as all existing routes)
**Cache**: 300 seconds server-side
**Source**: Proxied from `GET https://api.puter.com/puterai/chat/models/details` (no Puter auth required for listing)

**Response 200**:
```json
{
  "ok": true,
  "models": [
    {
      "id": "gpt-5-nano",
      "name": "GPT-5 Nano",
      "tier": "free",
      "pricing": {
        "prompt": "0",
        "completion": "0"
      },
      "context_length": 128000,
      "description": "Fast, free model via Puter"
    },
    {
      "id": "gpt-4o",
      "name": "GPT-4o",
      "tier": "paid",
      "pricing": {
        "prompt": "0.000005",
        "completion": "0.000015"
      },
      "context_length": 128000,
      "description": ""
    }
  ]
}
```

**Response 500** (Puter API unreachable, fallback served):
```json
{
  "ok": true,
  "models": [ /* static fallback list */ ],
  "fallback": true
}
```

---

### POST /settings (extended fields)

**New accepted fields** (added to existing form):

| Field | Type | Validation | Notes |
|-------|------|-----------|-------|
| `llm_provider` | string | `in:openrouter,puter` | Active provider |
| `puter_model` | string | `nullable\|string\|max:255` | Required when `llm_provider = puter` |
| `puter_disclosure_acknowledged` | boolean | `boolean` | Set to `1` when checkbox checked |

**Response**: Unchanged — redirects back with success/error flash.

---

### POST /api/v1/cases/{case}/dispatch (existing, extended)

**New behaviour**: When the case is dispatched, the controller reads `auth()->user()->llm_provider` and `puter_model` at dispatch time and stores:
- `model_used` = the effective model (OpenRouter `selected_model` OR Puter `puter_model`)
- A new `puter_token` field in the job payload (for Puter provider only) — token is read from the `X-Puter-Token` request header

**No change to route signature or response.**

---

## Frontend Contracts (Alpine.js ↔ Backend)

### Puter Connection Status Check (client-side only)

Called on Settings page load via Puter.js SDK — no server endpoint involved:

```javascript
// Called on Alpine init
async checkPuterConnection() {
  try {
    this.puterStatus = 'checking';
    const ok = await puter.auth.isSignedIn();
    this.puterStatus = ok ? 'connected' : 'not_connected';
  } catch {
    this.puterStatus = 'not_connected';
  }
}
```

### Puter Login Modal Trigger (client-side only)

```javascript
async connectPuter() {
  this.puterStatus = 'connecting';
  try {
    await puter.auth.signIn();
    this.puterStatus = 'connected';
  } catch (e) {
    this.puterStatus = 'not_connected';
    this.puterError = 'فشل الاتصال بحساب Puter. حاول مرة أخرى.';
  }
}
```

### Puter Token Extraction for Job Dispatch

Before submitting a case that uses Puter, the frontend reads the Puter token and includes it as a custom header:

```javascript
// In case dispatch form submit handler (cases/create.blade.php)
async function dispatchCase(formData) {
  const provider = document.querySelector('[name=llm_provider]')?.value
                   || '{{ auth()->user()->llm_provider }}';

  const headers = { 'X-CSRF-TOKEN': '{{ csrf_token() }}' };

  if (provider === 'puter') {
    const token = puter.authToken;
    if (!token) {
      showError('يجب الاتصال بحساب Puter أولاً من صفحة الإعدادات.');
      return;
    }
    headers['X-Puter-Token'] = token;
  }

  // submit form with headers...
}
```

---

## LLMServiceInterface Contract

```php
// app/Services/LLM/LLMServiceInterface.php

interface LLMServiceInterface
{
    /**
     * @param  array<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $meta
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function complete(
        string $model,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $meta = []
    ): array;

    /**
     * @param  callable(string): void  $onChunk
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function completeStream(
        string $model,
        array $messages,
        callable $onChunk,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $meta = []
    ): array;
}
```

---

## Error Codes — PuterException

| Code | Meaning | User-facing message (Arabic) |
|------|---------|------------------------------|
| `puter_auth_required` | No valid Puter token in request | يجب الاتصال بحساب Puter من صفحة الإعدادات قبل تشغيل القضايا. |
| `puter_auth_expired` | Token was valid but is now expired | انتهت صلاحية جلسة Puter. يرجى العودة للإعدادات وإعادة الاتصال. |
| `puter_model_unavailable` | Selected model not available | النموذج المحدد غير متاح حالياً. جرّب نموذجاً آخر من الإعدادات. |
| `puter_network_error` | Puter API unreachable | تعذّر الوصول إلى خدمة Puter. تحقق من اتصالك وحاول مرة أخرى. |
| `puter_quota_exceeded` | Account usage limit reached | تجاوزت الحصة المسموح بها في حساب Puter. تحقق من حسابك على puter.com. |
