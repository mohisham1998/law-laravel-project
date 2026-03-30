# Feature Specification: AI-Powered Input Auditing Modal

**Feature Branch**: `004-ai-audit-modal`
**Created**: 2026-03-24
**Status**: Draft
**Input**: User description: "AI-Powered Input Auditing Modal that replaces the existing Phase 2 approval modal with LLM-driven input completeness scoring, tiered feedback, and inline resolution."

## Clarifications

### Session 2026-03-24

- Q: When the user fills in inline inputs and clicks Proceed/Proceed Anyway, should those inputs be saved to the case so Phase 2 agents can use them, or are they ephemeral? → A: Persisted — inline inputs are saved to the case on Proceed (text appended to intake, files stored as documents).
- Q: For feedback items with input type "selection", where do the selectable options come from? → A: LLM-provided — the AI returns a list of selectable options alongside each "selection" type feedback item.
- Q: What is the maximum wait time for the audit call before the modal degrades to fallback mode? → A: Dynamic two-phase timeout — 10 seconds before transitioning from skeleton to a "still analyzing" indicator, 30 seconds hard ceiling before degrading to fallback mode. Between 10s and 30s the user can proceed at will.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - View Completeness Score on Modal Open (Priority: P1)

A legal operator completes Phase 1 analysis for a case and sees the approval modal. Instead of a static confirmation dialog, the modal immediately shows a loading skeleton while an AI audit runs in the background. Within seconds, a dual-state progress bar appears showing the current completeness score (e.g., 45%) and the projected score (e.g., 92%) if all gaps are addressed. A brief summary tells the operator what the AI found overall.

**Why this priority**: The score bar and summary are the core value proposition — without them, this is just a standard confirmation modal. This story delivers the primary differentiator.

**Independent Test**: Can be tested by opening the modal on any case in `awaiting_laws` status and verifying the score bar renders with a loading state, then populates with a numeric score and summary text.

**Acceptance Scenarios**:

1. **Given** a case with status `awaiting_laws`, **When** the user views the case detail page, **Then** the modal opens with a skeleton loading state on the score bar and feedback panel while the AI audit runs.
2. **Given** the modal is open and loading, **When** the AI audit completes successfully, **Then** the score bar animates to show the current score as a solid fill and the projected score as a ghost extension, with both percentages labeled and a directional arrow between them.
3. **Given** the modal is open and loading, **When** 10 seconds pass without an audit response, **Then** the skeleton transitions to a "still analyzing" indicator and the Proceed Anyway button becomes available.
4. **Given** the modal is in the "still analyzing" state, **When** 30 seconds total have elapsed without a response, **Then** the score bar and feedback panel are hidden and replaced with a neutral fallback message, and the user can proceed normally.
5. **Given** the modal is open and loading, **When** the AI audit call fails (network error, malformed response), **Then** the score bar and feedback panel are hidden and replaced with a neutral message (e.g., "Audit unavailable — you may proceed normally"), and the user can still approve or cancel as before.

---

### User Story 2 - Review Tiered Feedback (Priority: P1)

Below the score bar, the operator sees feedback items organized into three tiers: required (red), recommended (amber), and optional (green). Each item shows a short label and a plain-language explanation of why it matters. A two-to-three sentence AI summary sits above the tier list to give context before the operator scans the details.

**Why this priority**: Feedback is the actionable insight that drives the operator to improve inputs. Without it, the score is just a number with no path to improvement.

**Independent Test**: Can be tested by verifying that audit results render correctly as grouped, color-coded feedback items with labels, reasons, and a summary paragraph.

**Acceptance Scenarios**:

1. **Given** the audit returns feedback items across all three tiers, **When** the results render, **Then** required items appear in red, recommended in amber, and optional in green, each with a label and explanation.
2. **Given** the audit returns a summary assessment, **When** the results render, **Then** the summary appears above the tiered feedback list.
3. **Given** the audit returns zero items in a tier, **When** the results render, **Then** that tier section is hidden entirely rather than shown empty.

---

### User Story 3 - Resolve Flagged Items Inline (Priority: P2)

For each flagged feedback item, the modal renders an inline input appropriate to the item's type — a text field, file uploader, or selection input. When the operator fills in an input, the audit automatically re-runs after an 800ms debounce, and the score bar updates in real time to reflect the improvement.

**Why this priority**: Inline resolution turns passive feedback into an active improvement loop. However, the modal still delivers value without it (operators can note gaps and address them outside the modal).

**Independent Test**: Can be tested by filling in an inline text field for a flagged item and verifying the score bar re-animates with an updated score after the debounce window.

**Acceptance Scenarios**:

