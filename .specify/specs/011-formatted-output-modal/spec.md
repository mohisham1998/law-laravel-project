# Feature Specification: Formatted Text Output Modal

**Feature Branch**: `011-formatted-output-modal`
**Created**: 2026-03-31
**Status**: Draft
**Input**: Replace PDF case output with a formatted text modal displaying structured legal analysis rendered from Markdown, using the Cairo font, with clear headers, organized lists, and professional typography.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - View Case Analysis in Formatted Modal (Priority: P1)

After the AI pipeline completes analysis, the formatted output modal opens automatically, presenting the full legal analysis formatted with clear headers, organized lists, and professional typography using the Cairo font. A persistent button (replacing the former PDF button, labelled with the website's existing UI terminology) also allows the user to re-open the modal at any time. Content is rendered from Markdown output produced by the pipeline agents.

**Why this priority**: This is the core output experience — without a readable, well-structured display, the feature delivers no value. It replaces the current PDF generation flow entirely.

**Independent Test**: Can be tested by completing a case analysis and verifying the modal auto-opens with correctly rendered Markdown content (headers, lists, font, spacing), and that the persistent button also re-opens it.

**Acceptance Scenarios**:

1. **Given** a case analysis that has just completed, **When** the pipeline finishes, **Then** the formatted output modal opens automatically without any user action.
2. **Given** a completed case analysis, **When** the user clicks the output button (labelled with the website's existing terminology for viewing results), **Then** the formatted modal opens displaying the Markdown-rendered analysis with distinct section headers, organized lists, Cairo font, and professional spacing.
2. **Given** the modal is open, **When** the content exceeds the visible area, **Then** the modal scrolls vertically to allow reading all content without losing the header or action buttons.
3. **Given** the modal is open on a small screen (tablet/mobile), **When** the user views the content, **Then** the layout adapts responsively without content overflow or broken formatting.
4. **Given** the modal is open, **When** the user clicks the close button (×), **Then** the modal dismisses cleanly and the user returns to the case view.
5. **Given** the user dismissed the modal, **When** they click the output button on the case page, **Then** the modal re-opens with the same analysis.

---

### User Story 2 - Navigate Long Documents Comfortably (Priority: P2)

A user with a lengthy multi-section legal analysis can scroll through the modal comfortably, with the close button remaining accessible regardless of scroll position.

**Why this priority**: Long legal analyses are the norm; a persistent close control prevents the user from having to scroll back to the top to dismiss.

**Independent Test**: Can be tested with a long case output by scrolling to the bottom and verifying the close button remains visible and functional.

**Acceptance Scenarios**:

1. **Given** a long analysis in the modal, **When** the user scrolls to the bottom, **Then** the close button (×) remains visible in a sticky header or footer.
2. **Given** the modal is open, **When** the user presses Escape, **Then** the modal closes.

---

### Edge Cases

- What happens when the case analysis contains no output or empty sections? The modal should display a graceful "No content available" message rather than an empty or broken layout.
- How does the system handle very large analyses (50+ sections)? The modal must remain scrollable without performance degradation.
- How are inline Latin/English words rendered within an RTL paragraph? They should flow naturally within the RTL direction (standard Unicode bidirectional algorithm handling).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The modal MUST open automatically when the AI pipeline completes a case analysis, without requiring user action.
- **FR-002**: The case page MUST display a persistent button — replacing the former PDF button, labelled using the website's existing UI terminology for viewing results — that re-opens the output modal at any time after completion.
- **FR-003**: The modal MUST display completed case analysis output as formatted text in a large, scrollable overlay instead of generating a PDF.
- **FR-004**: The modal MUST render Markdown input — `##`/`###` headers as bold, visually larger headings; `-` and `1.` lists as properly indented bullet and numbered lists.
- **FR-005**: All text in the modal MUST use the Cairo font family for both Arabic and Latin characters.
- **FR-006**: The modal MUST be responsive and adapt to screen sizes from mobile (≥ 320px width) to desktop (≥ 1920px width).
- **FR-007**: The modal MUST include a clearly visible close button (×) that dismisses the overlay.
- **FR-008**: The close button MUST remain accessible (sticky) while the user scrolls through the content.
- **FR-009**: The modal MUST close when the user presses the Escape key.
- **FR-010**: The modal MUST display a graceful "No content available" message when case output is empty or unavailable.
- **FR-011**: The modal MUST render all content in right-to-left direction, consistent with the system-wide RTL layout.

### Key Entities

- **Case Analysis Output**: The structured result of the AI legal pipeline, delivered as Markdown text consisting of `##`/`###` headers, `-` bullet lists, numbered lists, and body paragraphs; may contain Arabic and/or English text.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can open the formatted output modal within 2 seconds of clicking the results button for any completed case.
- **SC-002**: The modal correctly renders all Markdown content with proper visual hierarchy (headers distinct from body text) for 100% of completed cases.
- **SC-003**: The modal is fully usable (no horizontal scroll, no content truncation) on screen widths from 320px to 1920px.
- **SC-004**: The modal renders in right-to-left direction for 100% of cases, consistent with the system-wide RTL layout.

## Assumptions

- The existing case pipeline agents already produce Markdown-formatted output (`##` headers, `-` bullet lists, numbered lists); no changes to pipeline output format are required.
- The Cairo font is available via Google Fonts CDN or is already loaded in the project's base layout; no self-hosting of the font is required.
- This feature replaces the existing PDF generation flow; the PDF button is replaced by a results-viewing button whose label matches the website's existing UI terminology.
- The modal auto-opens on pipeline completion; the persistent button serves as a re-open trigger for users who have already dismissed it.
- Users are already authenticated and have permission to view the case; no additional authorization logic is needed for the modal.
- Markdown rendering is handled client-side; no new backend endpoints are required.
- The entire system uses RTL (right-to-left) layout; the modal inherits this direction globally — no per-section direction detection is required.

## Clarifications

### Session 2026-03-31

- Q: What format does the pipeline output produce for the modal renderer? → A: Markdown text — agents output `##` headers, `-` bullet lists, numbered lists.
- Q: Should the Word export feature be included? → A: No — Word export feature removed from scope entirely.
- Q: How is the modal triggered? → A: Auto-opens on pipeline completion; a persistent button (replacing the PDF button, labelled with existing website UI terminology) also re-opens it.
- Q: What is the modal's text direction? → A: RTL — the entire system is RTL-based; the modal inherits this globally.
