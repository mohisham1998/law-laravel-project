# Feature Specification: Case Output Page Redesign

**Feature Branch**: `002-case-output-redesign`
**Created**: 2026-03-22
**Status**: Draft
**Input**: User description: "redesign the case output page (show.blade.php) for a clearer multi-agent pipeline UX: fix navbar overlap, consolidate streaming components, add visual pipeline tracker, make phase gates prominent, and surface PDF export clearly"

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — At-a-Glance Pipeline Progress (Priority: P1)

A lawyer opens a case that is actively processing and immediately understands the full 13-agent pipeline: which agents have completed (green), which is running now (animated), and which are still pending (grey). Phase membership (Phase 1 / Phase 2 / Phase 3) is visually grouped so the three-phase architecture is obvious. No scrolling is required to see the overall pipeline state.

**Why this priority**: The pipeline tracker is the page's primary orientation tool. Without it users have no sense of where the case stands in the 13-step analysis. All other improvements build on this foundation.

**Independent Test**: Load a case in `phase2_processing` state. Without scrolling, the user can identify how many agents have completed, which agent is running, and which phase is active. This alone delivers actionable value.

**Acceptance Scenarios**:

1. **Given** a case is in `phase2_processing`, **When** the user opens the case page, **Then** a visual pipeline tracker is visible without scrolling, showing all 13 agents grouped into three labelled phases, with each agent displaying the correct status (completed / in-progress / pending).
2. **Given** an agent transitions from in-progress to completed, **When** the SSE event is received, **Then** the tracker updates without a page refresh and the next agent animates into the in-progress state.
3. **Given** a case is `phase3_completed`, **When** the user opens the page, **Then** all 13 agents show as completed and a 100% progress indicator is shown.
4. **Given** a case is in `paused` or `failed` state, **When** the user opens the page, **Then** the failed agent is shown with a distinct error indicator in the tracker.

---

### User Story 2 — Prominent Phase Gate Actions (Priority: P2)

A lawyer whose case has reached `phase2_completed` immediately sees a prominent, visually distinct call-to-action inviting them to start Phase 3 judicial arbitration. The action stands out from informational content and is easy to locate on first glance — not buried mid-page.

**Why this priority**: Phase gates are approval checkpoints where the user must act to advance the pipeline. Missing or overlooking the gate stalls the workflow. This is a correctness issue, not just aesthetics.

**Independent Test**: Open a `phase2_completed` case. Without any scrolling, the Phase 3 gate call-to-action is visible and clearly signals that user action is required. Clicking it starts Phase 3.

**Acceptance Scenarios**:

1. **Given** a case is `phase2_completed`, **When** the page loads, **Then** a full-width phase gate banner is displayed directly below the pipeline tracker (before the agent list) with a clearly labelled primary action button to start Phase 3 judicial arbitration.
2. **Given** the user clicks the Phase 3 start button, **When** the request completes, **Then** the banner disappears and the pipeline tracker updates to show Phase 3 agents as pending/in-progress.
3. **Given** a case is in any status other than `phase2_completed`, **When** the page loads, **Then** no phase gate banner is shown and no layout gap appears.

---

### User Story 3 — Single Consolidated Live Output (Priority: P3)

During active processing a lawyer watches live agent output in one unified panel — not two competing streaming panels. Output displays as formatted, readable Arabic text (with headings, bold, and paragraphs). All completed agent outputs are visible by expanding the respective agent card, restored from the database on page load without needing SSE replay.

**Why this priority**: The current page has two separate streaming panels showing the same content in different formats, creating confusion and wasting space. Removing the duplicate and fixing formatting directly eliminates the most-reported UX complaint.

**Independent Test**: While an agent is running, count the live streaming areas on screen — there must be exactly one. Expand a completed agent card. No raw JSON, escape sequences, or unrendered markdown symbols appear anywhere.

**Acceptance Scenarios**:

1. **Given** a case is processing, **When** the user views the page, **Then** exactly one live-output area is visible — embedded within the currently-running agent card — and it streams content in formatted, readable Arabic text.
2. **Given** an agent has completed and its output is in the database, **When** the user expands that agent's card, **Then** the output renders correctly: headings are styled, bold text is bold, paragraphs are separated, and no raw markdown symbols or escape sequences appear.
3. **Given** a page is refreshed mid-run, **When** the page loads, **Then** all previously completed agents show their stored formatted outputs within 2 seconds, without needing SSE events to replay them.

