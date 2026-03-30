# Feature Specification: LLM Provider Switch — OpenRouter & Puter

**Feature Branch**: `008-puter-provider-switch`
**Created**: 2026-03-26
**Status**: Draft
**Input**: User description: "Update the Settings page so users can choose their LLM provider between OpenRouter and Puter."

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Provider Selection in Settings (Priority: P1)

A user opens the Settings page and sees a new "AI Provider" section with two options: **OpenRouter** and **Puter**. The currently active provider is highlighted. Switching providers immediately changes the provider used for all future AI operations across the product.

**Why this priority**: This is the foundational control surface for the entire feature. Without it, nothing else in this spec can be used.

**Independent Test**: Can be fully tested by opening Settings, selecting Puter, saving, submitting an AI operation, and confirming the request was routed through Puter — delivering the core provider-switching value independently.

**Acceptance Scenarios**:

1. **Given** the user is on the Settings page, **When** they view the AI Provider section, **Then** they see a toggle or radio group with "OpenRouter" and "Puter" options, with the currently active provider pre-selected.
2. **Given** OpenRouter is currently active, **When** the user selects Puter and saves, **Then** all subsequent AI requests are routed through Puter, not OpenRouter.
3. **Given** Puter is currently active, **When** the user selects OpenRouter and saves, **Then** all subsequent AI requests are routed through OpenRouter exactly as before.
4. **Given** no provider has been explicitly set, **When** the user opens Settings, **Then** OpenRouter is the default active provider (backward compatibility).

---

### User Story 2 — Puter Model Selection (Priority: P2)

When the user selects Puter as their provider, a model dropdown appears in Settings showing the list of models available through Puter. The default selected model is **gpt-5-nano**. The chosen model persists and controls all AI requests globally when Puter is active.

**Why this priority**: Puter-specific model selection is the second core deliverable. Without it, Puter-based operations have no configurable model.

**Independent Test**: Can be tested by switching to Puter, observing the model dropdown with gpt-5-nano pre-selected, changing the model, saving, running an AI operation, and verifying the chosen model was used.

**Acceptance Scenarios**:

1. **Given** the user selects Puter, **When** the Puter section renders, **Then** a model dropdown is displayed listing all available Puter models with **gpt-5-nano** selected by default.
2. **Given** the user changes the Puter model to another option, **When** they save settings, **Then** all AI requests from that point forward use the newly selected model.
3. **Given** the user has previously set a Puter model, **When** they return to Settings, **Then** the previously saved model is pre-selected in the dropdown.
4. **Given** Puter is active, **When** any AI operation is triggered anywhere in the product, **Then** the request uses the Puter model currently saved in Settings — not a hardcoded or per-screen model.

---

### User Story 3 — Puter Onboarding & Disclosure (Priority: P2)

The first time a user selects Puter (or when Puter is selected and prerequisites are not met), the product displays a clear, plain-language explanation of what Puter is, how it differs from OpenRouter, and that it may involve the user's own Puter account usage/billing. The user must acknowledge this before proceeding.

**Why this priority**: Puter has materially different billing semantics. Users must understand before relying on it to prevent surprise charges or confusion.

**Independent Test**: Can be tested by selecting Puter for the first time and verifying the disclosure message appears with an acknowledgement step before the provider is saved.

**Acceptance Scenarios**:

1. **Given** the user selects Puter for the first time, **When** they choose it, **Then** an inline notice panel appears in the Settings page explaining that Puter uses the user's own account and may consume usage credits, with an "I understand" checkbox that must be checked before the selection can be saved.
2. **Given** the user has already acknowledged the Puter disclosure, **When** they switch back and forth between providers, **Then** the disclosure does not re-appear on every switch.
3. **Given** a user who has never used Puter, **When** the disclosure is shown, **Then** it is written in plain, non-technical language with no jargon — no references to APIs, HTTP, or SDKs.

---

### User Story 4 — Puter Error States & Edge Cases (Priority: P3)

When Puter is active and an AI operation fails (authorization error, unavailable model, network failure, or account issue), the product surfaces a clear, user-friendly error message and does not silently fall back to OpenRouter or produce blank output.

**Why this priority**: Proper error handling prevents user confusion and support burden after provider switching is released.

**Independent Test**: Can be tested by simulating a Puter failure (invalid credentials or network block) and verifying the error message appears with actionable guidance.

**Acceptance Scenarios**:

