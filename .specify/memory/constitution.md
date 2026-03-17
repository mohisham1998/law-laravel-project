# Legal Orchestrator Constitution

<!--
Sync Impact Report:
Version: 2.0.0 (Amended - API-First removed, Portal UI adopted)
Previous Version: 1.0.0
Ratified: 2026-03-14
Last Amended: 2026-03-16
Modified Principles:
  - REMOVED: I. API-First Architecture (NON-NEGOTIABLE)
  - ADDED: I. Laravel Full-Stack with Portal UI (MANDATORY)
  - II. Docker Containerization - unchanged
  - III–XII: Renumbered from former II–XII (no content change)
  - V. Stitch - clarified as optional/supplementary; Blade primary for case dashboard
Templates Status:
  ✅ constitution.md - Updated
  ⚠ plan-template.md - No change required (Constitution Check remains valid)
  ⚠ spec-template.md - No change required
  ⚠ tasks-template.md - No change required
Follow-up TODOs: None
-->

## Project Identity

**Name**: Saudi Legal Case Orchestration System  
**Purpose**: Production-ready Laravel 11 application that orchestrates AI agents to process Saudi legal cases through a 3-phase workflow (Analysis → 9-Agent Processing → Optional Judicial Arbitration), with a web portal for case management and live dashboard.  
**Frontend**: Laravel Blade views (RTL Arabic, Tailwind CSS, Cairo font) for case dashboard, case show, documents, and settings; optional Google Stitch for specific screens where applicable.  
**Backend**: Laravel 11 with PostgreSQL, Redis, OpenRouter AI integration, and server-rendered UI.

---

## Core Principles

### I. Laravel Full-Stack with Portal UI (MANDATORY)

**Rule**: The application serves both server-rendered UI and API. Laravel Blade is the primary UI for the case dashboard, case show, documents, and settings. All portal UI MUST follow the project design system.

**Rationale**: Legal professionals need a single web portal to create cases, view live agent progress, and export PDFs. Blade with Tailwind enables fast iteration, RTL Arabic support, and consistency with the existing dashboard styling without splitting frontend across another stack.

