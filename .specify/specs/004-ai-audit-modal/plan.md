# Implementation Plan: AI-Powered Input Auditing Modal

**Branch**: `004-ai-audit-modal` | **Date**: 2026-03-24 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/004-ai-audit-modal/spec.md`

## Summary

Replace the existing Phase 2 approval modal with an AI-powered input auditing modal that scores case input completeness via OpenRouter LLM calls, presents tiered feedback (required/recommended/optional), and allows inline resolution with real-time score updates. The modal is a drop-in replacement — same triggers, same endpoints, same dismissal behavior — with an audit layer added before the user proceeds.

## Technical Context

**Language/Version**: PHP 8.x / Laravel 11
**Primary Dependencies**: Blade templates, Tailwind CSS (CDN), vanilla JavaScript, Guzzle HTTP (via OpenRouterClient), OpenRouter API
**Storage**: SQLite (dev) / MySQL (prod) for case data; local disk for file uploads; no new tables (audit is ephemeral)
**Testing**: Manual end-to-end verification per Constitution Principle III (self-testing after every change)
**Target Platform**: Docker-containerized web application (Linux server)
**Project Type**: Web application (server-rendered with async JavaScript)
**Performance Goals**: Audit response within 5s (target), 10s soft timeout, 30s hard ceiling; modal interactive within 1s; re-audit within 2s of input change
**Constraints**: No new pages (Constitution VI); all LLM calls via existing OpenRouterService; RTL Arabic UI; no Alpine.js (not loaded in layout)
**Scale/Scope**: Single-user modal interaction; one concurrent audit call per modal session; single context (Phase 2 approval) for initial implementation

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Real-Time First | **PASS** | Score bar updates immediately on audit response; re-audit results push to UI on arrival via async fetch. No page refresh needed. Not a streaming pipeline — it's a request/response cycle triggered by user action, which is appropriate for this interaction pattern. |
| II. Zero-Cache UI | **PASS** | Modal is Blade component (server-rendered). Audit API response uses no-cache headers. No new static assets requiring content-hash. |
| III. Self-Testing | **PASS** | Plan includes manual verification steps for each user story. |
| IV. Human-Readable Output | **PASS** | All audit feedback is designed as plain-language labels and reasons. No raw JSON, IDs, or stack traces shown to user. Score is a percentage. |
| V. Agent Logic from SKILL.md | **N/A** | The audit is a standalone utility LLM call, not an agent in the pipeline. It does not modify agent behavior. Prompt template is defined in the audit service, not in SKILL.md. |
| VI. No New Pages | **PASS** | Replaces existing `phase2-approval-modal.blade.php` component within `cases/show.blade.php`. No new page files. |
| VII. General Standards | **PASS** | Simple service + controller + Blade approach. Config in env vars. Single working state at each phase. |

No violations. No complexity tracking needed.

## Project Structure

### Documentation (this feature)

```text
specs/004-ai-audit-modal/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
│   └── audit-api.md     # Audit endpoint contract
└── tasks.md             # Phase 2 output (via /speckit.tasks)
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/
│   └── CaseController.php          # Add audit() method
├── Services/
│   └── InputAuditService.php       # NEW — audit prompt + response parsing
config/
└── legal.php                       # Add audit_passing_threshold, audit config
resources/views/components/
└── phase2-approval-modal.blade.php # REWRITE — full modal replacement
routes/
└── web.php                         # Add audit route
```

**Structure Decision**: Minimal footprint — one new service class, one new route, one rewritten Blade component, and config additions. No new models, migrations, or pages. The `InputAuditService` follows the same pattern as existing agent services (inject `OpenRouterService`, build prompt, parse JSON response).
