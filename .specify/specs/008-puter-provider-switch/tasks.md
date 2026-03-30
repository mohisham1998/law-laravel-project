# Tasks: LLM Provider Switch — OpenRouter & Puter

**Input**: Design documents from `/specs/008-puter-provider-switch/`
**Prerequisites**: plan.md ✅, spec.md ✅, data-model.md ✅, contracts/api-contracts.md ✅, quickstart.md ✅

**Organization**: Tasks grouped by user story to enable independent implementation and testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no blocking dependencies)
- **[Story]**: User story label (US1–US5)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Scaffolding, config, and migration skeleton before any backend logic.

- [X] T001 Add `PUTER_API_BASE_URL` env variable to `.env.example` with default `https://api.puter.com`
- [X] T002 [P] Create `config/puter.php` with `api_base_url`, `models_endpoint`, `chat_endpoint`, `cache_ttl` keys reading from env
- [X] T003 [P] Create directory stubs `app/Services/LLM/` and `app/Services/Puter/` (placeholder `.gitkeep` or direct file creation in T005+)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Database migration + core PHP interface that all user stories depend on. Must be complete before any story work begins.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T004 Create migration `database/migrations/2026_03_26_000001_add_puter_fields_to_users.php` — adds `llm_provider` (string, default `openrouter`), `puter_model` (string, default `gpt-5-nano`), `puter_disclosure_acknowledged` (boolean, default false) to `users` table
- [X] T005 [P] Create `app/Services/LLM/LLMServiceInterface.php` — define `complete()` and `completeStream()` method signatures per contracts/api-contracts.md
- [X] T006 [P] Create `app/Services/Puter/PuterException.php` — typed exception class with 5 static factory methods: `authRequired()`, `authExpired()`, `modelUnavailable()`, `networkError()`, `quotaExceeded()`, each with the Arabic user-facing message from contracts/api-contracts.md
- [X] T007 Update `app/Models/User.php` — add `llm_provider`, `puter_model`, `puter_disclosure_acknowledged` to `$fillable` and `casts()` (depends on T004)
- [X] T008 Run migration via `docker compose exec app php artisan migrate` and verify 3 columns exist in `users` table (depends on T004, T007)

**Checkpoint**: Foundation ready — migration applied, interface defined, exception class created. User story implementation can now begin.

---

## Phase 3: User Story 1 — Provider Selection in Settings (Priority: P1) 🎯 MVP

**Goal**: Users can select OpenRouter or Puter in Settings; the chosen provider is persisted and used for all subsequent AI requests.

**Independent Test**: Open Settings → select Puter → save → open Settings again → confirm Puter is still selected → submit a case → confirm LLM request was routed through Puter (check logs or `model_used` on the case).

### Implementation for User Story 1