**Requirements**:
- Blade templates and view components MAY be used for case management, dashboard, documents, and settings pages
- All new UI components MUST follow the existing dashboard styling (primary color #006b34, Cairo font, RTL layout, rounded-xl, shadow-sm)
- RTL layout and Arabic typography (Cairo) MUST be used for Arabic content
- API endpoints MAY coexist for mobile or external integrations; web routes and Blade views are first-class
- Long-running operations (e.g. agent processing) MUST use queues; UI MAY use SSE or polling for real-time updates
- No duplicate implementation of the same flow in both Blade and another frontend unless explicitly scoped

**Validation**: PRs that add or change Blade views MUST adhere to the design system and RTL/Arabic requirements.

---

### II. Docker Containerization (MANDATORY)

**Rule**: All environments (development, staging, production) MUST run in Docker containers. No exceptions.

**Rationale**: Ensures consistent environments, simplifies deployment, enables horizontal scaling, and isolates dependencies.

**Required Services**:
1. **app** - Laravel API (PHP 8.3 + Nginx)
2. **postgres** - PostgreSQL 16 database
3. **redis** - Redis 7 (queue backend + caching)
4. **horizon** - Laravel Horizon dashboard
5. **worker** - Queue workers (4 replicas for 100 concurrent cases)

**Docker Standards**:
- Multi-stage Dockerfile for optimized production images
- Docker Compose for local development
- Separate `docker-compose.prod.yml` for production
- Volume mount `.agent/skills/legal-counsel/SKILL.md` for hot-reload
- Health check endpoints for all services
- Environment-specific `.env` files (never commit secrets)
- Container resource limits defined (CPU, memory)

**Deployment**: `docker compose up -d` for dev, orchestration (Kubernetes/Docker Swarm) for production.

---

### III. Agent Orchestration Philosophy

**Rule**: Multi-call orchestration where Laravel makes separate, sequential API calls for each of 9+ agents.

**Rationale**: Full control over each agent, independent error handling, granular monitoring, and ability to retry specific agents.

**Requirements**:
- Each agent is independently executable and testable
- **Gate-by-file validation**: No agent runs without prerequisite outputs from previous agents
- **Self-correcting error loop**: Agents learn from mistakes via `errors_log.md`
- Track execution metrics per agent (tokens, duration, cost)
- Support agent retry without restarting entire pipeline

**External Dependencies**:
- OpenRouter API (Claude 3.5 Sonnet) as AI engine
- SKILL.md file-based configuration (no hardcoded prompts)
- PostgreSQL for JSON/JSONL storage
- Laravel Horizon for queue management
- Laravel Sanctum for API authentication

---

### IV. Senior-Level Laravel Development (CRITICAL)

**Rule**: ALL Laravel implementation MUST follow `@senior-developer` skill guidelines from `.cursor/rules/senior-developer.mdc`.

**Premium Craftsmanship Standards**:
- Every line of code intentional and refined
- Performance-focused: Sub-1.5s API response times, optimized queries
- Innovation over convention when it enhances the system
- Advanced patterns: Service containers, dependency injection, design patterns
- Sophisticated error handling with graceful degradation
- Premium logging and observability (no `var_dump` debugging)

**Database & Query Optimization**:
- Eloquent ORM with advanced query optimization
- Eager loading to prevent N+1 queries
- Database indexing strategy for all foreign keys and frequently queried columns
- Query performance monitoring (log queries > 100ms)

**API Design Standards**:
- RESTful conventions with resource-based endpoints
- Consistent JSON response structure: `{data: {}, meta: {}, errors: []}`
- API versioning strategy (v1 prefix)
- Comprehensive error responses with actionable messages

**PHP 8.3+ Features**:
- Typed properties, enums, readonly properties
- Constructor property promotion
- Match expressions over switch statements
- Named arguments for clarity
- Attributes for routing and validation

---

### V. Stitch Dashboard Development Standards (When Used)

**Rule**: The case dashboard and case show UI are implemented in Laravel Blade. When additional screens or experiences are built with Google Stitch, they MUST use `stitch-loop` and `shadcn-ui` skills.

**stitch-loop Skill (Autonomous Screen Building)**:
- Use `.stitch/DESIGN.md` as single source of design system truth
- Generate screens via Stitch MCP tools (`generate_screen_from_text`)
- Follow baton system (`.stitch/next-prompt.md`) for iterative development
- Download assets: HTML + PNG to `.stitch/designs/{screen-name}.html`
- Integrate with Laravel API using fetch calls with error handling
- Update `.stitch/metadata.json` with screen metadata

**shadcn-ui Skill (Component Consistency)**:
- Data tables for case lists (sortable, filterable, paginated)
- Progress indicators for agent execution timeline
- Modals for laws upload and settings
- Toast notifications for real-time updates
- Markdown renderer for legal brief display
- Form components with validation feedback

**8 Required Screens**:
1. Dashboard Home - Case list, statistics, recent activity
2. New Case Form - Upload intake text + documents
3. Case Detail View - Phase progress, agent status, live updates
4. Laws Upload Modal - After Phase 1, upload required statutes
5. Output Viewer - Display legal briefs with Markdown rendering
6. Error Log Viewer - Self-correcting loop errors with fixes
7. Agent Timeline - Visual progress tracker for all 9 agents
8. Settings Page - Confidence threshold, SKILL.md version info

**Stitch Screen Requirements** (ALL 8 screens):
- Reference design system from `.stitch/DESIGN.md` Section 6
- Implement shadcn/ui patterns (not custom components)
- Include API endpoint URLs in code comments
- Handle loading states with skeleton screens
- Handle error states with user-friendly messages
- Support RTL layout for Arabic content
- Use Tajawal font for Arabic text
- Authentication token handling (localStorage)
- 5-second polling for real-time updates
- Responsive design (desktop + mobile breakpoints)

---

### VI. SKILL.md Integration & Hot-Reload

**Rule**: SKILL.md is the single source of truth for agent behavior. File-based only, never in database.

**File-Based Configuration**:
- Read fresh from disk on every agent execution (no caching by default)
- Track `skill_version` and `skill_hash` for every case processed
- Support hot-reload: queue restart picks up new SKILL.md immediately
- SKILL.md lives in `.agent/skills/legal-counsel/SKILL.md`

**Version Control**:
- Versioned in git with semantic versioning in frontmatter
- Validation endpoint: `POST /api/admin/validate-skill`
- Never store SKILL.md content in database
- Track which version generated each case for debugging

**Continuous Improvement Workflow**:
1. Edit `.agent/skills/legal-counsel/SKILL.md` locally
2. Test validation: `curl POST /api/admin/validate-skill`
3. Commit: `git commit -m "feat: improve Agent 6 statute matching"`
4. Deploy: `git pull && docker compose restart worker`
5. New cases use updated SKILL.md immediately

**Docker Integration**: Volume mount SKILL.md for hot-reload without container rebuild.

---

### VII. Test-Driven Development (NON-NEGOTIABLE)

**Rule**: TDD mandatory. Tests written → User approved → Tests fail → Then implement.

**Testing Requirements**:
- **Unit tests**: All service classes (80% coverage minimum)
- **Integration tests**: Agent orchestration flow, gate validation
- **Feature tests**: All API endpoints with authentication
- **End-to-end test**: Actual case from `intake.txt` + `docs/` + `laws/`
- Mock external dependencies (OpenRouter, Stitch) in tests

**Test Standards**:
- PHPUnit for all tests
- Pest PHP for expressive syntax (optional)
- Database transactions for test isolation
- Factories and seeders for test data
- Parallel test execution for speed

**Quality Gates**:
- All tests MUST pass before PR merge
- No decrease in code coverage allowed
- Performance tests for API endpoints (< 200ms)
- Security tests for authentication and authorization

---

### VIII. Data Integrity & Security

**Input Validation**:
- Max 10MB per document upload
- Only `.txt` and `.md` files allowed for laws and documents
- Arabic text encoding validation (UTF-8)
- Sanitize all user inputs to prevent XSS
- Rate limiting: 10 cases per hour per user

**Output Storage**:
- Store all 19 intermediate output files for full traceability
- JSONL files stored as JSON in PostgreSQL for queryability
- Markdown files stored as text with metadata
- Confidence scores tracked for quality monitoring
- Soft deletes for audit trail

**Authentication & Authorization**:
- Laravel Sanctum token-based authentication for API; session or Sanctum for web UI
- Rate limiting per user and per endpoint
- CORS configured for allowed frontend origins (Stitch CDN when used, or same-origin for Blade)
- API keys encrypted in database
- Token expiration and refresh strategy (7-day expiry)

**PostgreSQL Security**:
- Database credentials in environment variables only
- Connection pooling (min: 5, max: 20)
- Encrypted connections (SSL/TLS)
- Regular backups (daily automated)
- Read replicas for reporting queries

---

### IX. Legal Domain Compliance

**Saudi Legal System Requirements**:
- Confidence threshold: **0.70 minimum** for statute matching
- Mandatory `CASE:{}` and `LAW:{}` references in all analysis
- **Zero hallucination policy**: Only cite from `03_statutes_index.jsonl`
- Abrogation checking: Newer laws supersede older ones
- Fiqh principles as logical fallback when statutes unavailable

**Three-Phase Processing** (Strict Sequential):
1. **Phase 1**: Analysis only, wait for user to provide laws
2. **Phase 2**: 9 agents run sequentially with gate validation
3. **Phase 3**: Optional judicial arbitration (manual trigger only)

**Quality Assurance**:
- Self-correcting loop logs all errors with fixes applied
- Agent 9 (QA) validates all outputs before completion
- Cases marked as `completed_with_warnings` if confidence < 0.70
- Manual review queue for low-confidence matches

---

### X. Performance & Scalability

**Resource Management**:
- Queue workers: 4 workers for 100 concurrent cases
- Redis for queue backend (fast, reliable)
- Database connection pooling (min: 5, max: 20)
- Horizontal scaling: stateless API design

**Docker Resource Limits**:
- **app**: 2 CPU cores, 4GB RAM
- **worker** (each): 1 CPU core, 2GB RAM
- **postgres**: 2 CPU cores, 8GB RAM
- **redis**: 1 CPU core, 2GB RAM
- **horizon**: 0.5 CPU cores, 1GB RAM

**Performance Targets**:
- API response time: < 200ms for status checks
- Polling endpoints: cached for 5 seconds
- Queue job processing: < 30 minutes for Phase 2
- Database queries: < 100ms (log slower queries)

**Cost Optimization**:
- Track token usage per agent for cost analysis
- Average cost per case: $0.26 (Phase 2) to $0.32 (Phase 3)
- Monitor API call duration for performance optimization
- Alert on abnormal token consumption (> 100k tokens/case)

**Monitoring (Premium Standards)**:
- Laravel Telescope for API call tracking
- Horizon for queue job monitoring
- Structured logging to `legal` channel with context
- Metrics dashboard: tokens, duration, errors, completion rate
- Alert thresholds for production issues

---

### XI. Milestone-Based Delivery

**Incremental Delivery**:
- Each milestone MUST be independently testable
- Deliver working software at end of each milestone
- No "big bang" releases (incremental feature rollout)
- User acceptance testing after each milestone
- Demo-ready at end of each sprint

**Quality Gates (Per Milestone)**:
- ✅ All tests pass (unit, integration, feature)
- ✅ Code review approved
- ✅ Performance benchmarks met (< 200ms API, < 30min processing)
- ✅ Security scan passed (no critical vulnerabilities)
- ✅ SKILL.md validation passed
- ✅ Stitch screens functional with API integration
- ✅ Docker containers build and run successfully

**Documentation Requirements**:
- API documentation (OpenAPI/Swagger)
- Deployment guide for production
- Developer setup instructions (Docker-based)
- SKILL.md update workflow
- Stitch screen integration guide
- Troubleshooting playbook

---

### XII. Technical Debt Management

**Code Maintainability (Senior Standards)**:
- Follow PSR-12 coding standards
- Use PHP 8.3+ features (typed properties, enums, readonly)
- Avoid premature optimization (profile first)
- Refactor when cyclomatic complexity > 10
- Document complex business logic

**Dependency Management**:
- Keep Laravel and dependencies up to date
- Security patches applied within 48 hours
- Document all third-party dependencies with rationale
- Avoid dependencies with known vulnerabilities
- Regular dependency audits (monthly)

**Technical Debt Tracking**:
- Log technical debt items in GitHub issues
- Prioritize debt that impacts performance or security
- Allocate 20% of each milestone to debt reduction
- Never ship with known critical issues

---

## Development Workflow

### Skill-Based Development (MANDATORY)

**Reference these skills during implementation**:
- `@senior-developer` - All Laravel code
- `stitch-loop` - All Stitch screen generation
- `shadcn-ui` - All Stitch component patterns
- `.agent/skills/legal-counsel/SKILL.md` - Agent logic

### Git Branching Strategy

- Feature branches for each milestone
- Pull requests required for all changes
- No direct commits to `main`
- SKILL.md updates tracked in commit messages with rationale

### Code Review Standards

- All PRs reviewed by senior developer (or AI with `@senior-developer` skill)
- Check for adherence to constitution principles
- Performance review (query analysis, N+1 checks)
- Security review (input validation, authentication)
- Docker configuration review (resource limits, health checks)

### Deployment Process (Docker-Based)

1. **Build**: `docker compose build`
2. **Test**: `docker compose -f docker-compose.test.yml up --abort-on-container-exit`
3. **Deploy**: Zero-downtime blue-green deployment
4. **Migrate**: Database migrations run before code deployment
5. **Restart**: Queue workers restart to pick up new SKILL.md
6. **Monitor**: Health check endpoints monitored
7. **Rollback**: Documented rollback plan tested quarterly

---

## Critical Success Factors

1. ✅ **Senior-level Laravel code** following `@senior-developer` skill
2. ✅ **Laravel Blade portal UI** with consistent design system (RTL, Cairo, primary styling)
3. ✅ **SKILL.md hot-reload** without code redeployment
4. ✅ **Gate-by-file validation** preventing agent execution errors
5. ✅ **Self-correcting loop** learning from mistakes
6. ✅ **Stitch screens** (when used) via `stitch-loop` + `shadcn-ui` skills
7. ✅ **Docker containerization** for all environments
8. ✅ **Milestone-based delivery** with quality gates
9. ✅ **Production-ready** from day one (no prototypes)

---

## Governance

### Amendment Process

1. Propose amendment with rationale and impact analysis
2. Review by senior developer or technical lead
3. Update constitution with version bump:
   - **MAJOR**: Backward incompatible principle removals/redefinitions
   - **MINOR**: New principles or materially expanded guidance
   - **PATCH**: Clarifications, wording fixes, non-semantic refinements
4. Update dependent templates (plan, spec, tasks)
5. Communicate changes to all team members
6. Document in Sync Impact Report (HTML comment at top)

### Compliance Review

- All PRs MUST verify compliance with constitution
- Complexity MUST be justified against principles
- Deviations require explicit approval and documentation
- Quarterly constitution review for relevance and effectiveness

### Runtime Development Guidance

- Use `CLAUDE.md` for agent-specific development guidance
- Reference constitution principles in all technical decisions
- Escalate conflicts between principles to technical lead

---

**Version**: 2.0.0  
**Ratified**: 2026-03-14  
**Last Amended**: 2026-03-16

---

## Appendix: Technology Stack

### Core Technologies
- **Backend**: Laravel 11 (PHP 8.4)
- **Database**: PostgreSQL 16
- **Cache/Queue**: Redis 7
- **AI Engine**: OpenRouter API (Claude 3.5 Sonnet)
- **Portal UI**: Laravel Blade, Tailwind CSS, RTL Arabic (Cairo font)
- **Optional frontend**: Google Stitch (Project ID: 18254556907662508752) when used
- **Authentication**: Laravel Sanctum
- **Queue Management**: Laravel Horizon
- **Containerization**: Docker + Docker Compose

### Development Tools
- **Testing**: PHPUnit, Pest PHP
- **Code Quality**: PHPStan, Laravel Pint
- **API Documentation**: Scribe, OpenAPI
- **Monitoring**: Laravel Telescope, Horizon
- **Version Control**: Git

### Infrastructure
- **Orchestration**: Docker Compose (dev), Kubernetes/Swarm (prod)
- **CI/CD**: GitHub Actions
- **Hosting**: Cloud provider with Docker support
- **CDN**: For Stitch static assets
- **Backups**: Automated daily PostgreSQL backups