1. **Given** a feedback item with input type "text", **When** the results render, **Then** a text input field appears below the item.
2. **Given** a feedback item with input type "file", **When** the results render, **Then** a file upload control appears below the item.
3. **Given** a feedback item with input type "selection", **When** the results render, **Then** a selection input appears below the item.
4. **Given** the operator types into an inline text field, **When** 800ms pass without further input, **Then** the audit re-fires with updated inputs and the score bar smoothly re-animates to the new score.
5. **Given** the operator makes rapid successive edits (within 800ms of each other), **When** the edits stop, **Then** only one audit call fires after the final 800ms debounce.

---

### User Story 4 - Adaptive Proceed Action (Priority: P2)

The proceed button adapts based on the current score relative to the passing threshold. When the score meets or exceeds the threshold, a standard "Proceed" button is the primary action. When below, "Proceed Anyway" appears as a secondary action with an inline warning that output quality may be affected. The operator is never blocked.

**Why this priority**: This gives operators informed choice without creating a hard gate. It respects their autonomy while making quality tradeoffs visible.

**Independent Test**: Can be tested by observing the CTA label and styling change as the score crosses the threshold boundary.

**Acceptance Scenarios**:

1. **Given** the audit score is at or above the passing threshold, **When** the operator views the action area, **Then** "Proceed" appears as the primary button.
2. **Given** the audit score is below the passing threshold, **When** the operator views the action area, **Then** "Proceed Anyway" appears as a secondary-styled button with a concise warning about potential quality impact.
3. **Given** the operator clicks "Proceed" or "Proceed Anyway", **When** the action completes, **Then** all inline inputs are persisted to the case (text appended to intake, files stored as documents) and Phase 2 processing starts.
4. **Given** the audit is still loading, **When** the operator views the action area, **Then** the proceed button is disabled until results arrive or a timeout/failure occurs.

---

### User Story 5 - Backward-Compatible Modal Behavior (Priority: P1)

The new modal is a drop-in replacement for the existing approval modal. All current triggers (case status `awaiting_laws`), dismissal behaviors (backdrop click, cancel button), and backend endpoints (`start-phase2`, `update-missing-info`, `request-changes`) continue to work identically. No upstream changes are required.

**Why this priority**: If the replacement breaks existing flows, nothing else matters. Backward compatibility is a non-negotiable baseline.

**Independent Test**: Can be tested by exercising every existing approval flow — approve, cancel, request changes, add missing info — and verifying identical outcomes.

**Acceptance Scenarios**:

1. **Given** a case in `awaiting_laws` status, **When** the user views the case, **Then** the new modal opens automatically (same trigger as the current modal).
2. **Given** the modal is open, **When** the user clicks the backdrop or Cancel, **Then** the modal closes (same behavior as the current modal).
3. **Given** the modal is open, **When** the user clicks "Proceed" or "Proceed Anyway", **Then** Phase 2 starts via the existing `start-phase2` endpoint.
4. **Given** the modal is open, **When** the user clicks "Request Changes", **Then** the request changes form appears and submits to the existing `request-changes` endpoint.

---

### Edge Cases

