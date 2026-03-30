# Implementation Plan: Production-Ready Agent Pipeline Quality Overhaul

**Branch**: `007-pipeline-quality-overhaul` | **Date**: 2026-03-26 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/007-pipeline-quality-overhaul/spec.md`

## Summary

Refactor the 13-agent legal orchestrator pipeline to guarantee high-quality, hallucination-free LLM output regardless of model tier. The core changes are: (1) replace the generic PromptBuilder that dumps 617-line SKILL.md into every agent with agent-specific focused prompts, (2) add structured output templates with few-shot examples, (3) add deterministic PHP validators that cross-check citations against upstream data, (4) fix Phase 1 token starvation (150→4096), (5) centralize per-agent temperature/max_tokens config, and (6) improve RAG context delivery to prevent silent truncation.

## Technical Context

**Language/Version**: PHP 8.x / Laravel 11
**Primary Dependencies**: Guzzle HTTP, OpenRouter API, Redis (events), Alpine.js (frontend)
**Storage**: SQLite (dev) / MySQL (prod), local disk for case files (`storage/app/cases/{id}/`)
**Testing**: Playwright MCP (UI end-to-end), manual pipeline runs
**Target Platform**: Linux server (Docker), Windows dev
**Project Type**: Web service (Laravel monolith)
**Performance Goals**: Pipeline completes within 30 minutes per case, each agent within 3 minutes
**Constraints**: No new dependencies, backward compatible with existing case data, SKILL.md is sole source of truth
**Scale/Scope**: Single-user to small team, 13 agents across 3 phases, ~15 PHP files modified

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Real-Time First | PASS | No changes to SSE/streaming infrastructure. Agent output still streams token-by-token. |
| II. Zero-Cache UI | PASS | No frontend asset changes. API responses already use `Cache-Control: no-store`. |
| III. Self-Testing After Every Change | PASS | Each implementation phase will be tested via Playwright MCP UI testing. |
| IV. Human-Readable Output Always | PASS | Improved output quality directly serves this principle — better structured Arabic legal output. |
| V. Agent Logic Comes From SKILL.md | PASS | Core of this feature. PromptBuilder will extract sections from SKILL.md, not hardcode prompts. All prompt content traces to SKILL.md. |
| VI. No New Pages | PASS | No UI pages created. Changes are entirely in backend services. |
| VII. General Development Standards | PASS | Config-driven, no hardcoded values, each phase leaves system in working state. |

**Gate result: ALL PASS — proceed to Phase 0.**

## Project Structure

### Documentation (this feature)

```text
specs/007-pipeline-quality-overhaul/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
└── tasks.md             # Phase 2 output (/speckit.tasks)
```

### Source Code (repository root)

```text
app/
├── Services/
│   ├── Orchestration/
│   │   ├── PromptBuilder.php          # REWRITE — agent-specific prompt extraction
│   │   ├── GateValidator.php          # MODIFY — add deterministic validation
│   │   ├── LegalOrchestrator.php      # MODIFY — integrate validators
│   │   └── OutputValidator.php        # NEW — deterministic citation/quote validator
│   ├── Agents/
│   │   ├── Phase1AnalysisAgent.php    # MODIFY — fix max_tokens, focused prompt
│   │   └── Phase2/
│   │       ├── Phase2BaseAgent.php    # MODIFY — config-driven temp/tokens, context delivery
│   │       ├── LeadCounselAgent.php   # MODIFY — focused prompt + template
│   │       ├── EvidenceManagerAgent.php    # MODIFY — focused prompt + template
│   │       ├── ChainOfCustodyAgent.php    # MODIFY — focused prompt + template
│   │       ├── TimelineExtractorAgent.php # MODIFY — focused prompt + template
│   │       ├── LawManagerAgent.php        # MODIFY — focused prompt + template + few-shot
│   │       ├── StatuteMatcherAgent.php    # MODIFY — focused prompt + template + few-shot + validator
│   │       ├── DefenseStrategistAgent.php # MODIFY — focused prompt + template
│   │       ├── LegalDrafterAgent.php      # MODIFY — focused prompt + template + few-shot + validator
│   │       └── QualityAssuranceAgent.php  # MODIFY — focused prompt + deterministic checks
│   └── Agents/Phase3/
│       ├── JudgeAgent.php             # MODIFY — focused prompt
│       ├── DevilsAdvocateAgent.php    # MODIFY — focused prompt + temp fix
│       └── FortificationAgent.php     # MODIFY — focused prompt
├── config/
│   └── legal.php                      # MODIFY — add per-agent config block
└── .agent/skills/legal-counsel/
    └── SKILL.md                       # READ ONLY — source of truth (not modified)
```

**Structure Decision**: Standard Laravel monolith structure. All changes within existing `app/Services/` hierarchy. One new file (`OutputValidator.php`) for deterministic validation logic. No new directories needed.

## Complexity Tracking

No constitution violations — table not needed.