- [X] T009 [P] [US1] Create `app/Services/Puter/PuterService.php` — constructor accepts `string $puterToken`; implement `complete()` posting to `{api_base_url}/puterai/openai/v1/chat/completions` with `Authorization: Bearer $puterToken`; match return shape `{content, prompt_tokens, completion_tokens, total_tokens}`; map HTTP errors to `PuterException` (depends on T005, T006)
- [X] T010 [P] [US1] Implement `completeStream()` in `app/Services/Puter/PuterService.php` — same endpoint with `stream: true`, iterate SSE chunks via Guzzle, call `$onChunk` per token, return final shape (depends on T009)
- [X] T011 [P] [US1] Create `app/Services/LLM/LLMServiceFactory.php` — `make(?string $puterToken = null): LLMServiceInterface`; reads `auth()->user()->llm_provider`; returns `PuterService($puterToken)` or `OpenRouterService::fromConfig()` (depends on T005, T009)
- [X] T012 [US1] Update `app/Services/OpenRouter/OpenRouterService.php` — add `implements LLMServiceInterface` declaration; no logic change (depends on T005)
- [X] T013 [US1] Update `app/Http/Controllers/SettingsController.php` — `index()` passes `llmProvider`, `puterModel`, `puterDisclosureAcknowledged` to view; `update()` validates and saves `llm_provider` (`in:openrouter,puter`), `puter_model` (nullable|string|max:255), `puter_disclosure_acknowledged` (boolean) (depends on T007)
- [X] T014 [P] [US1] Create `app/Http/Controllers/PuterController.php` — `getPuterModels()` fetches `GET {api_base_url}/puterai/chat/models/details`, applies 300s cache, derives tier from pricing, returns JSON per contracts/api-contracts.md; falls back to static list on failure (depends on T002, T006)
- [X] T015 [US1] Add route `GET /api/v1/settings/puter-models` → `PuterController@getPuterModels` in `routes/api/v1.php` (depends on T014)
- [X] T016 [US1] Update `app/Jobs/ProcessPhase1Job.php` — add `string $puterToken = ''` property; read `X-Puter-Token` header at dispatch time; pass token to `LLMServiceFactory::make($this->puterToken)` when constructing the agent (depends on T011)
- [X] T017 [P] [US1] Update `app/Jobs/ProcessPhase2Job.php` — same Puter token plumbing as T016 (depends on T011)
- [X] T018 [P] [US1] Update `app/Jobs/ProcessPhase3Job.php` — same Puter token plumbing as T016 (depends on T011)
- [X] T019 [US1] Update `app/Services/Agents/Phase2/Phase2BaseAgent.php` — change constructor parameter type from `OpenRouterService` to `LLMServiceInterface`; all `completeStream`/`complete` call sites unchanged (depends on T012)
- [X] T020 [US1] Update `app/Services/Agents/Phase1AnalysisAgent.php` — same type change to `LLMServiceInterface` (depends on T012)
- [X] T021 [US1] Update `app/Http/Controllers/CaseController.php` (or case dispatch controller) — at dispatch time set `model_used` from effective model (OpenRouter `selected_model` OR Puter `puter_model`); read `X-Puter-Token` header and store in job payload (depends on T007, T016, T017, T018)
- [X] T022 [US1] Add Puter.js CDN script tag to `resources/views/pages/settings.blade.php` `@push('scripts')` block: `<script src="https://js.puter.com/v2/"></script>`
- [X] T023 [US1] Add provider toggle UI to `resources/views/pages/settings.blade.php` — "مزود الذكاء الاصطناعي" card with two radio buttons (OpenRouter / Puter), hidden input `name="llm_provider"` synced via Alpine.js `provider` data property (depends on T022)
- [X] T024 [US1] Wrap existing OpenRouter model card in `resources/views/pages/settings.blade.php` with `x-show="provider === 'openrouter'"` — no other changes to OpenRouter UI (depends on T023)
- [X] T025 [US1] PHP syntax-lint all modified PHP files via `docker compose exec app php -l` (depends on T012, T013, T014, T016, T017, T018, T019, T020, T021)

**Checkpoint**: Provider toggle persists on save; LLM factory routes to correct backend; OpenRouter flow unchanged.

---

## Phase 4: User Story 2 — Puter Model Selection (Priority: P2)

**Goal**: When Puter is selected, a model dropdown appears with all available Puter models, gpt-5-nano pre-selected; chosen model persists and controls all AI requests globally.

**Independent Test**: Select Puter → observe model dropdown with gpt-5-nano pre-selected → change to another model → save → reload Settings → confirm chosen model still selected → confirm `puter_model` column updated in `users` table.

### Implementation for User Story 2

- [X] T026 [US2] Add Puter section container to `resources/views/pages/settings.blade.php` with `x-show="provider === 'puter'"` — contains connection status badge, connect button, disclosure panel (placeholder for US3), and model dropdown (depends on T023)
- [X] T027 [US2] Add Puter model dropdown inside Puter section in `resources/views/pages/settings.blade.php` — Select2 styled `<select name="puter_model">`; on Alpine init fetch `/api/v1/settings/puter-models`; show loading spinner while fetching; render options with "مجاني" badge for `tier=free` and SAR cost for `tier=paid`; default to `{{ $puterModel }}` (gpt-5-nano) (depends on T015, T026)
- [X] T028 [US2] Add error state + retry button to Puter model dropdown in `resources/views/pages/settings.blade.php` — if fetch fails, show Arabic error message and retry button that re-calls the models endpoint (depends on T027)
- [X] T029 [US2] Update `resources/views/components/agent-model-config.blade.php` — accept `$llmProvider = auth()->user()->llm_provider`; when `$llmProvider === 'puter'`, replace `$modelGroups` with Puter model groups from `PuterController::getPuterModels()` via a PHP-side call; apply same free/paid label logic; save mechanism (JSON `agent_model_overrides`) unchanged (depends on T014, T027)

**Checkpoint**: Puter model dropdown functional with dynamic data, pricing labels, and persistence; per-agent overrides work for Puter models.

---

## Phase 5: User Story 3 — Puter Onboarding & Disclosure (Priority: P2)

**Goal**: First-time Puter selection shows an inline Arabic disclosure panel with an "I understand" checkbox that must be checked before saving.

**Independent Test**: Clear `puter_disclosure_acknowledged` to false in DB → open Settings → select Puter → confirm disclosure panel appears → try to save without checkbox → confirm save is blocked → check box → confirm save is enabled → save → reload Settings → confirm disclosure panel does not reappear.

### Implementation for User Story 3