---

### User Story 4 — PDF Export Prominently Surfaced (Priority: P4)

Once a case reaches a completed phase (`phase2_completed` or `phase3_completed`), the PDF export action is immediately visible — in the header, sidebar, or a sticky action area — without scrolling. Clicking it downloads a formatted Arabic PDF.

**Why this priority**: PDF export is the primary deliverable of the system. Burying it below lengthy agent cards means users frequently miss it, defeating the system's purpose.

**Independent Test**: On a `phase3_completed` case, the PDF export button is visible in the viewport immediately on page load without scrolling. Clicking it downloads the PDF.

**Acceptance Scenarios**:

1. **Given** a case is `phase2_completed` or `phase3_completed`, **When** the page loads, **Then** a PDF export button is visible without scrolling (in the header area, sidebar, or a sticky action bar).
2. **Given** a case is still processing, **When** the user views the page, **Then** the PDF export button is either hidden or shown as disabled with an explanation that it is not yet available.
3. **Given** the user clicks the PDF export button, **When** generation is in progress, **Then** the button shows a loading state; once complete, the download begins automatically.

---

### User Story 5 — No Navbar/Content Overlap (Priority: P5)

On all standard viewport sizes, the navigation bar does not overlap or obscure any case page content. The case page content starts below the navbar with adequate spacing so headings, buttons, and agent cards are fully readable and clickable.

**Why this priority**: Navbar overlap is a layout defect that blocks content and prevents interaction. Fixing it is a baseline accessibility and usability requirement.

**Independent Test**: On viewports of 375px, 768px, 1280px, and 1440px width, scroll to the top of the case page. All content including breadcrumb and case title is fully visible below the navbar with no overlap.

**Acceptance Scenarios**:

1. **Given** the page is viewed on any viewport from 375px to 1920px wide, **When** the user scrolls to the top, **Then** the case title and breadcrumb are fully visible below the navbar with at least 8px clearance.
2. **Given** the navbar is sticky, **When** the user scrolls down, **Then** the navbar remains visible and page content scrolls underneath it without any content being clipped or hidden.

---

### Edge Cases

- What if a case has no outputs yet (just created, not yet processed)? The pipeline tracker must still render all 13 agents as pending without errors.
- What if SSE disconnects and the user refreshes? All completed agent outputs must restore from the database without SSE.
- What if an agent produces very long output (>10,000 characters)? The expanded agent card must scroll internally without breaking the page layout.
- What if the browser viewport is narrow (mobile, 375px)? The pipeline tracker must be usable — either via horizontal scroll or a condensed representation.
- What if Phase 3 agents are not yet in the database (case only completed Phase 2)? Only agents 0–9 outputs should show; agents 10–12 must show as pending without errors.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The page MUST display a visual pipeline tracker rendered as a compact icon grid in a full-width row above the content/sidebar grid (spanning all columns): three phase sections (Phase 1: agent 0, Phase 2: agents 1–9, Phase 3: agents 10–12), each with a horizontal row of agent bubbles showing the agent number, short name, and a per-agent status indicator (pending / in-progress / completed / failed).
- **FR-002**: The pipeline tracker MUST update in real time via SSE as agent statuses change, without requiring a page refresh.
- **FR-003**: The page MUST contain exactly one live streaming output area at any time during active processing — embedded within the currently active agent's card — and the separate global output terminal panel MUST be removed.
- **FR-004**: Completed agent outputs MUST be pre-loaded from the database on page load and rendered as formatted HTML (headings, bold, paragraphs) within their respective agent cards.
- **FR-005**: No raw JSON, escape sequences, unrendered markdown symbols, or machine-formatted data MUST appear in any user-visible output area.
- **FR-006**: When a case status is `phase2_completed`, the page MUST display a visually prominent phase gate banner positioned above the agent list, containing the Phase 3 start action as its primary call-to-action.
- **FR-007**: The phase gate banner MUST be absent when the case status is anything other than `phase2_completed`.
- **FR-008**: When a case is `phase2_completed` or `phase3_completed`, a PDF export action MUST be visible in the first viewport without scrolling (header, sticky bar, or sidebar).
- **FR-009**: The PDF export button MUST show a synchronous loading state (button disabled + spinner) while the browser awaits the PDF response; no backend job or polling is required. The download begins automatically once the response is received.
- **FR-010**: The page layout MUST provide sufficient top offset so that no content is obscured by the fixed navigation bar on any viewport width from 375px to 1920px.
- **FR-011**: Completed agent cards MUST default to collapsed on page load regardless of case status (including phase3_completed); the currently running agent MUST default to expanded and scrolled into view. On fully completed cases all cards start collapsed.
- **FR-012**: When a case is `paused` or `failed`, a retry action MUST be displayed with visual prominence comparable to the Phase 3 gate banner.
- **FR-013**: The redesigned page MUST continue to use the existing Blade + Tailwind CSS stack with no new frontend dependencies.
- **FR-014**: All existing SSE, database pre-loading, and real-time update behaviour established in the prior fixes MUST be preserved; only the visual layout and component organisation changes.

