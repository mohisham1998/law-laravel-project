# Implementation Plan: Case Output Page Redesign

**Branch**: `002-case-output-redesign` | **Date**: 2026-03-22 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/002-case-output-redesign/spec.md`

---

## Summary

Redesign `cases/show.blade.php` and its directly included Blade components to deliver a clearer multi-agent pipeline UX. The primary change is a new full-width pipeline tracker component (compact icon grid, 3 phases × 13 agents) placed above the existing content grid. Secondary changes: remove the duplicate `agent-output-panel` streaming terminal (FR-003), promote the Phase 3 gate banner above the agent list (FR-006), move the PDF export button into the case header area so it is visible without scrolling (FR-008/009 with synchronous spinner), fix the navbar/content overlap by verifying the `layouts/app.blade.php` top padding (FR-010), and enforce all-collapsed default on completed cases (FR-011). No new dependencies, no new pages, no backend changes.

---

## Technical Context

**Language/Version**: PHP 8.x / Laravel 11
**Primary Dependencies**: Blade templating, Tailwind CSS (CDN, already loaded), Alpine.js, Material Symbols Outlined icons
**Storage**: N/A (view-only changes; reads `AgentDefinitions::all()`, `$case->outputs`, `$case->agentExecutions`)
**Testing**: Manual browser verification against real case data (constitution §III); Docker container via `docker exec`
**Target Platform**: Desktop + mobile browsers (375px–1920px wide)
**Project Type**: Web application — Blade/Livewire frontend only
**Performance Goals**: Pipeline tracker SSE update < 500ms perceived lag (SC-007); DB pre-load within 2s on refresh (SC-003)
**Constraints**: No new frontend dependencies (FR-013); no backend/controller/job changes (spec Out of Scope); existing SSE infrastructure preserved (FR-014)
**Scale/Scope**: 1 page file + 4 existing Blade components modified; 1 new Blade component created

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Real-Time First | ✅ PASS | Pipeline tracker updates via existing SSE events; no polling added. FR-002 mandates real-time tracker updates. Existing SSE connection in `agent-timeline-live` is preserved. |
| II. Zero-Cache UI | ✅ PASS | Blade views use no compiled asset bundles; Tailwind is CDN. View cache cleared after changes (`php artisan view:clear`). No new cached layers introduced. |
| III. Self-Testing After Every Change | ✅ PASS | Each task ends with Docker-based browser verification step. Constitution requires visual/functional confirmation. |
| IV. Human-Readable Output Always | ✅ PASS | FR-004/005 require markdown-to-HTML rendering; no raw JSON exposed. Existing `renderMarkdown()` JS function preserved. |
| V. Agent Logic Comes From SKILL.md | ✅ PASS | No agent logic changes. Pure UI layout redesign. |
| VI. No New Pages — Enhance Existing UI | ✅ PASS | Only `cases/show.blade.php` and its included components are modified. One new Blade component (`pipeline-tracker`) created as a component (not a page). |
| VII. General Development Standards | ✅ PASS | Simple, minimal changes. Tailwind utilities only. Each task leaves system in working state. |

**Post-Phase-1 Re-check**: No violations introduced by design. All constitution gates remain green.

---

## Project Structure

### Documentation (this feature)

```text
specs/002-case-output-redesign/
├── plan.md              ← this file
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
├── quickstart.md        ← Phase 1 output
└── tasks.md             ← Phase 2 output (/speckit.tasks)
```

### Source Code (affected files)

```text
resources/views/
├── layouts/
│   └── app.blade.php                        ← VERIFY top padding (FR-010)
├── pages/cases/
│   └── show.blade.php                       ← PRIMARY: restructure layout
└── components/
    ├── pipeline-tracker.blade.php           ← NEW: full-width 3-phase icon grid
    ├── agent-timeline-live.blade.php        ← MODIFY: remove duplicate panel include; collapse logic
    ├── agent-output-panel.blade.php         ← REMOVE include from show.blade.php (FR-003)
    ├── pdf-export-button.blade.php          ← MODIFY: move to header; add spinner state
    └── output-chain.blade.php              ← REMOVE include from show.blade.php (replaced by tracker)
