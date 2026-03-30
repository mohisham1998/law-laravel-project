<!--
  === Sync Impact Report ===
  Version change: 2.1.0 → 2.1.1
  Bump rationale: PATCH — standardize skill.md → SKILL.md casing in
  Principle V to match codebase (PromptBuilder.php) and plan/tasks artifacts.
  Linux/Docker deployment is case-sensitive.

  Modified principles:
    V.  "Agent Logic Comes From skill.md" → "Agent Logic Comes From SKILL.md"
        (4 occurrences of `skill.md` → `SKILL.md`)

  Added sections: None.
  Removed sections: None.

  Templates requiring updates:
    ✅ plan-template.md — No update needed.
    ✅ spec-template.md — No update needed.
    ✅ tasks-template.md — No update needed.
    ✅ CLAUDE.md — No update needed.

  Follow-up TODOs: None.
-->

# Legal-Counsel AI Agent Project Constitution

## Core Principles

### I. Real-Time First (NON-NEGOTIABLE)

- All data updates MUST propagate to the UI in real time. No feature
  may rely on manual page refresh to surface new information.
- The system MUST use WebSockets or Server-Sent Events (SSE) for all
  live updates. HTTP polling is forbidden.
- Every agent output, case update, document result, or workflow event
  MUST be pushed to the client immediately upon availability.
- WebSocket/SSE connections MUST include automatic reconnection with
  exponential back-off so disconnections heal transparently.
- AI agent responses MUST be streamed token-by-token, never buffered
  and delivered in bulk.

### II. Zero-Cache UI (NON-NEGOTIABLE)

- All UI changes MUST appear in the browser without any manual
  cache-busting step from the developer or user.
- All static assets (JS, CSS, fonts) MUST use content-hash filenames
  so new builds automatically invalidate the browser cache.
- Docker builds MUST bust stale layers when frontend assets change.
  No old bundles may be served from cached Docker layers.
- The dev server MUST run with Hot Module Replacement (HMR) or
  live-reload so changes appear within seconds of saving.
- All dynamic API responses MUST return `Cache-Control: no-store` to
  prevent proxies from serving stale data.

### III. Self-Testing After Every Change (NON-NEGOTIABLE)

- After implementing any feature or UI change, the agent MUST verify
  the feature is working by accessing the UI directly and confirming
  the behavior is correct.
- Every feature MUST have at least one validation step that proves it
  works end-to-end, not just that the code compiles.
- If a feature cannot be confirmed visually or functionally, it is
  NOT considered done.

### IV. Human-Readable Output Always (NON-NEGOTIABLE)

- All outputs shown to the user MUST be clean, readable, and
  self-explanatory.
- The system MUST NOT expose raw JSON, internal IDs, error stack
  traces, or machine-formatted data directly to the user interface.
- Dates, statuses, names, and results MUST be formatted in plain
  language the user can act on immediately.
- Error messages MUST explain what went wrong and what the user can
  do next.

### V. Agent Logic Comes From SKILL.md (NON-NEGOTIABLE)

- The `SKILL.md` file inside the legal-counsel agent directory is the
  single source of truth for every agent's flow, features, and
  behavior.
- No agent logic may be invented or assumed. All implementation MUST
  be derived from what `SKILL.md` defines.
- If `SKILL.md` is ambiguous, the agent MUST pause and ask for
  clarification before implementing.
- Changes to agent behavior require a corresponding update to
  `SKILL.md` first.

### VI. No New Pages — Enhance Existing UI (NON-NEGOTIABLE)

- All UI pages for this project already exist. The agent MUST NOT
  create new page files under any circumstance.
- Before implementing any UI feature, the agent MUST read and
  understand the current code in the relevant existing page(s).
- The agent MUST decide whether to revamp (rewrite substantially) or
  enhance (extend incrementally) the existing page to satisfy the
  requirement.
- If a spec implies a "new page", the agent MUST map it to the
  closest existing page and adapt that page instead.
- Existing pages (`resources/views/pages/`):
  - `dashboard.blade.php` — main dashboard
  - `ai-analysis.blade.php` — AI analysis view
  - `settings.blade.php` — application settings
  - `cases/index.blade.php` — case listing
  - `cases/create.blade.php` — new case form
  - `cases/show.blade.php` — case detail view
  - `cases/timeline.blade.php` — case timeline
  - `cases/show-retry-section.blade.php` — retry section
  - `documents/index.blade.php` — document listing
  - `laws/index.blade.php` — laws listing
  - `laws/show.blade.php` — law detail view
  - `law-library/index.blade.php` — law library listing
  - `law-library/create.blade.php` — add to law library
  - `law-library/edit.blade.php` — edit law library entry
  - `law-library/show.blade.php` — law library detail

### VII. General Development Standards

- Prefer simple, maintainable solutions over clever or
  over-engineered ones.
- Every implementation phase MUST end with a working, testable
  state — never leave the system in a broken intermediate state.
- Docker and environment setup MUST be reproducible from a single
  command with no manual steps.
- All configuration that varies between environments (dev, staging,
  prod) MUST live in environment variables, never hardcoded.

## Project Context

**Saudi Legal Orchestrator** — a Laravel 11 application with a
9-agent AI pipeline for legal case analysis under Saudi law.

- **Backend**: Laravel 11, PHP 8.x
- **Frontend**: Blade + Livewire + Alpine.js + Tailwind CSS
- **AI**: OpenRouter API (multi-model), RAG with vector embeddings
- **Queue**: Laravel queues (database driver)
- **Storage**: Local disk + SQLite (dev) / MySQL (prod)
- **Docker**: docker-compose for containerized deployment
- **Agents**: `app/Services/Agents/` — Phase 1 + Phase 2 pipeline
- **RAG**: `app/Services/RAG/` — law parsing, embeddings, retrieval
- **Streaming**: `CaseStreamController` + Livewire for live updates

## Development Workflow

- Specs go in `.specify/specs/` before implementation begins.
- Use `/speckit.specify` to draft a spec, `/speckit.plan` to plan,
  `/speckit.implement` to execute.
- All new features require a spec document approved before coding
  starts.
- Test against real case data when possible
  (see `docs/PRODUCTION_REAL_CASE_SETUP.md`).

## Governance

This constitution supersedes all other project practices and
guidelines. Every pull request, code review, and implementation
decision MUST comply with the principles above.

### Amendment Procedure

1. Propose the change with rationale in writing.
2. Update this file with the amendment.
3. Increment the version per semantic versioning:
   - **MAJOR**: Principle removal, redefinition, or backward-incompatible
     governance change.
   - **MINOR**: New principle added or existing principle materially
     expanded.
   - **PATCH**: Clarifications, wording fixes, non-semantic refinements.
4. Update `LAST_AMENDED_DATE` to the date of the change.
5. Propagate changes to dependent templates if principles affect their
   structure or gates.

### Compliance

- Claude MUST follow spec-driven development:
  Spec → Plan → Tasks → Implement.
- No implementation without a spec.
- NON-NEGOTIABLE principles cannot be overridden by any spec,
  plan, or ad-hoc instruction. They apply unconditionally.

**Version**: 2.1.1 | **Ratified**: 2026-03-19 | **Last Amended**: 2026-03-19
