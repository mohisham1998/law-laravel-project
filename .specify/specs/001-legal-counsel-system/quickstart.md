# Quickstart: Legal-Counsel Development

**Feature**: `001-legal-counsel-system` | **Date**: 2026-03-19

## Prerequisites

- Docker + Docker Compose
- PHP 8.x + Composer
- Node.js 18+ + npm
- Redis server (local or Docker)
- OpenRouter API key

## Setup

```bash
# Clone and install
git clone <repo>
cd law-laravel-project
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Configure .env
# OPENROUTER_API_KEY=your-key-here
# OPENROUTER_DEFAULT_MODEL=anthropic/claude-3.5-sonnet
# REDIS_HOST=127.0.0.1

# Database
php artisan migrate
php artisan db:seed  # Seeds law library + test user

# Build assets
npm run build  # or npm run dev for HMR
```

## Running

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Queue worker (processes agents)
php artisan queue:work --timeout=900

# Terminal 3: Vite dev server (HMR)
npm run dev
```

## Docker

```bash
docker-compose up -d
# Includes: PHP-FPM, Nginx, Redis, SQLite
# Auto-runs migrations and seeds on first start
```

## Test Login

- Email: `test@example.com`
- Password: `password`

## Key Files for This Feature

| Area | Files |
|------|-------|
| Agent base | `app/Services/Agents/Phase2/Phase2BaseAgent.php` |
| Agent prompts | `.agent/skills/legal-counsel/SKILL.md` |
| Orchestrator | `app/Services/Orchestration/LegalOrchestrator.php` |
| Gate validator | `app/Services/Orchestration/GateValidator.php` |
| SSE streaming | `app/Services/CaseEventService.php`, `app/Http/Controllers/CaseStreamController.php` |
| Phase 1 | `app/Services/Agents/Phase1AnalysisAgent.php`, `app/Jobs/ProcessPhase1Job.php` |
| Phase 2 | `app/Jobs/ProcessPhase2Job.php` |
| RAG search | `app/Services/RAG/VectorSearchService.php` |
| Case UI | `resources/views/pages/cases/show.blade.php` |
| Agent timeline | `resources/views/components/agent-timeline-live.blade.php` |
| Phase 2 gate | `resources/views/components/phase2-approval-modal.blade.php` |

## Development Workflow

1. Read `SKILL.md` before modifying any agent — it's the source of truth (Constitution V)
2. Never create new page files — enhance existing pages (Constitution VI)
3. Test every change via the UI before considering it done (Constitution III)
4. All dynamic responses must include `Cache-Control: no-store` (Constitution II)
5. All output must stream in real-time via SSE (Constitution I)

## Testing a Full Pipeline Run

1. Login → Create a new case with title + intake text + supporting documents
2. Watch Phase 1 stream in real-time → produces required laws list
3. Click "Start Phase 2" → watch 9 agents run sequentially
4. After completion → click "Export PDF"
5. Verify PDF is Arabic RTL with no AI traces