```

**Structure Decision**: Single Laravel web application. All changes are within `resources/views/`. No new routes, controllers, models, or migrations needed. The new `pipeline-tracker.blade.php` is a Blade component that reads `AgentDefinitions::all()` and `$case->agentExecutions` on page load, then updates via the existing SSE custom events (`sse:agent.started`, `sse:agent.completed`, `sse:agent.failed`) already dispatched by `agent-timeline-live`.

---

## Complexity Tracking

No constitution violations to justify.

---

## Phase 0: Research

*See [research.md](research.md) for full findings.*

### Key Decisions

| Decision | Chosen Approach | Rationale |
|----------|----------------|-----------|
| Pipeline tracker DOM update mechanism | Listen to existing `window.dispatchEvent` custom SSE events (`sse:agent.started`, `sse:agent.completed`, `sse:agent.failed`) | Already broadcast by `agent-timeline-live.blade.php`; zero new infrastructure |
| Tracker status on page load | Read `$case->agentExecutions` in PHP, serialize to JS via `@json()`, initialize tracker status in `DOMContentLoaded` | Same pattern as `$outputsByAgent` pre-load already in use |
| PDF export loading state | Synchronous: disable button + show spinner; listen to `click`, set disabled + innerHTML to spinner, let native browser handle download response | No backend change; matches FR-009 clarification (Option A) |
| Navbar overlap fix | `layouts/app.blade.php` already uses `<main class="flex-1 flex flex-col overflow-y-auto">` with sticky `<header>`. Content div is `<div class="p-8 flex-1">`. The sticky header is inside the scrollable main, not fixed to the viewport, so no overlap occurs for the majority of viewports. Verify and add `pt-0` guard if needed. | Existing layout confirmed correct; issue may have been resolved by prior fixes |
| Removing `agent-output-panel` include | Remove the `@include('components.agent-output-panel', ...)` line from `show.blade.php`. The terminal panel still exists as a component file but is no longer included. | FR-003: exactly one live-output area |
| Removing `output-chain` include | Remove `@include('components.output-chain', ...)` from `show.blade.php`. Replaced by the pipeline tracker which shows all agents visually. | Reduces clutter; tracker supersedes output-chain |
| Phase gate banner position | Move Phase 3 gate block above `@include('components.agent-timeline-live')` and below the pipeline tracker (FR-006: above the agent list) | Currently appears after the case header card but before the timeline — already roughly correct; needs to be above the timeline, not inside the content card |
| Collapsed default for completed cases | `agent-timeline-live` already uses `details` HTML element per agent card; set `open` attribute only on in-progress agent | Enforce in `initializeFromDB()`: never set `open` on completed agents on load |

---

## Phase 1: Design & Contracts

### Data consumed by new `pipeline-tracker` component

The tracker is a read-only view component. It consumes:

| Source | PHP Variable | Passed To JS Via |
|--------|-------------|-----------------|
| `AgentDefinitions::all()` | `$definitions` | `@foreach` in Blade |
| `$case->agentExecutions` collection | `$executionsByAgent` (keyed by `agent_number`) | `@json($executionsByAgent)` |
| `$case->status` | `$statusVal` | `@json($statusVal)` |

`$executionsByAgent` shape (serialized):
```json
{
  "0": { "status": "completed", "started_at": "...", "completed_at": "..." },
  "1": { "status": "in_progress", "started_at": "..." },
  "2": { "status": "pending" }
}
```

The tracker JS maps execution status → visual state:

| Execution Status | Tracker Visual |
|-----------------|---------------|
| `completed` | Green filled circle + checkmark icon |
| `in_progress` | Amber animated pulse circle + spinner icon |
| `retrying` | Amber animated pulse circle + refresh icon |
| `failed` | Red circle + error icon |
| `pending` / absent | Grey circle + schedule icon |

SSE update path: `agent-timeline-live` dispatches `sse:agent.started` → tracker JS listener updates bubble to `in_progress`. `sse:agent.completed` → tracker updates bubble to `completed`. `sse:agent.failed` → tracker updates bubble to `failed`. Progress bar percentage = `completed_count / 13 * 100`.

### Component Layout: `pipeline-tracker.blade.php`

```
┌─────────────────────────────────────────────────────────────────────┐
│  [pipeline-tracker] full-width card above the lg:grid-cols-3 grid  │
│                                                                     │
│  المرحلة الأولى (1 agent)                                           │
│  ┌──────────┐                                                        │
│  │  0 · تحليل │ ← bubble: number + short Arabic name + status dot   │
│  └──────────┘                                                        │
│                                                                     │
│  المرحلة الثانية (9 agents)                                          │
│  ┌───┐ ┌───┐ ┌───┐ ┌───┐ ┌───┐ ┌───┐ ┌───┐ ┌───┐ ┌───┐             │
│  │ 1 │ │ 2 │ │ 3 │ │ 4 │ │ 5 │ │ 6 │ │ 7 │ │ 8 │ │ 9 │             │
│  └───┘ └───┘ └───┘ └───┘ └───┘ └───┘ └───┘ └───┘ └───┘             │
│                                                                     │
│  المرحلة الثالثة (3 agents)                                          │
│  ┌────┐ ┌────┐ ┌────┐                                               │
│  │ 10 │ │ 11 │ │ 12 │                                               │
│  └────┘ └────┘ └────┘                                               │
│                                                                     │
│  ████████████████░░░░░░░░░░  7 / 13 مكتمل              [progressBar]│
└─────────────────────────────────────────────────────────────────────┘
```

On mobile (< 768px): each phase row wraps or scrolls horizontally (`overflow-x-auto`).

### Show page restructure: `show.blade.php`

New section order:

```
1. [Full-width] Session flash / breadcrumb / case header
2. [Full-width] pipeline-tracker component  ← NEW
3. [Full-width] Phase 3 gate banner (phase2_completed only)  ← MOVED UP
4. [Full-width] Phase 2 approval modal (awaiting_laws only)
5. [lg:grid-cols-3] Main grid:
   Left col (2/3):
     - agent-timeline-live (agent cards + live streaming inline)
     - Required laws
     - Documents
   Right col (1/3):
     - Quick Actions (includes PDF export button in header)
     - Retry card (failed/paused)
     - AI Insights
