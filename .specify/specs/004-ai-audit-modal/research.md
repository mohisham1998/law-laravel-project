# Research: AI-Powered Input Auditing Modal

**Branch**: `004-ai-audit-modal` | **Date**: 2026-03-24

## R1: LLM Call Pattern for Non-Streaming JSON Response

**Decision**: Use `OpenRouterService::complete()` with a structured prompt that requests JSON output inside a markdown code block.

**Rationale**: This is the established pattern across all existing agents (Phase1AnalysisAgent, Phase2 agents). The `complete()` method handles retries (3 attempts, 2s delay) and transient error codes (408, 429, 5xx). JSON is extracted via regex from markdown code blocks, which is proven reliable in the codebase.

**Alternatives considered**:
- Streaming via `completeStream()` — unnecessary overhead for a single JSON response; streaming is designed for token-by-token display, not structured data.
- Direct OpenRouter API call bypassing the service — would lose retry logic and centralized config.

## R2: Audit Endpoint Architecture (Sync vs Async)

**Decision**: Synchronous AJAX endpoint (`POST /cases/{case}/audit`) that makes the LLM call server-side and returns JSON directly.

**Rationale**: The audit is a single request/response — the client fires a fetch request, the server calls OpenRouter, parses the response, and returns structured JSON. The client handles loading states and timeouts. This is simpler than a queue-based approach and matches the modal's ephemeral nature (no persistence needed). The existing `OpenRouterClient` has a configurable timeout (default 300s) which is more than sufficient for the 30s hard ceiling.

**Alternatives considered**:
- Queue job + SSE polling — over-engineered for a single LLM call in a user-facing modal. Adds complexity (job dispatch, Redis events, SSE stream) for no benefit.
- Client-side LLM call (direct to OpenRouter) — exposes API key to the browser. Rejected for security.

## R3: File Upload in Inline Resolution

**Decision**: Reuse the existing file upload pattern from `CaseController::store()` — files are stored to `cases/{case_id}/` on local disk, with `CaseDocument` records created.

**Rationale**: The existing storage infrastructure handles file uploads with proper MIME validation (images + documents, max 50MB). The inline file upload in the modal should use the same path, creating a `CaseDocument` immediately on upload (not deferred to Proceed) so the file is available for the re-audit payload. If the user cancels, orphaned files are acceptable (matches existing behavior where case creation stores files before the case is fully configured).

**Alternatives considered**:
- Temporary storage with deferred persistence — adds complexity (temp paths, cleanup logic, rename on commit). The cost of an orphaned file in a cancelled modal is negligible compared to the implementation complexity.
- Client-side file metadata only (no upload until Proceed) — the re-audit needs to reference the document, and the LLM prompt should include document metadata. Uploading immediately keeps the audit payload accurate.

## R4: Passing Threshold Configuration

**Decision**: Add `audit_passing_threshold` to `config/legal.php`, defaulting to `env('AUDIT_PASSING_THRESHOLD', 70)`.

**Rationale**: Follows the existing pattern in `config/legal.php` which already has `confidence_threshold => 0.70`. The threshold is used both server-side (in the audit response, optionally) and client-side (JavaScript reads it from a Blade-injected variable or data attribute). Environment variable allows per-deployment tuning without code changes.

**Alternatives considered**:
- Hardcoded in JavaScript — violates Constitution VII (config in env vars).
- Database-backed setting — over-engineered for a single integer threshold.

## R5: Modal State Management (No Alpine.js)

**Decision**: Use vanilla JavaScript with a simple state object pattern for modal state management. No Alpine.js.

**Rationale**: The existing layout (`app.blade.php`) does not load Alpine.js. The existing modal uses vanilla JS (DOM manipulation, fetch API, event listeners). Introducing Alpine.js would require adding a CDN script tag and establishing new patterns. The modal's state is simple enough (loading/loaded/error, score, feedback items, inline input values) to manage with a plain JS object and DOM updates.

**Alternatives considered**:
- Add Alpine.js — would benefit reactivity but violates the principle of minimal change and introduces a new dependency not currently loaded.
- Livewire component — requires server roundtrips for every state change; the 800ms debounce with client-side fetch is more responsive.

## R6: Debounce and Abort Controller Pattern

**Decision**: Use `AbortController` for cancelling in-flight audit requests, combined with a standard debounce function (800ms). When the modal closes, abort any pending request.

**Rationale**: Native browser `AbortController` is the standard way to cancel fetch requests. Combined with debounce, it ensures: (1) rapid edits don't fire multiple calls, (2) a new audit call cancels any in-flight previous call, (3) modal close cancels everything. This is simpler and more reliable than tracking request IDs or using flags.

**Alternatives considered**:
- Server-side request tracking — unnecessary; the server doesn't need to know about cancelled requests (it just completes and the response is ignored).
- No cancellation (just ignore stale responses) — would waste server resources and API credits on superseded audit calls.

## R7: Audit Prompt Language

**Decision**: The audit prompt instructs the LLM to respond in Arabic, matching the application's existing language conventions.

**Rationale**: The application UI is entirely in Arabic (RTL layout, Arabic labels, Arabic form content). All existing agent prompts produce Arabic output. The audit summary and feedback labels/reasons should be in Arabic for consistency. The prompt template explicitly specifies Arabic output.

**Alternatives considered**:
- English output — inconsistent with the rest of the application; would require the user to context-switch languages mid-workflow.
- Bilingual — unnecessary complexity; the app serves a single-language audience.
