# Implementation Plan: Productionize Smart Legal Advisor - Dynamic UI Values

**Branch**: `006-dynamic-ui-values` | **Date**: 2026-03-24 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/006-dynamic-ui-values/spec.md`

## Summary

This feature replaces all static/hardcoded UI values in the Smart Legal Advisor with dynamic, real-data-driven behavior. The implementation involves:
1. Creating API endpoints for case data retrieval (progress, stats, agent status)
2. Updating blade templates to use dynamic data from database queries
3. Implementing functional pause/refresh controls for AI Analysis
4. Ensuring real-time updates via existing SSE infrastructure

**Technical Approach**: Use existing Laravel controllers + new API endpoints to serve dynamic data. Existing SSE streaming already handles real-time updates (Principle I compliant). Frontend uses Blade templates with Alpine.js for reactivity.

## Technical Context

**Language/Version**: PHP 8.x (Laravel 11)  
**Primary Dependencies**: Laravel 11, Livewire, Alpine.js, Tailwind CSS, OpenRouter API, RAG Services  
**Storage**: MySQL (production), SQLite (development)  
**Testing**: PHPUnit (framework), manual Playwright for E2E  
**Target Platform**: Linux server (Docker container)  
**Project Type**: Web application  
**Performance Goals**: Real-time updates < 1s latency via SSE, page load < 2s  
**Constraints**: Zero-cache UI (Principle II), Real-time-first (Principle I), No new pages (Principle VI)  
**Scale/Scope**: Multi-user legal platform, 13 agents per case, 50+ pages

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Gate | Status | Notes |
|------|--------|-------|
| **Principle I: Real-Time First** | ✅ PASS | Feature uses existing SSE infrastructure for live updates. No polling required. |
| **Principle II: Zero-Cache UI** | ✅ PASS | Laravel mix with hash-based asset names. API responses use no-store cache headers. |
| **Principle III: Self-Testing** | ✅ PASS | Each UI component must be verified via Playwright after implementation. |
| **Principle IV: Human-Readable Output** | ✅ PASS | Already addressed in spec - using Arabic labels and localized values. |
| **Principle V: SKILL.md** | N/A | This feature doesn't modify agent logic. |
| **Principle VI: No New Pages** | ✅ PASS | Only enhancing existing pages: ai-analysis.blade.php, dashboard.blade.php, cases/index.blade.php, cases/show.blade.php |
| **Principle VII: General Standards** | ✅ PASS | Following Laravel conventions, environment-based config. |

## Project Structure

### Documentation (this feature)

```text
specs/006-dynamic-ui-values/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command)
```

### Source Code (repository root)

This is an existing Laravel 11 web application. No new directories required.

```text
# Laravel Application Structure
app/
├── Http/
│   └── Controllers/
│       ├── CaseController.php       # ENHANCE - add dynamic data methods
│       ├── DashboardController.php  # ENHANCE - add real statistics
│       └── Api/
│           └── CaseController.php   # ADD - case status API endpoints

resources/
└── views/
    ├── pages/
    │   ├── ai-analysis.blade.php    # ENHANCE - replace static values
    │   ├── dashboard.blade.php      # ENHANCE - replace static charts
    │   └── cases/
    │       ├── index.blade.php      # ENHANCE - dynamic navigation
    │       └── show.blade.php       # ENHANCE - dynamic timeline

routes/
├── web.php                             # Existing routes
└── api.php                             # ADD - new API endpoints
```

**Structure Decision**: Standard Laravel 11 web application structure. No new directories. All changes within existing `app/`, `resources/views/`, and `routes/` directories as per Principle VI (No New Pages).

## Phase 0: Research

### Research Tasks

**Decision**: No additional research needed - the feature relies on existing Laravel patterns and the required data models (LegalCase, AgentExecution, CaseDocument, CaseOutput) already exist with the necessary fields.

### Research Findings

No unknowns to resolve. The feature uses:
- Existing LegalCase model with `progress_percentage`, `status`, `phase` fields
- Existing AgentExecution model with `status`, `progress_percentage`, `agent_number`
- Existing CaseDocument model for document counts
- Existing relationship methods for law matches

---

## Phase 1: Design

The design is already included in the specification (spec.md). Key implementation details:

### Data Flow

1. **AI Analysis Page**: Add route parameter for case ID, query LegalCase + AgentExecution for progress data
2. **Dashboard**: Enhance DashboardController to compute real statistics from database
3. **Pause/Refresh**: Add API endpoints in CaseController, connect to existing status handling

### API Endpoints Needed

- `GET /api/cases/{id}/progress` - Returns case progress, stage states, counts
- `POST /api/cases/{id}/pause` - Pauses case processing
- `GET /api/dashboard/stats` - Returns real statistics for dashboard

### Blade Template Updates

- Replace hardcoded percentages with `{{ $case->progress_percentage }}`
- Replace hardcoded counts with dynamic calculations
- Add Alpine.js reactivity for pause/refresh buttons

---

## Phase 2: Implementation

(Tasks will be generated by `/speckit.tasks`)

### Implementation Notes

1. **Always use SSE** for real-time updates (Principle I)
2. **Never cache** API responses - use `Cache-Control: no-store`
3. **Test visually** after each component change (Principle III)
4. **Keep existing pages** - enhance don't replace (Principle VI)
5. **Use Arabic** for all user-facing labels

---

## Complexity Tracking

> None required - no Constitution violations.
