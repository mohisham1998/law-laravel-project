# Implementation Plan: Legal-Counsel — AI-Powered Legal Case Management System

**Branch**: `001-legal-counsel-system` | **Date**: 2026-03-19 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/001-legal-counsel-system/spec.md`

## Summary

Enhance the existing Saudi Legal Orchestrator to fully implement the Legal-Counsel specification. The application already has a working foundation: 12 agents (Phase 1 + Phase 2 + Phase 3), Redis-based SSE streaming, RAG law library with vector embeddings, Laravel queue processing, and an Arabic RTL UI. The implementation focuses on:

1. **Reworking agent prompts and outputs** to match the spec's structured output requirements (`.jsonl`, `.json`, dual-file outputs per agent)
2. **Eliminating per-case law uploads** — replace with RAG-only law retrieval throughout the pipeline
3. **Adding missing agents** — Fortification Agent (Phase 3) for brief hardening
4. **Implementing pipeline resume/re-run** — resume from last completed agent, re-run from any chosen agent forward
5. **Building the self-correction loop** — auto-detect and fix content errors within each agent
6. **Creating the PDF export service** — Arabic RTL PDF generation with court formatting
7. **Creating skill.md** — the single source of truth for all agent behavior (Constitution Principle V)
8. **Enhancing the UI** — richer agent panels, correction notifications, Phase 3 gate, export button

## Technical Context

**Language/Version**: PHP 8.x / Laravel 11
**Primary Dependencies**: Livewire, Alpine.js, Tailwind CSS, Guzzle HTTP, OpenRouter API
**Storage**: SQLite (dev) / MySQL (prod), Redis (event queue), Local disk (case files)
**Testing**: PHPUnit (Laravel built-in), Browser testing via manual validation per Constitution III
**Target Platform**: Docker containerized Linux server, accessed via web browser
**Project Type**: Web application (monolith — Laravel full-stack)
**Performance Goals**: No fixed pipeline time limit. SSE streaming within 2 seconds of generation (SC-002)
**Constraints**: All output in Arabic. RTL layout. No page refreshes for live updates. Single case per session.
**Scale/Scope**: Single-user system (no auth required). Hundreds of cases, thousands of law articles in RAG DB.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| # | Principle | Status | Notes |
|---|-----------|--------|-------|
| I | Real-Time First | PASS | SSE streaming already implemented via `CaseEventService` + `CaseStreamController`. Agent output streams token-by-token via `OpenRouterService::completeStream()`. Reconnection with backoff needed — verify existing implementation. |
| II | Zero-Cache UI | PASS | Vite handles asset hashing. `Cache-Control: no-store` set on SSE responses. `PreventBrowserCacheInLocal` middleware exists. Docker asset busting via entrypoint. |
| III | Self-Testing After Every Change | PASS | Each implementation phase will end with manual UI verification per this principle. |
| IV | Human-Readable Output Always | PASS | All agent output displayed as Arabic prose. FR-023 enforces no raw JSON/IDs in UI. QA Agent (9) strips all machine artifacts. |
| V | Agent Logic From skill.md | ACTION REQUIRED | `skill.md` does NOT exist yet. Must be created before any agent logic changes. `PromptBuilder` already reads from configurable path. |
| VI | No New Pages | PASS | All 15 pages exist. Implementation enhances `cases/show.blade.php`, `dashboard.blade.php`, `settings.blade.php`, and existing components. No new page files. |
| VII | General Development Standards | PASS | Docker reproducible, env-var config, incremental working states. |

**Gate Result**: PASS with one action item — create `skill.md` as first task.

## Project Structure

### Documentation (this feature)

```text
.specify/specs/001-legal-counsel-system/
├── spec.md              # Feature specification (complete)
├── plan.md              # This file
├── research.md          # Phase 0 output — technical decisions
├── data-model.md        # Phase 1 output — entity/schema analysis
├── quickstart.md        # Phase 1 output — dev setup guide
├── contracts/           # Phase 1 output — interface contracts
│   ├── sse-events.md    # SSE event type contracts
│   ├── agent-outputs.md # Agent output file contracts
│   └── api-endpoints.md # HTTP endpoint contracts
└── tasks.md             # Phase 2 output (via /speckit.tasks)
```

### Source Code (repository root)

```text
app/
├── Console/Commands/         # Artisan commands (existing)
├── Enums/                    # CaseStatus, AgentStatus, ErrorType, PhaseType
├── Http/
│   ├── Controllers/
│   │   ├── CaseController.php          # Case CRUD + Phase gates + retry + PDF
│   │   ├── CaseStreamController.php    # SSE streaming endpoint
│   │   ├── DashboardController.php     # Dashboard stats
│   │   ├── DocumentController.php      # Document upload/preview
│   │   ├── LawController.php           # Law registry CRUD
│   │   ├── LawLibraryController.php    # RAG law library management
│   │   └── SettingsController.php      # User settings + OpenRouter config
│   └── Middleware/
│       └── PreventBrowserCacheInLocal.php
├── Jobs/
│   ├── ProcessPhase1Job.php            # Phase 1 queue job
│   ├── ProcessPhase2Job.php            # Phase 2 queue job (9 agents)
│   ├── ProcessLawFileJob.php           # Parse law file → articles
│   └── GenerateLawEmbeddingsJob.php    # Generate embeddings for articles
├── Models/
│   ├── LegalCase.php                   # Core case entity (UUID PK)
│   ├── CaseDocument.php                # Uploaded case files
│   ├── CaseOutput.php                  # Agent output storage
│   ├── AgentExecution.php              # Agent run metrics
│   ├── ErrorLog.php                    # Error tracking
│   ├── CaseMetrics.php                 # Aggregated case metrics
│   ├── RequiredLaw.php                 # Phase 1 identified laws (REWORK: RAG-sourced)
│   ├── CaseLaw.php                     # Per-case law files (DEPRECATE: use RAG only)
│   ├── EvidenceRepositoryEntry.php     # Evidence categorization
│   ├── LawRegistry.php                 # Master law definitions
│   ├── LawFile.php                     # Law document files
│   ├── LawArticle.php                  # Parsed law articles
│   ├── LawEmbedding.php                # Vector embeddings (1536-dim)
│   └── User.php                        # User model
├── Services/
│   ├── Agents/
│   │   ├── Phase1AnalysisAgent.php     # REWORK: RAG-based law identification
│   │   ├── Phase2/
│   │   │   ├── Phase2BaseAgent.php     # ENHANCE: add self-correction loop
│   │   │   ├── LeadCounselAgent.php    # Agent 1: REWORK prompts + outputs
│   │   │   ├── EvidenceManagerAgent.php # Agent 2: REWORK → chunks.jsonl output
│   │   │   ├── ChainOfCustodyAgent.php # Agent 3: REWORK → RAG retrieval + statutes_index
│   │   │   ├── TimelineExtractorAgent.php # Agent 4: REWORK → timeline.json output
│   │   │   ├── LawManagerAgent.php     # Agent 5: REWORK → issues + adversary analysis
│   │   │   ├── StatuteMatcherAgent.php # Agent 6: REWORK → confidence threshold + quotes
│   │   │   ├── DefenseStrategistAgent.php # Agent 7: REWORK → risk matrix + defense layers
│   │   │   ├── LegalDrafterAgent.php   # Agent 8: REWORK → legal syllogism format
│   │   │   └── QualityAssuranceAgent.php # Agent 9: REWORK → AI erasure + QA checklist
│   │   └── Phase3/
│   │       ├── JudgeAgent.php          # Agent 10: ENHANCE prompts
│   │       ├── DevilsAdvocateAgent.php # Agent 11: ENHANCE prompts
│   │       └── FortificationAgent.php  # Agent 12: NEW — brief hardening
│   ├── Orchestration/
│   │   ├── LegalOrchestrator.php       # ENHANCE: resume + re-run support
│   │   ├── PromptBuilder.php           # Reads skill.md for prompts
│   │   └── GateValidator.php           # REWORK: RAG-based gates
│   ├── RAG/
│   │   ├── LawParserService.php        # Article extraction (10 regex patterns)
│   │   ├── LawProcessingService.php    # Parse + embed orchestration
│   │   ├── EmbeddingService.php        # Vector generation (1536-dim)
│   │   └── VectorSearchService.php     # Cosine similarity search
│   ├── OpenRouter/
│   │   ├── OpenRouterClient.php        # HTTP client for OpenRouter
│   │   ├── OpenRouterService.php       # Retry logic + streaming
│   │   └── OpenRouterException.php     # Custom exception
│   ├── CaseEventService.php            # Redis SSE event queue
│   └── PdfExportService.php            # NEW — Arabic RTL PDF generation
├── resources/views/
│   ├── layouts/app.blade.php           # RTL Arabic layout (existing)
│   ├── pages/
│   │   ├── cases/show.blade.php        # ENHANCE: Phase 3 gate, PDF export, agent re-run
│   │   ├── cases/create.blade.php      # Minor: remove law upload references
│   │   ├── cases/index.blade.php       # Minor: status label updates
│   │   ├── dashboard.blade.php         # Minor: metrics display
│   │   ├── settings.blade.php          # Existing: model selection
│   │   └── ...                         # Other pages: minimal changes
│   └── components/
│       ├── agent-timeline-live.blade.php    # ENHANCE: correction events, re-run buttons
│       ├── agent-output-panel.blade.php     # ENHANCE: typewriter effect polish
│       ├── phase2-approval-modal.blade.php  # REWORK: RAG-based approval
│       └── pdf-export-button.blade.php      # ENHANCE: Phase 3 support
└── .agent/skills/legal-counsel/
    └── SKILL.md                        # NEW — single source of truth for agent behavior