### Key Entities

- **LegalCase**: The case record driving all conditional UI rendering. Its `status` value determines which sections, banners, and actions are visible.
- **CaseOutput**: Per-agent outputs stored in the database, keyed by `agent_number`. Markdown outputs are rendered; JSON/JSONL outputs are excluded from display.
- **AgentDefinition**: Static configuration of all 13 agents (number, name, Arabic name, phase). The canonical source for pipeline tracker structure.
- **AgentExecution**: Runtime record for each agent (status, timestamps). Used on page load to determine which agents are completed, in-progress, or pending.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user opening an actively-processing case can identify the current running agent, total completed count, and active phase in under 5 seconds without scrolling.
- **SC-002**: Zero occurrences of raw JSON, escape sequences, or unrendered markdown symbols appear in any user-visible output area across all tested cases.
- **SC-003**: On page refresh mid-run, all previously completed agent outputs are visible and formatted within 2 seconds of page load.
- **SC-004**: The Phase 3 gate call-to-action is visible without scrolling on 100% of desktop viewports at 1024px width and above.
- **SC-005**: The PDF export button is reachable without scrolling on completed cases, reducing the user's time-to-export to under 10 seconds from page open.
- **SC-006**: No page content is obscured by the navigation bar on any viewport from 375px to 1920px wide.
- **SC-007**: Live streaming output updates appear within 500ms of the SSE event being received — no perceptible lag between agent output emission and screen display.

---

## Assumptions

- The existing SSE infrastructure (`CaseStreamController`, `CaseEventService`) and the markdown-to-HTML rendering added in the prior SSE fix remain unchanged — this spec covers visual layout only.
- The 13-agent pipeline structure defined in `AgentDefinitions::all()` is stable during implementation.
- Tailwind CSS utility classes already in the project are sufficient for all styling; no new CSS frameworks, icon packs, or JavaScript libraries are needed.
- The existing three-column grid layout (`lg:grid-cols-3`) with content area and sidebar is retained; improvements are within the content column and its components.
- "Navbar overlap" is caused by insufficient top padding on the page content area in `layouts/app.blade.php`, not a structural layout issue.
- The `phase2_approval_modal` component and `show-retry-section` partial are retained without changes.
- Mobile-responsive behaviour for the pipeline tracker defaults to a compact horizontal-scrolling row on viewports narrower than 768px.

---

## Clarifications

### Session 2026-03-22

- Q: How should the PDF export button handle its loading state — synchronous spinner, async job, or streamed progress? → A: Synchronous — disable button + show spinner while browser awaits download; no backend change needed.
- Q: What visual form should the pipeline tracker take? → A: Compact icon grid — three phase sections each with a horizontal row of agent bubbles (number + short name + status indicator).
- Q: Where should the pipeline tracker be placed on the page? → A: Full-width row above the content/sidebar grid, spanning all columns.
- Q: On a fully completed case (phase3_completed), should agent cards start collapsed or expanded? → A: All collapsed — user expands individual cards of interest.

---

## Out of Scope

- Changes to backend PHP logic, controllers, queue jobs, agents, or SSE infrastructure.
- Redesign of any page other than `cases/show.blade.php` and its directly included blade components.
- Changes to PDF content or generation logic; only the export button placement and visibility are in scope.
- New authentication, user management, or settings features.
- Real-time collaboration (multiple users viewing the same case simultaneously).
