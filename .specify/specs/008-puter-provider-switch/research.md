# Research: LLM Provider Switch — OpenRouter & Puter

**Date**: 2026-03-26
**Branch**: `008-puter-provider-switch`

---

## 1. Puter.js Browser SDK — AI Interface

### Decision
Use `puter.ai.chat()` from the Puter.js browser SDK for model calls.

### Rationale
Puter exposes a first-class `puter.ai` namespace in its JS SDK. This is the canonical, documented interface for AI completions and is model-agnostic within Puter's catalog.

### Findings

**SDK load**: Puter.js is loaded via CDN script tag:
```html
<script src="https://js.puter.com/v2/"></script>
```

**Chat completion call** (browser):
```javascript
// Non-streaming
const response = await puter.ai.chat(
  prompt,          // string — the user message
  { model: 'gpt-5-nano' }  // options object
);
// response is a string or an object with .message.content

// Streaming (event-based)
const response = await puter.ai.chat(
  prompt,
  { model: 'gpt-5-nano', stream: true }
);
// returns an async iterable of chunks
```

**Message array form** (multi-turn):
```javascript
const response = await puter.ai.chat([
  { role: 'system', content: systemPrompt },
  { role: 'user',   content: userPrompt },
], { model: 'gpt-5-nano' });
```

**Response shape**:
```json
{
  "message": {
    "role": "assistant",
    "content": "..."
  },
  "index": 0,
  "finish_reason": "stop"
}
```

---

## 2. Puter Authentication — Browser Login Modal

### Decision
Use `puter.auth.signIn()` to trigger the Puter login modal. Check `puter.auth.isSignedIn()` before making AI calls to determine connection status.

### Rationale
Puter manages its own session in the browser. The login modal is a built-in SDK feature that handles account creation, login, and OAuth. No custom auth UI needed.

### Findings

**Check auth status** (sync boolean):
```javascript
const isSignedIn = puter.auth.isSignedIn(); // sync, returns boolean
```

**Trigger login modal** — MUST be called from a user click handler (browsers block unsolicited popups):
```javascript
document.getElementById('connect-puter').addEventListener('click', async () => {
  await puter.auth.signIn();
  // resolves when user completes login or account creation
  // optional: pass { attempt_temp_user_creation: true } for frictionless onboarding
});
```

**Alternative modal** (dialog instead of popup):
```javascript
await puter.ui.authenticateWithPuter();
```

**Get current user** (after login):
```javascript
const user = await puter.auth.getUser();
// { username: '...', uuid: '...', email: '...', ... }
```

**Sign out**:
```javascript
await puter.auth.signOut();
```

**Auto-auth**: Every `puter.ai.chat()` call that requires auth will automatically trigger sign-in if not already authenticated. No need to call `signIn()` manually unless building a custom flow.

**Connection status pattern** for the Settings UI (Alpine.js):
```javascript
async function checkPuterConnection() {
  try {
    return puter.auth.isSignedIn() ? 'connected' : 'not_connected';
  } catch {
    return 'not_connected';
  }
}
```

---

## 3. Puter AI Model List

### Decision
Use `puter.ai.listModels()` from the browser SDK. The same data is also available via a public REST endpoint (`GET https://api.puter.com/puterai/chat/models/details`) callable from Laravel for server-side model fetching.

### Rationale
Dynamic model list confirmed available. `listModels()` returns 500+ models with pricing data. The server-side REST endpoint is used by `SettingsController::getPuterModels()` so the model list is cached server-side without requiring a browser SDK call.

### Findings

**Browser SDK** (optional, for client-side use):
```javascript
const models = await puter.ai.listModels();             // all providers
const openaiModels = await puter.ai.listModels("openai"); // filtered
```

**Server-side REST endpoint** (used in Laravel — no auth required for listing):
```
GET https://api.puter.com/puterai/chat/models/details
```

**Authoritative model object shape**:
```json
{
  "id": "claude-opus-4-5",
  "provider": "anthropic",
  "name": "Claude Opus 4.5",
  "aliases": ["claude-opus"],
  "context": 200000,
  "max_tokens": 64000,
  "cost": {
    "currency": "usd-cents",
    "tokens": 1000000,
    "input": 500,
    "output": 2500
  }
}
```

**Cost field**: `cost.input` and `cost.output` are in **USD-cents per 1M tokens**. Convert: `prompt_price_per_token = cost.input / 100 / 1_000_000`.

**Free model detection**: `cost` is `null` or absent, OR `cost.input === 0` AND `cost.output === 0`.

**Pricing display logic** (matching OpenRouter selector):
- `cost === null` OR `cost.input === 0`: label `مجاني` (Free)
- Otherwise: compute SAR cost per average case using `cost.input`/`cost.output` → same calculation as OpenRouter

---

## 4. Puter Token / Session for Backend Proxy

### Decision
The Puter browser session is managed entirely client-side by Puter.js. For the backend-proxy architecture (confirmed in clarification Q1), the browser passes the Puter auth token as a custom HTTP header (`X-Puter-Token`) with each AI request to the Laravel backend. Laravel extracts it and includes it when calling Puter's API.

### Rationale
Puter does not expose a persistent API key for server-side use in the same way OpenRouter does. The user's Puter session token is ephemeral and browser-bound. Passing it per-request from the frontend is the correct pattern for a backend-proxy architecture.

### Findings

**Extracting token from browser SDK** (direct property — confirmed):
```javascript
const token = puter.authToken; // string — available immediately after signIn() resolves
```

