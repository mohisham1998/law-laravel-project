# Data Model: LLM Provider Switch — OpenRouter & Puter

**Branch**: `008-puter-provider-switch`
**Date**: 2026-03-26

---

## Entity: User (extended)

**Table**: `users`
**Change type**: Migration — add 3 columns

| Column | Type | Default | Nullable | Notes |
|--------|------|---------|----------|-------|
| `llm_provider` | `string` | `'openrouter'` | No | Active LLM provider. Values: `openrouter` \| `puter` |
| `puter_model` | `string` | `'gpt-5-nano'` | No | Default Puter model when no per-agent override applies |
| `puter_disclosure_acknowledged` | `boolean` | `false` | No | Whether user has acknowledged the Puter billing disclosure |

**Existing columns preserved without change**:
- `selected_model` — OpenRouter model ID (unchanged, still used when `llm_provider = 'openrouter'`)
- All other user columns

**Validation rules**:
- `llm_provider` MUST be one of `['openrouter', 'puter']`
- `puter_model` MUST be a non-empty string (validated against fetched model list at save time)
- `puter_disclosure_acknowledged` — set to `true` on first Puter save with checkbox checked; never reset to `false`

---

## Entity: LegalCase (unchanged schema, updated logic)

**Table**: `cases`
**Change type**: Logic only — no migration needed

**Existing column reused**:
- `agent_model_overrides` — JSON `{ "0": "model-id", "5": "model-id" }` — when `llm_provider = 'puter'`, stores Puter model IDs per agent. Backward-compatible: existing OpenRouter cases are unaffected.
- `model_used` — set at case dispatch time to the user's active global model (OpenRouter `selected_model` OR Puter `puter_model`) at the moment of dispatch.

**`modelForAgent(int $agentNumber)` resolution order** (unchanged priority, updated fallback):
1. `agent_model_overrides[$agentNumber]` — per-agent override (works for both providers)
2. `model_used` — global model snapshotted at dispatch time
3. `config('openrouter.default_model')` — ultimate fallback (OpenRouter only; Puter cases always have `model_used` set)

---

## Entity: PuterModel (transient — not persisted)

**Source**: Dynamically fetched from Puter's model API at Settings page load
**Persisted**: Never stored in DB — fetched fresh each Settings load
**Cached**: In Laravel cache for 300 seconds (5 minutes) to avoid hammering the Puter API

**Shape**:
```json
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
}
```

**Tier derivation**:
- `tier = "free"` if `pricing.prompt == "0"` AND `pricing.completion == "0"`
- `tier = "paid"` otherwise

---

## Entity: PuterConnectionStatus (ephemeral browser state)

**Location**: Alpine.js component state in `settings.blade.php`
**Persisted**: Never — re-checked on every Settings page load via `puter.auth.isSignedIn()`
**Not stored in DB**: Puter session is entirely browser-managed

**States**:
```
not_connected  →  (user clicks "Connect Puter Account")  →  connecting
connecting     →  (puter.auth.signIn() resolves)         →  connected
connected      →  (user clicks "Disconnect")             →  not_connected
connected      →  (token expires / page reload)          →  not_connected (re-check on load)
```

---

## State Transitions: Provider Setting

```
openrouter (default)
    │
    │ User selects "Puter" + checks "I understand" + saves
    ▼
puter
    │
    │ User selects "OpenRouter" + saves
    ▼
openrouter
```

**Rules**:
- Provider switch only takes effect on form save (not on radio click alone)
- If switching TO puter and `puter_disclosure_acknowledged = false`, the "I understand" checkbox is mandatory before save is allowed
- If switching FROM puter TO openrouter, no special gate — save proceeds immediately

---

## Interface: LLMServiceInterface (new)

**Location**: `app/Services/LLM/LLMServiceInterface.php`

```php
interface LLMServiceInterface {
    public function complete(
        string $model,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $meta = []
    ): array;  // returns: { content, prompt_tokens, completion_tokens, total_tokens }

    public function completeStream(
        string $model,
        array $messages,
        callable $onChunk,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $meta = []
    ): array;  // returns: { content, prompt_tokens, completion_tokens, total_tokens }
}
```

---

## Service: LLMServiceFactory (new)

**Location**: `app/Services/LLM/LLMServiceFactory.php`

**Resolution logic**:
```
auth()->user()->llm_provider
    = 'puter'       →  PuterService::make($puterToken)
    = 'openrouter'  →  OpenRouterService::fromConfig()
    (null/default)  →  OpenRouterService::fromConfig()
```

**Token flow**: `$puterToken` is extracted from the `X-Puter-Token` request header in the Job/Controller and passed through to the factory.

---

## New Files Summary

| File | Type | Purpose |
|------|------|---------|
| `database/migrations/YYYY_add_puter_fields_to_users.php` | Migration | Add `llm_provider`, `puter_model`, `puter_disclosure_acknowledged` |
| `app/Services/LLM/LLMServiceInterface.php` | Interface | Shared contract for OpenRouter + Puter |
| `app/Services/LLM/LLMServiceFactory.php` | Factory | Resolves correct service at runtime |
| `app/Services/Puter/PuterService.php` | Service | Backend proxy to Puter's API |
| `app/Services/Puter/PuterException.php` | Exception | Typed Puter errors (auth, model_unavailable, network) |
| `app/Http/Controllers/PuterController.php` | Controller | `/api/puter/models` and `/api/puter/connection-status` |

## Modified Files Summary

| File | Change |
|------|--------|
| `app/Models/User.php` | Add `llm_provider`, `puter_model`, `puter_disclosure_acknowledged` to fillable + casts |
| `app/Models/LegalCase.php` | `modelForAgent()` updated for Puter fallback |
| `app/Http/Controllers/SettingsController.php` | Handle `llm_provider`, `puter_model`, `puter_disclosure_acknowledged` in `update()` + new `getPuterModels()` |
| `app/Services/OpenRouter/OpenRouterService.php` | Implement `LLMServiceInterface` |
| `app/Jobs/ProcessPhase1Job.php` | Pass `X-Puter-Token` from case metadata to `LLMServiceFactory` |
| `app/Jobs/ProcessPhase2Job.php` | Same as above |
| `app/Jobs/ProcessPhase3Job.php` | Same as above |
| `app/Services/Agents/Phase2/Phase2BaseAgent.php` | Use `LLMServiceFactory` instead of `OpenRouterService` directly |
| `app/Services/Agents/Phase1AnalysisAgent.php` | Use `LLMServiceFactory` |
| `resources/views/pages/settings.blade.php` | Add provider toggle, Puter section, disclosure panel, connection status |
| `resources/views/components/agent-model-config.blade.php` | Show Puter models in per-agent dropdowns when provider = puter |
| `routes/web.php` + `routes/api/v1.php` | Add Puter routes |