1. **Given** Puter is active and the user's Puter session/authorization is missing, **When** an AI operation is triggered, **Then** the product displays an error explaining that Puter authorization is required, with a path to resolve it.
2. **Given** Puter is active and the selected model is unavailable, **When** an AI request is made, **Then** the product shows a clear message naming the issue ("selected model is currently unavailable") and suggests switching to a different model or provider.
3. **Given** Puter is active and a network or service failure occurs, **When** an AI operation is triggered, **Then** the product shows a failure message — it does NOT silently fall back to OpenRouter.
4. **Given** Puter is active, **When** the request fails for any reason, **Then** the error message includes guidance on next steps (check account, change model, or switch provider).

---

### User Story 5 — OpenRouter Backward Compatibility (Priority: P1)

All existing OpenRouter settings, model selections, and AI flows continue to work exactly as before for users who never switch to Puter or who switch back to OpenRouter. No migration is required.

**Why this priority**: Regression in the existing provider is unacceptable — it affects all current users.

**Independent Test**: Can be tested by leaving (or returning to) OpenRouter in Settings and verifying all existing AI flows produce the same results as before this feature was added.

**Acceptance Scenarios**:

1. **Given** a user who never changes their provider setting, **When** they use any AI feature, **Then** behavior is identical to before this feature was released.
2. **Given** a user who previously used Puter and switches back to OpenRouter, **When** they use any AI feature, **Then** the OpenRouter flow resumes with no errors and no stale Puter settings interfering.
3. **Given** an existing OpenRouter API key is saved, **When** the user opens Settings after this feature ships, **Then** OpenRouter is still the active provider and the API key is intact.

---

### Edge Cases

- What happens if a user saves Puter but has no active Puter account or session?
- What happens if the Puter model list fails to load? → Dropdown shows an error state with a retry button (FR-006b); the previously saved model identifier is preserved.
- What happens if a user switches provider mid-pipeline (while an AI job is already running)?
- What happens if the Puter service is fully unreachable at Settings load time?
- What happens if a previously valid Puter model is removed from the available list?
- What happens if two browser sessions have conflicting provider settings?

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The Settings page MUST include an AI Provider selector with at least two options: "OpenRouter" and "Puter".
- **FR-002**: The selected AI provider MUST be persisted and used as the single source of truth for all LLM operations across the entire product.
- **FR-003**: When OpenRouter is selected, the product MUST continue to operate exactly as it did before this feature was introduced, with no behavioral changes.
- **FR-004**: When Puter is selected, the product MUST route all LLM calls through Puter instead of OpenRouter. All Puter calls MUST originate from the Laravel backend (server-side proxy) — the browser frontend does not call Puter directly.
- **FR-005**: The Settings page MUST display a Puter model dropdown when Puter is the active provider.
- **FR-006**: The Puter model dropdown MUST dynamically fetch and list all models available through Puter's AI interface at Settings load time, displaying a loading state while fetching.
- **FR-006a**: Each model in the Puter dropdown MUST display its pricing tier — free models labeled "Free" and paid models showing their cost per token/request — using the same visual treatment as the existing OpenRouter model selector.
- **FR-006b**: If the Puter model list fails to load, the dropdown MUST show an error state with a retry option rather than an empty list.
- **FR-007**: The default selected model in the Puter dropdown MUST be **gpt-5-nano**.
- **FR-008**: The selected Puter model MUST be persisted as the global default applied to all AI requests when Puter is active.
- **FR-008a**: The per-agent model override panel MUST be extended to support Puter models when Puter is the active provider, mirroring the existing OpenRouter per-agent override system exactly. Each agent can have an individual Puter model selected; unoverridden agents use the global Puter model default.
- **FR-009**: The first time a user selects Puter, the product MUST display an inline notice panel directly in the Settings page explaining that Puter uses the user's own account and may incur usage costs. The panel MUST include an "I understand" checkbox that the user must check before the provider switch can be saved. Once acknowledged, the panel does not re-appear on subsequent provider switches.
- **FR-010**: The Puter model dropdown MUST be hidden when OpenRouter is the active provider.
- **FR-011**: When Puter is active and an AI request fails, the product MUST surface a user-friendly error specific to the failure type (authorization/token expired, model unavailable, network error).
- **FR-011a**: The Settings page MUST display a Puter Connection Status indicator ("Not Connected" / "Connected") and a button to trigger the Puter browser login modal when not connected.
- **FR-011b**: If a Puter AI request is attempted while the browser has no valid Puter token, the product MUST surface an error prompting the user to connect their Puter account from Settings before retrying.
- **FR-012**: The product MUST NOT silently fall back to OpenRouter when a Puter request fails — the failure must be surfaced to the user.
- **FR-013**: Provider and model settings MUST persist across page reloads and user sessions.
- **FR-014**: The OpenRouter API key field and model selector MUST remain functional and unchanged when OpenRouter is the active provider.
- **FR-015**: The end-to-end provider-selection flow MUST pass Playwright UI MCP validation covering: provider switching, Puter selection, default model behavior, model changes, setting persistence, and successful AI execution per provider.