- What happens when the AI audit returns an empty feedback list (no items in any tier)? The score should display as 100 and only the summary and "Proceed" button should appear.
- What happens when the case has no intake text or very minimal input? The audit should still run and return meaningful feedback about missing information.
- What happens when the user uploads a file via inline input but the upload fails? The file input should show an error state, and the audit should not re-trigger for the failed upload.
- What happens when the user opens the modal, the audit starts loading, and the user immediately closes the modal? The in-flight audit request should be cancelled or its result ignored on return.
- What happens when multiple rapid file uploads and text edits occur simultaneously? The 800ms debounce applies uniformly across all input types — only the latest state triggers the re-audit.
- What happens when the AI returns malformed or incomplete JSON? The modal should degrade gracefully as if the audit failed (hide score bar, show fallback message, allow normal proceed).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST replace the existing Phase 2 approval modal entirely — no parallel modal, no feature flag, single implementation.
- **FR-002**: System MUST fire an AI audit call immediately when the modal opens, sending the task type, task description, task requirements, and all current user inputs (text fields, uploaded document metadata, selected options).
- **FR-003**: System MUST display a skeleton loading state on the score bar and feedback panel while the audit runs, while still showing the user's submitted inputs.
- **FR-004**: System MUST render a dual-state progress bar showing the current score (solid fill, brand primary color) and projected score (lighter-opacity ghost extension) with both percentages labeled and a directional arrow between them.
- **FR-005**: System MUST animate the progress bar on initial load and re-animate smoothly on every score update.
- **FR-006**: System MUST display a two-to-three sentence summary assessment above the tiered feedback list.
- **FR-007**: System MUST group feedback items into three tiers — required (red), recommended (amber), optional (green) — each showing a label and a plain-language reason.
- **FR-008**: System MUST render an appropriate inline input (text field, file uploader, or selection input) for each feedback item based on the input type specified by the AI response.
- **FR-009**: System MUST debounce re-audit calls at 800ms when the user makes inline edits, preventing excessive calls during rapid input.
- **FR-010**: System MUST update the score bar in real time when a re-audit completes after inline input changes.
- **FR-011**: System MUST show "Proceed" as the primary action when the score meets or exceeds the passing threshold, and "Proceed Anyway" as a secondary action with an inline quality warning when below the threshold.
- **FR-012**: System MUST never block the user from proceeding — the audit is informational only.
- **FR-013**: System MUST implement a two-phase timeout for the audit call: after 10 seconds without a response, the skeleton loading state transitions to a "still analyzing" indicator (the user can proceed at any time during this phase); after 30 seconds (hard ceiling), the system degrades to fallback mode — hiding the score bar and feedback panel, displaying a neutral fallback message, and allowing the user to proceed normally.
- **FR-013a**: System MUST degrade gracefully on audit failure (network error, malformed response, or hard timeout) — hide the score bar and feedback panel, display a neutral fallback message, and allow the user to proceed normally.
- **FR-014**: System MUST preserve all existing modal behaviors: same trigger condition (case status `awaiting_laws`), same dismissal (backdrop click, cancel), same backend endpoints (`start-phase2`, `update-missing-info`, `request-changes`).
- **FR-015**: System MUST accept and respect all existing callbacks and state from the parent context (submission payload, task context, confirm/cancel/close handlers).
- **FR-016**: The AI audit response MUST return structured JSON containing: completeness score (0-100), projected score, summary assessment (2-3 sentences), and three tiered feedback lists (required, recommended, optional) where each item includes a label, a reason, an input type (text, file, or selection), and — for selection type items — a list of selectable options with display labels.
- **FR-017**: Scoring logic MUST enforce that missing required items cap the score at 60, missing recommended items cap at 85, and a fully addressed submission scores 86-100 based on depth and quality.
- **FR-018**: System MUST be context-aware — the audit prompt is parameterized by task type and its associated requirements, allowing different contexts to inject their own audit configuration.
- **FR-019**: System MUST cancel or ignore in-flight audit results when the modal is closed before the audit completes.
- **FR-020**: System MUST persist all user-provided inline inputs to the case when the user clicks Proceed or Proceed Anyway — text inputs are appended to the case intake data, and file uploads are stored as case documents — so that Phase 2 agents can use the enriched information.
- **FR-021**: System MUST NOT persist inline inputs if the user cancels or closes the modal without proceeding.

### Key Entities

- **Audit Result**: The AI's assessment of user inputs — contains a completeness score (0-100), projected score, summary text, and a list of feedback items grouped by tier.
- **Feedback Item**: A single finding from the audit — has a severity tier (required, recommended, optional), a short label, a plain-language reason, an input type indicating how the user can address it (text, file, or selection), and for selection items, a list of AI-provided options to choose from.
- **Audit Configuration**: Task-type-specific parameters that define what the AI evaluates — includes the task type identifier, task description, and a set of known requirements for that task type.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users see a completeness score and actionable feedback within 5 seconds of the modal opening.
- **SC-002**: Users who interact with inline resolution inputs see their score update within 2 seconds of completing an edit (800ms debounce plus audit round-trip).
- **SC-003**: 100% of existing approval modal flows (approve, cancel, request changes, add missing info) continue to function identically after the replacement.
- **SC-004**: When the AI audit service is unavailable, 100% of users can still proceed through the modal without being blocked or seeing error screens.
- **SC-005**: Users who address all required feedback items see their score rise above 60; users who also address all recommended items see it rise above 85.
- **SC-006**: The modal renders and becomes interactive (showing at minimum the loading state and user inputs) within 1 second of being triggered.

## Assumptions

- The passing threshold for score-based CTA adaptation defaults to 70 and is configurable per deployment without code changes.
- The existing `start-phase2` endpoint behavior is preserved as-is — the new modal adds an audit layer before the user reaches that action.
- The "Request Changes" flow from the current modal is preserved within the new modal alongside the audit feedback.
- File uploads within inline resolution inputs use the same storage and upload mechanisms already available in the application.
- The AI audit uses the same OpenRouter service already integrated in the application for LLM calls.
- The 800ms debounce window is hardcoded for this iteration; it may become configurable in the future.
- The modal currently serves a single context (Phase 2 approval after Phase 1). The context-aware architecture supports future contexts, but only the Phase 2 approval context is implemented initially.

## Out of Scope

- Persisting audit results or scores to the database — the audit is ephemeral and per-session.
- Audit history or trend tracking across multiple modal opens for the same case.
- User ability to customize or override scoring weights.
- Multi-language support for audit feedback beyond the existing application language conventions.
- Offline support or client-side fallback scoring when the AI service is unreachable.