```

PDF export button moves from below the agent cards into the **Quick Actions** sidebar card as the first prominent action, always visible on desktop (sidebar is in-viewport on load).

### PDF export button with spinner (`pdf-export-button.blade.php` update)

The existing `<a href="..." target="_blank">` approach already triggers a download without page navigation. Add an `onclick` handler:

```javascript
function handlePdfExport(btn, url) {
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined animate-spin">progress_activity</span> جارٍ التحضير...';
    // Open PDF in new tab; re-enable button after 3s (browser handles download)
    window.open(url, '_blank');
    setTimeout(function() {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined">picture_as_pdf</span> تصدير PDF';
    }, 3000);
}
```

### Navbar overlap root cause

`layouts/app.blade.php` uses a flex column layout where the `<header>` is sticky at `top-0` inside the scrollable `<main>`. Because `<main>` is `overflow-y-auto` (not the viewport), the sticky header sticks within the scroll container — meaning it does NOT overlap page content. The `p-8` on the content div provides 32px of breathing room. The overlap symptom is likely caused by the page-level `<div class="flex h-screen overflow-hidden">` clipping the sidebar. On very narrow viewports the sidebar may push the main content. The fix: ensure the main content area's `p-8` is sufficient (may reduce to `p-6` on mobile via `sm:p-8`) — no structural change needed.

---

## Contracts

This feature is purely an internal UI enhancement — no public APIs, CLI contracts, or external interfaces are introduced or changed. The only "contract" is the SSE custom event shape, which is already established and must not change:

```
window event: 'sse:agent.started'   → detail: { agent_number, agent_name }
window event: 'sse:agent.output'    → detail: { agent_number, content }
window event: 'sse:agent.completed' → detail: { agent_number, agent_name, metrics }
window event: 'sse:agent.failed'    → detail: { agent_number, agent_name, error }
window event: 'sse:case.status_changed' → detail: { status }
```

The new `pipeline-tracker` component MUST listen to these exact event names and shapes. It must NOT open a new `EventSource` — it listens only to `window` events dispatched by `agent-timeline-live`.

---

## Quickstart

*See [quickstart.md](quickstart.md) for full dev setup steps.*

Short version for this feature:
1. `docker compose up -d`
2. `docker exec law-laravel-project-app-1 php artisan view:clear`
3. Navigate to a case in `phase2_processing` or `phase3_completed` status
4. Verify pipeline tracker appears full-width above the grid
5. Verify exactly one live-output streaming area on screen
6. Verify PDF export button visible in sidebar without scrolling
7. Verify phase gate banner visible without scrolling on `phase2_completed` case