**Token type**: JWT containing a session UUID. The server validates it server-side (not fully stateless). Costs are charged to the user whose token is used (Puter's "user-pays" model).

**Backend receives** (token in custom header):
```
POST /api/puter/chat
X-Puter-Token: <browser-puter-token>
Content-Type: application/json

{ "model": "gpt-5-nano", "messages": [...], "stream": false }
```

**Laravel proxy** — use the **OpenAI-compatible endpoint** (confirmed, simpler than drivers/call):
```
POST https://api.puter.com/puterai/openai/v1/chat/completions
Authorization: Bearer <puter-token>
Content-Type: application/json

{
  "model": "gpt-5-nano",
  "messages": [
    { "role": "system", "content": "..." },
    { "role": "user",   "content": "..." }
  ],
  "max_tokens": 4096,
  "stream": false
}
```

**PHP/Laravel (Guzzle) example**:
```php
$response = Http::withToken($puterToken)
    ->post('https://api.puter.com/puterai/openai/v1/chat/completions', [
        'model'      => $model,
        'messages'   => $messages,
        'max_tokens' => $maxTokens,
    ]);

$content = $response->json('choices.0.message.content');
$usage   = $response->json('usage'); // { prompt_tokens, completion_tokens, total_tokens }
```

**Response shape** (identical to OpenAI — drops straight into existing parsing logic):
```json
{
  "choices": [{ "message": { "role": "assistant", "content": "..." }, "finish_reason": "stop" }],
  "usage": { "prompt_tokens": 120, "completion_tokens": 300, "total_tokens": 420 }
}
```

**Key insight**: The Puter backend endpoint is **OpenAI API-compatible**. `PuterService` can use nearly identical logic to `OpenRouterClient` — just a different base URL and auth token source.

---

## 5. Laravel-Side Puter Service

### Decision
Create `App\Services\Puter\PuterService` implementing the same `complete()` / `completeStream()` interface contract as `OpenRouterService`. A thin `LLMServiceFactory` resolves the active provider at runtime from `auth()->user()->llm_provider`.

### Rationale
Keeps agent code (`Phase2BaseAgent`, `Phase1AnalysisAgent`) completely unchanged — they call the same interface regardless of provider. The factory is the single routing point.

### Findings

**Interface contract** (both services must implement):
```php
interface LLMServiceInterface {
    public function complete(string $model, array $messages, ?float $temperature, ?int $maxTokens, array $meta): array;
    public function completeStream(string $model, array $messages, callable $onChunk, ?float $temperature, ?int $maxTokens, array $meta): array;
}
```

**Factory resolution**:
```php
class LLMServiceFactory {
    public static function make(?string $puterToken = null): LLMServiceInterface {
        $provider = auth()->user()->llm_provider ?? 'openrouter';
        return match($provider) {
            'puter'  => PuterService::fromConfig($puterToken),
            default  => OpenRouterService::fromConfig(),
        };
    }
}
```

---

## 6. Database Changes

### Decision
Add three columns to the `users` table: `llm_provider`, `puter_model`, `puter_disclosure_acknowledged`.

### Rationale
Provider and model settings are user-scoped and must persist across sessions (per FR-013). The disclosure flag prevents re-showing on every visit (per FR-009).

### Findings

**Migration columns**:
```php
$table->string('llm_provider')->default('openrouter');            // 'openrouter' | 'puter'
$table->string('puter_model')->default('gpt-5-nano');             // Puter model ID
$table->boolean('puter_disclosure_acknowledged')->default(false);  // disclosure flag
```

**LegalCase.modelForAgent() update**: When provider is `puter`, resolve from `puter_model` + `agent_model_overrides` (which will store Puter model IDs when Puter is active).

---

## 7. Agent Model Override Extension

### Decision
The `agent_model_overrides` JSON column on `legal_cases` stores model IDs keyed by agent number. When Puter is active, the same column stores Puter model IDs. `LegalCase::modelForAgent()` is unchanged — it resolves the model from overrides or falls back to `model_used`. The case's `model_used` is set at dispatch time from the user's active provider + model.

### Rationale
This reuses the existing override mechanism without schema changes, keeping backward compatibility with OpenRouter cases.

---

## 8. Per-Agent Puter Model Config UI

### Decision
Extend `resources/views/components/agent-model-config.blade.php` to show Puter models in the per-agent dropdown when the active provider is Puter. The model groups/labels are dynamically populated based on the fetched Puter model list.

### Rationale
Matches clarification answer A (per-agent overrides with Puter models, mirroring OpenRouter).

### Alternatives considered
- Single global Puter model — rejected per user clarification (A chosen over B)
- Separate UI component — rejected per Constitution Principle VI (no new pages/components unless necessary; extend existing)

---

## 9. Constitution Compliance

| Principle | Status | Notes |
|---|---|---|
| I. Real-Time First | ✅ Pass | Puter connection status updates in real-time via Alpine.js; no page refresh needed |
| II. Zero-Cache UI | ✅ Pass | No new static assets; existing cache-busting applies |
| III. Self-Testing | ✅ Pass | Playwright MCP validation required by FR-015 |
| IV. Human-Readable Output | ✅ Pass | All error messages in Arabic plain language |
| V. Agent Logic from SKILL.md | ✅ Pass | No agent logic changes; routing only |
| VI. No New Pages | ✅ Pass | All changes in existing `settings.blade.php` + existing component |
| VII. General Standards | ✅ Pass | Provider config in env/DB, not hardcoded |
