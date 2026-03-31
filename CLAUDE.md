# law-laravel-project — Claude Code Guide

## Project Overview

Saudi Legal Orchestrator — a Laravel 11 application with a 9-agent AI pipeline for legal case analysis under Saudi law. Uses OpenRouter for LLM calls, RAG for law library retrieval, Livewire for real-time UI, and Laravel queues for async processing.

## Key Directories

- `app/Services/Agents/` — AI agent classes (Phase1 + Phase2 agents)
- `app/Services/RAG/` — RAG law parser, embeddings, processing
- `app/Services/OpenRouter/` — OpenRouter API client & service
- `app/Jobs/` — Queue jobs (law embedding, phase processing)
- `app/Http/Controllers/` — HTTP controllers including SSE streaming
- `resources/views/` — Blade + Livewire views
- `.specify/specs/` — Feature specifications (spec-driven development)
- `.specify/memory/` — Project constitution and memory

## Spec-Driven Development (Spec Kit)

This project uses [GitHub Spec Kit](https://github.com/github/spec-kit) for spec-driven development.

**Workflow**: Specify → Plan → Tasks → Implement

### Available Slash Commands

| Command | Purpose |
|---|---|
| `/speckit.specify` | Write a feature spec from a description |
| `/speckit.clarify` | Clarify open questions in a spec |
| `/speckit.plan` | Create a technical implementation plan |
| `/speckit.tasks` | Break plan into discrete tasks |
| `/speckit.implement` | Execute tasks and implement the feature |
| `/speckit.analyze` | Analyze existing code or a spec |
| `/speckit.checklist` | Generate a quality checklist |
| `/speckit.constitution` | View/update project constitution |

**Rule**: No implementation without a spec. Always start with `/speckit.specify <feature description>`.

## Tech Stack

- **Backend**: Laravel 11, PHP 8.x
- **Frontend**: Blade, Livewire, Alpine.js, Tailwind CSS
- **AI**: OpenRouter API (multi-model LLM), RAG embeddings
- **Queue**: Laravel database queues
- **Dev**: SQLite, Docker (docker-compose)

## Common Commands

```bash
php artisan serve          # Start dev server
php artisan queue:work     # Start queue worker
php artisan migrate        # Run migrations
php artisan db:seed        # Seed law library
```

## Active Technologies
- PHP 8.x / Laravel 11 + Livewire, Alpine.js, Tailwind CSS, Guzzle HTTP, OpenRouter API (001-legal-counsel-system)
- SQLite (dev) / MySQL (prod), Redis (event queue), Local disk (case files) (001-legal-counsel-system)
- PHP 8.x / Laravel 11 + Blade templating, Tailwind CSS (CDN, already loaded), Alpine.js, Material Symbols Outlined icons (002-case-output-redesign)
- N/A (view-only changes; reads `AgentDefinitions::all()`, `$case->outputs`, `$case->agentExecutions`) (002-case-output-redesign)
- PHP 8.x / Laravel 11 + Blade templates, Tailwind CSS (CDN), vanilla JavaScript, Guzzle HTTP (via OpenRouterClient), OpenRouter API (004-ai-audit-modal)
- SQLite (dev) / MySQL (prod) for case data; local disk for file uploads; no new tables (audit is ephemeral) (004-ai-audit-modal)
- PHP 8.x / Laravel 11 + Guzzle HTTP, OpenRouter API, Redis (events), Alpine.js (frontend) (007-pipeline-quality-overhaul)
- SQLite (dev) / MySQL (prod), local disk for case files (`storage/app/cases/{id}/`) (007-pipeline-quality-overhaul)
- PHP 8.x / Laravel 11 + Blade, Livewire, Alpine.js, Tailwind CSS, Guzzle HTTP, Puter.js (CDN), OpenRouter API, Laravel Queue (database driver) (008-puter-provider-switch)
- SQLite (dev) / MySQL (prod) — 3 new columns on `users` table; no new tables (008-puter-provider-switch)
- PHP 8.x / Laravel 11 + Guzzle HTTP (OpenRouter/Puter API), Livewire, Alpine.js, Tailwind CSS (009-pipeline-output-quality)
- SQLite (dev) / MySQL (prod) — no schema changes needed (009-pipeline-output-quality)
- PHP 8.x / Laravel 11 + PromptBuilder (custom), OpenRouter API (Guzzle), Playwright (Node.js, MCP) (010-arabic-output-quality)
- Local disk — `storage/app/cases/{id}/outputs/` (case outputs), SQLite (dev) (010-arabic-output-quality)
- PHP 8.x / Laravel 11 + Blade, Alpine.js, Tailwind CSS (CDN), marked.js (CDN — lightweight Markdown renderer, no build step) (011-formatted-output-modal)
- SQLite (dev) — no schema changes required (011-formatted-output-modal)

## Recent Changes
- 001-legal-counsel-system: Added PHP 8.x / Laravel 11 + Livewire, Alpine.js, Tailwind CSS, Guzzle HTTP, OpenRouter API
