# Research: Case Output Page Redesign

**Date**: 2026-03-22 | **Branch**: `002-case-output-redesign`

---

## Decision 1: Pipeline Tracker DOM Update Mechanism

**Decision**: Listen to existing `window.dispatchEvent` custom SSE events already broadcast by `agent-timeline-live.blade.php`.

**Rationale**: The `agent-timeline-live` component already dispatches `sse:agent.started`, `sse:agent.completed`, and `sse:agent.failed` to `window`. The new `pipeline-tracker` component can subscribe to these with `window.addEventListener(...)` with zero new infrastructure. Opening a second `EventSource` is forbidden (already caused a bug in the prior SSE fix).

**Alternatives considered**:
- Second EventSource connection — rejected (duplicate SSE, prior bug, FR-014 says preserve existing behaviour)
- Livewire polling — rejected (HTTP polling forbidden by constitution §I)
- Custom Livewire component — rejected (over-engineering; no new deps per FR-013)

---

## Decision 2: Tracker Initialization on Page Load

**Decision**: Read `$case->agentExecutions` in the Blade controller/view, serialize to `@json()`, and initialize tracker bubble states in `DOMContentLoaded`.

**Rationale**: Identical pattern to `$outputsByAgent` already proven in `agent-timeline-live`. Blade renders the PHP collection into a JS constant; the tracker's init function iterates it and calls `updateBubble(agentNumber, status)` for each.

**Alternatives considered**:
- AJAX fetch on load — rejected (extra round trip; page already has the data)
- Store status in data attributes on Blade-rendered HTML — viable but less maintainable than a JS constant for the tracker's JS logic

---

## Decision 3: PDF Export Loading State

**Decision**: Synchronous spinner — on `click`, disable the `<button>`, swap its inner HTML to a spinner icon + "جارٍ التحضير..." label, call `window.open(url, '_blank')`, then restore the button after 3 seconds via `setTimeout`.

**Rationale**: PDF generation is synchronous at the Laravel level (`PdfExportService` returns a file response). Opening in a new tab triggers the browser's native download mechanism. The 3-second timeout is sufficient for any PDF to begin downloading before the button re-enables. No backend change required (per clarification Q1, Option A).

**Alternatives considered**:
- Async job + polling — rejected (requires backend job + new route; out of scope)
- Fetch API with blob download — viable but adds JS complexity; new tab approach is simpler and already works

---

## Decision 4: Navbar Overlap

**Decision**: The `layouts/app.blade.php` layout uses `<main class="flex-1 flex flex-col overflow-y-auto">` with a sticky `<header>` inside it. Because the sticky parent is the scroll container (not the viewport), the header cannot overlap content — it stays at the top of the scroll area and content starts below it. The `p-8` padding on the content wrapper is sufficient.

**Rationale**: Confirmed by reading the layout file. The `h-screen overflow-hidden` on the outer flex container and `overflow-y-auto` on `<main>` form a CSS scroll jail — sticky elements inside it stick to the top of `<main>`, not the viewport. No overlap can occur from this architecture.

**Action**: No structural change needed. Add `sm:p-8 p-6` to content wrapper to improve mobile breathing room. Verify with 375px viewport.

---

## Decision 5: Removing `agent-output-panel` Include

**Decision**: Remove the `@include('components.agent-output-panel', ...)` line from `show.blade.php`. The component file itself is retained (not deleted) to avoid breaking any other references.

**Rationale**: FR-003 requires exactly one live-output area. The `agent-output-panel` is the duplicate dark terminal that shows the same output as the inline streaming in `agent-timeline-live`. The timeline component already shows output inline in each agent card. Removing the include is sufficient — the component JS already only listens to window events (no independent EventSource), so removal is safe.

---

## Decision 6: Removing `output-chain` Include

**Decision**: Remove `@include('components.output-chain', ...)` from `show.blade.php`.

**Rationale**: The `output-chain` component renders a static accordion showing each agent's output filenames. This information is superseded by the new pipeline tracker which shows agents visually with real-time status. Removing it reduces page clutter (spec priority P3: "Single Consolidated Live Output").

---

## Decision 7: Phase Gate Banner Position

**Decision**: Move the Phase 3 gate `@if($statusVal === 'phase2_completed')` block to appear immediately after the pipeline tracker and before `@include('components.phase2-approval-modal')` and before `@include('components.agent-timeline-live')`.

**Rationale**: FR-006 states the banner must be "positioned above the agent list." Currently it appears after the case header card, which is above the timeline — already roughly correct. Making it the second full-width block after the tracker ensures it is visible without scrolling on any desktop viewport.

---

## Decision 8: Collapsed Default Enforcement

**Decision**: In `agent-timeline-live.blade.php`, the `initializeFromDB()` function must NOT set the `open` attribute on completed agents regardless of case status. Only the currently in-progress agent gets `open` set plus a `scrollIntoView()` call.

**Rationale**: FR-011 + clarification Q4 (Option A): all cards collapsed on completed cases. Current code in `initializeFromDB()` may expand the last agent — this must be corrected.