- [X] T030 [US3] Add connection status indicator and connect button to Puter section in `resources/views/pages/settings.blade.php` — status badge "غير متصل" (red) / "متصل" (green) driven by Alpine `puterStatus`; "Connect Puter Account" button calls `connectPuter()` Alpine method; uses `puter.auth.isSignedIn()` on init and `puter.auth.signIn()` on button click (depends on T026)
- [X] T031 [US3] Add Alpine.js methods to settings page: `checkPuterConnection()` and `connectPuter()` per the JavaScript contracts in contracts/api-contracts.md; initialize `puterStatus` on Alpine `init` (depends on T030)
- [X] T032 [US3] Add disclosure panel to Puter section in `resources/views/pages/settings.blade.php` — shown via `x-show="provider === 'puter' && !disclosureAcknowledged"` — plain Arabic explanation of Puter billing with "أفهم وأوافق" checkbox bound to `disclosureCheckbox` Alpine property (depends on T026, T030)
- [X] T033 [US3] Add save button gate logic in `resources/views/pages/settings.blade.php` — disable save button (`x-bind:disabled`) when: (provider is puter AND puterStatus !== 'connected') OR (provider is puter AND !disclosureAcknowledged AND !disclosureCheckbox) (depends on T031, T032)
- [X] T034 [US3] Add Puter token injection to case dispatch in `resources/views/pages/cases/create.blade.php` — before form submit, check `provider === 'puter'`; read `puter.authToken`; if absent show Arabic error; if present inject as `X-Puter-Token` header via fetch/AJAX or hidden form input (depends on T031)

**Checkpoint**: Disclosure panel shows on first Puter selection; save gate enforced; Puter token injected on case dispatch.

---

## Phase 6: User Story 4 — Puter Error States & Edge Cases (Priority: P3)

**Goal**: All Puter failures surface user-friendly Arabic error messages; no silent fallback to OpenRouter.

**Independent Test**: Dispatch a case with Puter selected but no valid `X-Puter-Token` header → confirm the case fails with Arabic error "يجب الاتصال بحساب Puter…" and does NOT fall back to OpenRouter.

### Implementation for User Story 4

- [X] T035 [P] [US4] Update `app/Jobs/ProcessPhase1Job.php` — in `handle()`, catch `PuterException` and map to existing case failure mechanism (`halt_reason`, `last_error_message`) using the Arabic user-facing message from `PuterException`; ensure failure does NOT retry via OpenRouter (depends on T006, T016)
- [X] T036 [P] [US4] Update `app/Jobs/ProcessPhase2Job.php` — same `PuterException` catch + failure mapping (depends on T006, T017)
- [X] T037 [P] [US4] Update `app/Jobs/ProcessPhase3Job.php` — same `PuterException` catch + failure mapping (depends on T006, T018)
- [X] T038 [US4] Add pre-dispatch validation in case dispatch controller — before queuing job, if `auth()->user()->llm_provider === 'puter'` and `X-Puter-Token` header is absent, return validation error: "يجب الاتصال بحساب Puter أولاً من صفحة الإعدادات." (depends on T021)
- [X] T039 [US4] Verify `resources/views/pages/cases/show.blade.php` renders `halt_reason`/`last_error_message` for Puter error codes — no new UI needed if existing display already handles these fields; confirm Arabic messages appear correctly in the UI

**Checkpoint**: Puter failures produce Arabic messages; no silent fallback; pre-dispatch token check blocks invalid submissions.

---

## Phase 7: User Story 5 — OpenRouter Backward Compatibility (Priority: P1)

**Goal**: All existing OpenRouter flows continue to work exactly as before; no regressions introduced.

**Independent Test**: With `llm_provider = 'openrouter'` (default), submit a case → pipeline completes normally → all agents used OpenRouter → no Puter code touched.

### Implementation for User Story 5

- [X] T040 [US5] Verify `LLMServiceFactory` defaults to `OpenRouterService` when `llm_provider` is null or `'openrouter'` — trace factory code path in `app/Services/LLM/LLMServiceFactory.php` and confirm no regression (depends on T011)
- [X] T041 [US5] Verify `app/Models/User.php` migration default sets `llm_provider = 'openrouter'` for all existing users without a value — check migration default and existing rows (depends on T004, T007)
- [X] T042 [US5] Verify Settings page still shows OpenRouter section correctly after UI changes — open Settings with default provider, confirm OpenRouter model card and API key fields render normally (depends on T023, T024)
- [X] T043 [US5] PHP syntax-lint all modified agent and job files via `docker compose exec app php -l app/Services/Agents/Phase2/Phase2BaseAgent.php app/Services/Agents/Phase1AnalysisAgent.php app/Jobs/ProcessPhase1Job.php app/Jobs/ProcessPhase2Job.php app/Jobs/ProcessPhase3Job.php` (depends on T019, T020, T035, T036, T037)

