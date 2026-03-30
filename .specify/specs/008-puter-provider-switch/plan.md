# Implementation Plan: LLM Provider Switch — OpenRouter & Puter

**Branch**: `008-puter-provider-switch` | **Date**: 2026-03-26 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/008-puter-provider-switch/spec.md`

---

## Summary

Add a provider selector to the Settings page allowing users to switch between **OpenRouter** (existing, unchanged) and **Puter** (new). When Puter is active, all LLM calls are routed through a new `PuterService` backend proxy that forwards requests to Puter's API using a browser-session token passed per-request from the frontend. The Settings page gains a Puter connection status indicator (triggering Puter's browser login modal), a dynamic Puter model dropdown with free/paid pricing labels, an inline first-time disclosure panel, and per-agent model override support for Puter models. A new `LLMServiceFactory` routes all agent calls to the correct service at runtime. OpenRouter behavior is entirely preserved.

---

## Technical Context

**Language/Version**: PHP 8.x / Laravel 11
**Primary Dependencies**: Blade, Livewire, Alpine.js, Tailwind CSS, Guzzle HTTP, Puter.js (CDN), OpenRouter API, Laravel Queue (database driver)
**Storage**: SQLite (dev) / MySQL (prod) — 3 new columns on `users` table; no new tables
**Testing**: Playwright MCP (end-to-end UI), Docker-exec PHP lint (syntax)
**Target Platform**: Docker container (Linux), browser (Chrome/Firefox)
**Project Type**: Web application (Laravel MVC + Blade views)
**Performance Goals**: Settings page loads Puter model list in < 3s; AI request routing adds < 50ms overhead
**Constraints**: No new pages (Constitution VI); OpenRouter must not regress; Puter token never persisted to DB
**Scale/Scope**: Single-user per session; 13 agents per pipeline; ~10 concurrent cases max

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Real-Time First | ✅ PASS | Connection status updates without page refresh via Alpine.js reactive state |
| II. Zero-Cache UI | ✅ PASS | No new static assets; Puter.js loaded from CDN with versioned URL |
| III. Self-Testing | ✅ PASS | FR-015 mandates Playwright MCP validation before completion |
| IV. Human-Readable Output | ✅ PASS | All errors in plain Arabic with actionable guidance; 5 error codes defined |
| V. Agent Logic from SKILL.md | ✅ PASS | No agent logic changes — only routing layer added |
| VI. No New Pages | ✅ PASS | All UI in existing `settings.blade.php` + existing `agent-model-config.blade.php` |
| VII. General Standards | ✅ PASS | Provider config in DB; token never hardcoded |

**No violations. No complexity table required.**

---

## Project Structure

### Documentation (this feature)

```text
specs/008-puter-provider-switch/
├── plan.md              ← this file
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
├── quickstart.md        ← Phase 1 output
├── contracts/
│   └── api-contracts.md ← Phase 1 output
└── tasks.md             ← Phase 2 output (/speckit.tasks command)
```

### Source Code Layout

```text
app/
├── Http/Controllers/
│   ├── SettingsController.php          [MODIFY] — add puter fields, getPuterModels()
│   └── PuterController.php             [NEW] — /api/puter/models endpoint
├── Models/
│   ├── User.php                        [MODIFY] — add 3 new fillable/cast fields
│   └── LegalCase.php                   [MODIFY] — modelForAgent() Puter fallback
├── Services/
│   ├── LLM/
│   │   ├── LLMServiceInterface.php     [NEW] — shared complete()/completeStream() contract
│   │   └── LLMServiceFactory.php       [NEW] — resolves correct service by user provider
│   ├── Puter/
│   │   ├── PuterService.php            [NEW] — backend proxy to Puter API
│   │   └── PuterException.php          [NEW] — typed Puter error codes
│   └── OpenRouter/
│       └── OpenRouterService.php       [MODIFY] — implement LLMServiceInterface
├── Jobs/
│   ├── ProcessPhase1Job.php            [MODIFY] — extract X-Puter-Token, use factory
│   ├── ProcessPhase2Job.php            [MODIFY] — same
│   └── ProcessPhase3Job.php            [MODIFY] — same
└── Agents/
    ├── Phase1AnalysisAgent.php         [MODIFY] — accept LLMServiceInterface instead of OpenRouterService
    └── Phase2/
        └── Phase2BaseAgent.php         [MODIFY] — accept LLMServiceInterface instead of OpenRouterService

database/migrations/
└── YYYY_MM_DD_add_puter_fields_to_users.php  [NEW]

resources/views/
├── pages/
│   └── settings.blade.php              [MODIFY] — provider toggle, Puter section
└── components/
    └── agent-model-config.blade.php    [MODIFY] — Puter model dropdowns