```

**Structure Decision**: Existing Laravel monolith structure preserved. All changes are enhancements to existing files or additions within existing directories. The only new service class is `PdfExportService`. The only new agent class is `FortificationAgent`. The only new directory is `.agent/skills/legal-counsel/`.

## Implementation Phases

### Phase A: Foundation (skill.md + Agent Base Rework)

1. Create `SKILL.md` — defines all 12 agent roles, their prompts, inputs, outputs, and behavioral rules
2. Rework `Phase2BaseAgent` — add self-correction loop (3 retries for content errors), error memory reading, structured output support
3. Rework `GateValidator` — remove per-case law upload checks, add RAG availability checks
4. Rework `LegalOrchestrator` — add resume-from-agent and re-run-from-agent capabilities
5. Update `CaseEventService` — add `agent.correction` event type for self-correction notifications

### Phase B: Agent Pipeline Rework (Agents 1-9)

Rework each agent's prompts and output processing to match spec requirements:

1. **Agent 1 (Lead Counsel)**: Produce `01_lead_plan.md` + `01_acceptance_criteria.json`
2. **Agent 2 (Evidence)**: Produce `02_chunks.jsonl` + `02_ingestion_report.md`
3. **Agent 3 (Integrity)**: RAG retrieval → `03_statutes_index.jsonl` + `03_conflict_warnings.md` + `03_chain_of_custody_summary.md`
4. **Agent 4 (Timeline)**: Produce `04_timeline.json` + `04_timeline.md` + `04_entities_index.md`
5. **Agent 5 (Law Lead)**: Produce `05_issues_to_statutes.md` + `05_procedural_notes.md` + `05_adversary_evidence_analysis.md` + `05_matching_guidelines.json`
6. **Agent 6 (Statute Matcher)**: Confidence ≥ 0.70 enforcement, literal quotes, produce `06_statutes_map.jsonl` + `06_accepted_matches.md` + `06_rejections.md` + `06_gaps_and_todo.md`
7. **Agent 7 (Strategy)**: Risk matrix, three-tier defense, produce `07_risk_matrix.md` + `07_defense_layers.md` + `07_charges_scenarios.json` + `07_mitigation_opportunities.md`
8. **Agent 8 (Drafter)**: Legal syllogism format, dual citations, produce `08_defense_arguments.md` + `08_final_brief.md` + `08_arguments_index.json`
9. **Agent 9 (QA)**: Full QA checklist, AI erasure, produce `09_QA_summary.md` + `09_violations.md` + `09_fixes_applied.json` + `09_todo_back_to_agents.md` + `09_final_brief_v2.md`

### Phase C: Phase 1 Rework + Phase 2 Gate

1. Rework `Phase1AnalysisAgent` — query RAG database for relevant laws instead of requesting uploads
2. Rework `phase2-approval-modal` — show RAG-identified laws for user confirmation
3. Update `CaseController::startPhase2()` — gate on RAG availability, not uploaded files
4. Deprecate `CaseLaw` model usage — keep table for backward compatibility but stop requiring per-case uploads

### Phase D: Phase 3 + Fortification

1. Create `FortificationAgent` (Agent 12) — reads judge notes + devil's advocate, produces hardened brief
2. Enhance `JudgeAgent` and `DevilsAdvocateAgent` prompts per spec
3. Add Phase 3 gate UI in `cases/show.blade.php`
4. Add Phase 3 job processing

### Phase E: PDF Export

1. Create `PdfExportService` — Arabic RTL PDF with court-submission margins
2. Integrate with `CaseController::pdf()` — already has route and controller stub
3. Use `dompdf` or `mpdf` (Arabic/RTL support) for PDF generation

### Phase F: UI Enhancements

1. Enhance `agent-timeline-live` — add re-run buttons per agent, correction event display
2. Enhance `agent-output-panel` — polish typewriter effect, smooth animations
3. Update `phase2-approval-modal` — RAG-sourced law display
4. Add PDF export button visibility logic (Phase 2 or Phase 3 complete)
5. Add pipeline resume UI (retry from specific agent)

### Phase G: Error Memory + Self-Correction

1. Implement `memory/errors_log.md` file-based error memory per case
2. Each agent reads error memory before starting
3. Self-correction loop in `Phase2BaseAgent` — detect and fix content errors (confidence < 0.70, quote mismatches, etc.)
4. `agent.correction` SSE events for UI notification
5. Pause after 3 failed attempts with Arabic Retry/Cancel options

## Complexity Tracking

> No constitution violations detected. All implementation uses existing patterns and structures.

| Decision | Rationale | Alternative Rejected |
|----------|-----------|---------------------|
| Keep `CaseLaw` table | Backward compatibility with existing cases | Dropping table would require migration cleanup of foreign keys |
| File-based error memory (`memory/errors_log.md`) alongside DB `error_logs` table | Spec requires file-based memory readable by agents via prompt context | Using only DB would require agents to query DB directly |
| `dompdf`/`mpdf` for PDF | Laravel ecosystem compatibility, Arabic RTL support | `wkhtmltopdf` requires system binary installation |

## Dependencies & Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Agent output quality depends on LLM model capability | High | Use `skill.md` with detailed structured output instructions; enforce via post-processing validation |
| PDF Arabic RTL rendering | Medium | Evaluate `mpdf` (better RTL) vs `dompdf` in research phase; test with real Arabic content |
| OpenRouter rate limits during 9-agent sequential pipeline | Medium | Existing retry logic (3 attempts, 2s delay). Add exponential backoff. Pipeline resume handles outages. |
| Large case documents exceeding LLM context windows | Medium | Agent 2 chunks documents (1200-1800 chars). Agents process chunks, not full documents. |
| Vector search performance at scale | Low | Current cosine similarity over all embeddings works for thousands of articles. Flag for future optimization if law library grows significantly. |