**Checkpoint**: OpenRouter flow identical to pre-feature baseline; all existing users unaffected.

---

## Phase 8: Polish & End-to-End Validation (FR-015)

**Purpose**: Playwright MCP validation, edge case checks, and final syntax review.

- [X] T044 [P] Playwright screenshot — Settings page with OpenRouter selected (default state): `resources/views/pages/settings.blade.php`
- [X] T045 [P] Playwright screenshot — Settings page with Puter selected showing Puter section with model dropdown and disclosure panel
- [X] T046 Playwright test — provider switch flow: select Puter → save → reload → confirm Puter still selected; select OpenRouter → save → reload → confirm OpenRouter selected
- [X] T047 Playwright test — disclosure gate: clear `puter_disclosure_acknowledged` → select Puter → confirm disclosure visible → attempt save without checkbox → confirm blocked → check checkbox → confirm save enabled
- [X] T048 Playwright test — model dropdown loads: select Puter → confirm model dropdown fetched and gpt-5-nano pre-selected → change model → save → reload → confirm model persisted
- [X] T049 Playwright test — error state: attempt case dispatch with Puter selected and no valid token → confirm Arabic error message appears
- [X] T050 [P] Run `docker compose exec app php artisan config:clear && php artisan view:clear && php artisan cache:clear` to verify clean state
- [X] T051 Document any test failures in `TEST_ISSUES.md` at repo root; resolve all issues before marking feature complete

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately
- **Phase 2 (Foundational)**: Depends on Phase 1 — BLOCKS all user stories
- **Phase 3 (US1)**: Depends on Phase 2 — core backend + provider toggle
- **Phase 4 (US2)**: Depends on Phase 3 (T015, T023, T026) — Puter model dropdown
- **Phase 5 (US3)**: Depends on Phase 3 (T023, T026) — disclosure + connection status
- **Phase 6 (US4)**: Depends on Phase 3 (T016, T017, T018, T021) — error plumbing
- **Phase 7 (US5)**: Depends on Phase 3 (T011, T023, T024) — regression verification
- **Phase 8 (Polish)**: Depends on all prior phases

### User Story Dependencies

- **US1 (P1)**: Foundational complete → implements factory, routing, toggle UI — no dependencies on other stories
- **US2 (P2)**: US1 complete (T015, T023, T026 needed) → model dropdown + per-agent overrides
- **US3 (P2)**: US1 complete (T023, T026 needed) → disclosure panel + connection status; can proceed in parallel with US2
- **US4 (P3)**: US1 complete (T016–T018, T021 needed) → error handling; can proceed in parallel with US2, US3
- **US5 (P1)**: US1 complete (T011, T023, T024 needed) → regression checks; can proceed in parallel with US2, US3, US4

### Within Each Phase

- Foundational tasks T005, T006 can run in parallel
- Within US1: T009, T010, T011, T012, T014 can run in parallel; T013, T015 depend on their respective tasks
- Within US4: T035, T036, T037 can run in parallel

### Parallel Opportunities

```bash
# Phase 2 — run in parallel:
T005  # LLMServiceInterface
T006  # PuterException

# Phase 3 (US1) — run in parallel first:
T009  # PuterService.complete()
T012  # OpenRouterService implements interface
T014  # PuterController

# Phase 5 (US3) + Phase 6 (US4) — can run in parallel after US1:
T030/T031/T032/T033  # Disclosure + connection UI
T035/T036/T037       # Error catch in jobs
```

---

## Implementation Strategy

### MVP First (US1 + US5 Only)

1. Complete Phase 1: Setup (T001–T003)
2. Complete Phase 2: Foundational (T004–T008) — critical blocker
3. Complete Phase 3: User Story 1 (T009–T025)
4. Complete Phase 7: User Story 5 regression check (T040–T043)
5. **STOP and VALIDATE**: Provider switch works; OpenRouter still works
6. Demo / deploy MVP

### Incremental Delivery

1. Setup + Foundational → backbone ready
2. US1 → provider toggle + routing live (MVP)
3. US2 → Puter model dropdown live
4. US3 → disclosure + connection status live (in parallel with US2)
5. US4 → error handling live
6. US5 → regression confirmed throughout
7. Phase 8 → Playwright validation + sign-off

---

## Notes

- Tests are not included per spec (no explicit TDD request); Playwright MCP validation in Phase 8 fulfills FR-015
- All Arabic user-facing strings come from contracts/api-contracts.md — do not translate inline
- Puter token is NEVER written to the database — only passed per-request via `X-Puter-Token` header
- `agent_model_overrides` JSON column is reused as-is; no schema change needed for per-agent Puter overrides
- Constitution VI: No new pages — all UI additions are within existing `settings.blade.php` and `agent-model-config.blade.php`