routes/
├── web.php                             [MODIFY] — no new routes (form POST unchanged)
└── api/v1.php                          [MODIFY] — add GET /settings/puter-models
```

---

## Phase 0: Research ✅ Complete

See [research.md](research.md) — all unknowns resolved:

1. ✅ Puter.js SDK: `puter.ai.chat()`, `puter.auth.signIn()`, `puter.auth.isSignedIn()`
2. ✅ Auth pattern: browser login modal → session token → pass as `X-Puter-Token` header
3. ✅ Model list: dynamic fetch via `puter.ai.listModels()` or Puter's backend API
4. ✅ Backend proxy: `POST https://api.puter.com/drivers/call` with `Authorization: Bearer <token>`
5. ✅ Laravel service: `PuterService` implementing `LLMServiceInterface`

---

## Phase 1: Design & Contracts ✅ Complete

### 1a. Data Model

See [data-model.md](data-model.md):
- `users` table: +3 columns (`llm_provider`, `puter_model`, `puter_disclosure_acknowledged`)
- `cases.agent_model_overrides`: reused unchanged for Puter model IDs
- `PuterModel`: transient entity, cached 300s
- `PuterConnectionStatus`: ephemeral Alpine.js state

### 1b. Interface Contracts

See [contracts/api-contracts.md](contracts/api-contracts.md):
- `GET /api/v1/settings/puter-models` — new endpoint
- `POST /settings` — extended with 3 new fields
- `LLMServiceInterface` — PHP interface contract
- `PuterException` — 5 error codes with Arabic user messages

### 1c. Constitution Re-check (post-design)

All 7 principles remain compliant. No violations introduced in design phase.

---

## Implementation Phases

### Phase A: Foundation (Backend)

**Goal**: Database + service layer ready; no UI changes yet.

**Steps**:

1. **Migration**: Create `YYYY_add_puter_fields_to_users.php`
   - Add `llm_provider string default openrouter`
   - Add `puter_model string default gpt-5-nano`
   - Add `puter_disclosure_acknowledged boolean default false`
   - Run migration inside Docker

2. **User model update**: Add 3 fields to `$fillable` and `casts()`

3. **`LLMServiceInterface`**: Create `app/Services/LLM/LLMServiceInterface.php`
   - Define `complete()` and `completeStream()` signatures

4. **`OpenRouterService` implements interface**: Add `implements LLMServiceInterface` — no logic change

5. **`PuterException`**: Create `app/Services/Puter/PuterException.php`
   - 5 typed codes: `puter_auth_required`, `puter_auth_expired`, `puter_model_unavailable`, `puter_network_error`, `puter_quota_exceeded`

6. **`PuterService`**: Create `app/Services/Puter/PuterService.php`
   - Constructor accepts `string $puterToken`
   - `complete()`: POST to `https://api.puter.com/puterai/openai/v1/chat/completions` with `Authorization: Bearer $puterToken` — OpenAI-compatible endpoint, same response shape as OpenRouter
   - `completeStream()`: same endpoint with `stream: true`, iterate SSE response
   - Map HTTP error codes to `PuterException` typed codes
   - Match return shape of `OpenRouterService` exactly

7. **`LLMServiceFactory`**: Create `app/Services/LLM/LLMServiceFactory.php`
   - `make(?string $puterToken = null): LLMServiceInterface`
   - Read `auth()->user()->llm_provider`
   - Return `PuterService` or `OpenRouterService` accordingly

8. **`SettingsController` update**:
   - `index()`: pass `llmProvider`, `puterModel`, `puterDisclosureAcknowledged` to view
   - `update()`: validate + save 3 new fields
   - `getPuterModels()`: fetch from Puter API (cache 300s), return JSON with tier + pricing

9. **Routes**: Add `GET /api/v1/settings/puter-models` → `SettingsController@getPuterModels`

**Verification**: Run `docker compose exec app php artisan migrate` — confirm 3 new columns exist.

---

### Phase B: Agent Routing

**Goal**: All 13 agents route through the correct LLM service based on user provider setting.

**Steps**:

1. **`Phase2BaseAgent`**: Accept `LLMServiceInterface` instead of `OpenRouterService`
   - Constructor parameter type change only; all call sites (`completeStream`, `complete`) unchanged
   - Update `executeWithStreaming()` to use `LLMServiceFactory::make($puterToken)`

2. **`Phase1AnalysisAgent`**: Same type change; use factory

3. **Job updates** (`ProcessPhase1Job`, `ProcessPhase2Job`, `ProcessPhase3Job`):
   - Add `string $puterToken = ''` to job constructor/properties
   - At dispatch time: read `X-Puter-Token` from request header, store in job payload
   - Pass token to `LLMServiceFactory::make($this->puterToken)` when constructing agent

4. **`LegalCase::modelForAgent()`**: Already correct — resolves from `agent_model_overrides` → `model_used`. When Puter is active, `model_used` is set at dispatch time to `puter_model`. No logic change needed.

5. **Case dispatch** (`CaseController@store` or equivalent): At dispatch time, set `model_used` from the user's effective model: OpenRouter `selected_model` OR Puter `puter_model`.

**Verification**: Syntax check all modified files via `docker compose exec app php -l`.

---

### Phase C: Settings UI — Provider Toggle & Puter Section

**Goal**: Settings page shows provider selector, Puter connection status, disclosure panel, and Puter model dropdown. OpenRouter UI is unchanged.