### Key Entities

- **LLM Provider Setting**: A persisted application-level setting determining which AI provider handles all LLM calls. Values: `openrouter` | `puter`. Default: `openrouter`.
- **Puter Model Setting**: A persisted application-level setting storing the selected Puter model identifier. Default: `gpt-5-nano`. Only relevant when provider is `puter`.
- **Puter AI Client**: A new Laravel service/adapter that wraps Puter's API as a backend proxy. Receives the Puter session token from the frontend per-request and forwards it alongside the model and prompt to Puter's API. Conforms to the same calling contract as the existing OpenRouter service.
- **Puter Connection Status**: A UI state shown in Settings indicating whether the user has an active Puter browser session. States: `Not Connected` | `Connected`. Driven by the presence of a valid Puter token in the browser session.
- **Disclosure Acknowledgement**: A persisted flag indicating whether the user has already seen and acknowledged the Puter billing/usage disclosure.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can switch between OpenRouter and Puter in Settings and confirm the active provider changes in under 10 seconds.
- **SC-002**: After switching to Puter and selecting a model, all AI operations across every screen use the selected Puter model (or per-agent override if set) — 0 agents remain hardcoded to OpenRouter when Puter is active.
- **SC-003**: The Puter model dropdown loads with gpt-5-nano pre-selected on 100% of first-time Puter selections.
- **SC-004**: 100% of existing OpenRouter-based AI flows produce equivalent output as before this feature shipped, with no regressions.
- **SC-005**: Puter error messages appear within 5 seconds of a failed AI request with actionable guidance — no silent failures or blank screens.
- **SC-006**: The Puter disclosure appears on 100% of first-time Puter selections and is absent for users who have already acknowledged it.
- **SC-007**: The full Playwright UI MCP test suite covering provider switching, model selection, persistence, and AI execution passes with 0 failures.

---

## Clarifications

### Session 2026-03-26

- Q: Should Puter calls be made from the Laravel backend (server-side proxy) or from the browser frontend (Puter.js SDK)? → A: Backend proxy — Laravel server calls Puter's API; frontend sends requests to Laravel as today.
- Q: How does the Laravel backend authenticate with Puter, and where does the Puter token live after the browser login modal? → A: Token in browser session only — Puter's browser-native login modal runs on the frontend; the resulting token is held by the browser and passed per-request to Laravel, which proxies it through to Puter's API. No Puter credentials are stored in the database.
- Q: How should the first-time Puter disclosure be presented to the user? → A: Inline confirmation panel — a notice banner appears directly in the Settings page when Puter is selected; user checks "I understand" to confirm before the provider switch is saved.
- Q: Should the Puter model list be static or dynamically fetched, and should pricing be shown? → A: Dynamic fetch from Puter's API at Settings load time; each model displays its pricing tier (free vs. paid with cost) exactly matching the existing OpenRouter model selector presentation.
- Q: When Puter is active, do per-agent model overrides apply using Puter models, or does one global Puter model apply to all agents? → A: Per-agent overrides with Puter models — the agent model config panel is extended to allow selecting a Puter model per agent, exactly mirroring the existing OpenRouter per-agent override system.

---

## Assumptions

- Puter calls are made from the **Laravel backend** as a server-side HTTP proxy. The frontend sends requests to Laravel exactly as it does today for OpenRouter; the backend then forwards them to Puter's API. This keeps the integration consistent with the existing queue-based pipeline and avoids CORS complexity.
- The Puter model list is fetched dynamically from Puter's API at Settings load time. Each model entry includes pricing information (free vs. paid with cost). The presentation mirrors the existing OpenRouter model selector exactly, including free/paid labels and cost display.
- "gpt-5-nano" is a valid, currently available model identifier in Puter's AI interface.
- The existing Settings page can be extended without a full rewrite.
- Puter authorization uses a **browser-native login modal** (Puter's own UI). The user completes login in the browser; the resulting Puter session token is held by the browser and passed as a header/parameter on each AI request to Laravel. Laravel proxies it to Puter's API. No Puter credentials are stored in the application database.
- Provider and model settings are stored in the application's existing settings/config persistence mechanism (database or equivalent).
- Mid-pipeline provider switches (while a job is already queued or running) will use the provider active when the job was dispatched — no retroactive switching of in-flight jobs.