**Steps**:

1. **Load Puter.js CDN** in `settings.blade.php` `@push('scripts')` section:
   ```html
   <script src="https://js.puter.com/v2/"></script>
   ```

2. **Provider toggle UI**: Add "مزود الذكاء الاصطناعي" card with two radio buttons:
   - OpenRouter (default)
   - Puter
   - Hidden input `name="llm_provider"` synced to selection

3. **Puter section** (shown only when Puter is selected — Alpine.js `x-show`):
   - **Connection status badge**: "غير متصل" (red) / "متصل" (green)
   - **Connect button**: triggers `puter.auth.signIn()` when not connected
   - **First-time disclosure panel** (shown when `!puterDisclosureAcknowledged` AND Puter is selected):
     - Plain Arabic explanation of Puter's billing model
     - "أفهم وأوافق" checkbox — must be checked before save is enabled
   - **Puter model dropdown**: same Select2 treatment as OpenRouter model dropdown
     - Loaded dynamically from `/api/v1/settings/puter-models`
     - Shows "مجاني" badge for free models, SAR cost for paid models
     - Default: `gpt-5-nano`

4. **OpenRouter section**: Add `x-show="provider === 'openrouter'"` to existing model card — no other changes.

5. **Form save gate**: Disable save button via Alpine.js if:
   - Provider is Puter AND not connected
   - Provider is Puter AND disclosure not acknowledged (first time)

6. **Puter token injection on save**: Before form submit, read `puter.authToken` and inject into a hidden input (for case dispatch) OR store in sessionStorage for use at case dispatch time.

**Verification**: Playwright screenshot of Settings page showing both provider states.

---

### Phase D: Per-Agent Puter Model Override

**Goal**: The agent model config panel (on case detail page) shows Puter models when provider is Puter.

**Steps**:

1. **Pass provider to component**: `agent-model-config.blade.php` receives `$llmProvider = auth()->user()->llm_provider`

2. **Conditional model groups**: When `$llmProvider === 'puter'`, replace the existing OpenRouter `$modelGroups` with Puter model groups fetched from the same API or a PHP-side cache call to `getPuterModels()`.

3. **Pricing display**: Apply same free/paid label logic to Puter models in the per-agent dropdown.

4. **Save logic**: `agent_model_overrides` stores Puter model IDs when Puter is active — no change to save mechanism (JSON column, same format).

**Verification**: Navigate to a case detail page with Puter active; confirm agent dropdowns show Puter models.

---

### Phase E: Error Handling

**Goal**: All Puter error states surface user-friendly Arabic messages.

**Steps**:

1. **`PuterService` error mapping**: Convert Puter API HTTP errors to `PuterException` typed codes (see contracts).

2. **Job error handling**: In `ProcessPhase1/2/3Job::handle()`, catch `PuterException` and map to existing case failure mechanism (`halt_reason`, `last_error_message`).

3. **Case show page**: Existing `halt_reason` display already shows Arabic error messages — ensure Puter error messages flow through this path.

4. **Case dispatch pre-check**: Before queuing a Puter case, validate that `X-Puter-Token` header is present. If not, return validation error: "يجب الاتصال بحساب Puter أولاً من صفحة الإعدادات."

**Verification**: Playwright test — attempt case dispatch without Puter connection, confirm error message appears.

---

### Phase F: Playwright End-to-End Validation (FR-015)

**Goal**: Full UI test flow confirming all success criteria.

**Test scenarios**:

1. **Provider switch**: Open Settings → Select Puter → Verify Puter section appears → Select OpenRouter → Verify OpenRouter section appears
2. **Puter connection**: Click "Connect Puter Account" → Puter modal appears → (test with mock or note as manual step)
3. **Disclosure panel**: First-time Puter selection → Disclosure panel visible → Cannot save until checkbox checked → Check box → Save enabled
4. **Model dropdown**: Puter selected → Model dropdown loads → gpt-5-nano pre-selected → Free/paid labels visible
5. **Model change + persist**: Change model → Save → Reload Settings → Correct model still selected
6. **OpenRouter regression**: Switch to OpenRouter → Submit a case → Verify pipeline runs normally
7. **Error: no token**: Attempt case with Puter selected but not connected → Error message appears

**Issues found**: Document in `TEST_ISSUES.md` at repo root. All issues must be resolved before feature is complete.

---

## Quickstart (Developer Setup)

See [quickstart.md](quickstart.md).

---

## Risk Register

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|-----------|
| Puter.js `puter.ai.listModels()` not available | Medium | Low | Fall back to curated static list in `PuterService::getFallbackModels()` |
| Puter backend API URL changes | Low | High | Store base URL in `config/puter.php` as env-driven config |
| Browser token passing blocked by CSRF/CORS | Low | High | Token passed via custom header, not cookie; CSRF uses existing `_token` |
| Per-agent Puter override UI complexity | Medium | Low | Scope to model ID swap only; no structural UI change needed |
| Mid-pipeline provider switch (running job uses old token) | Low | Medium | Job captures token at dispatch time; no mid-flight switch possible |
