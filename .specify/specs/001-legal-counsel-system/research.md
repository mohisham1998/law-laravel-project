# Research: Legal-Counsel System

**Feature**: `001-legal-counsel-system` | **Date**: 2026-03-19

## R-001: PDF Generation Library for Arabic RTL

**Decision**: Use `mpdf/mpdf` (mPDF) for PDF generation.

**Rationale**: mPDF has native RTL support, handles Arabic ligatures and diacritics correctly, supports custom fonts (e.g., Amiri, Cairo), and integrates well with Laravel via `composer require mpdf/mpdf`. It supports CSS-based page margins for court-submission formatting.

**Alternatives considered**:
- `dompdf/dompdf`: Good Laravel integration but weaker RTL support; Arabic text rendering has known issues with complex ligatures.
- `wkhtmltopdf` (via `spatie/laravel-pdf`): Excellent rendering but requires system binary installation, complicates Docker setup.
- `browserless/chrome`: Best rendering (actual Chrome engine) but requires external service, adds infrastructure complexity.

---

## R-002: Agent Output Format — Structured Files vs Database-Only

**Decision**: Agents produce both structured files on disk AND store content in the `case_outputs` database table. Files are the canonical source; DB records provide indexing and quick retrieval.

**Rationale**: The spec defines specific output files per agent (e.g., `02_chunks.jsonl`, `06_statutes_map.jsonl`). These files serve as inter-agent contracts — each agent reads predecessor files. Storing in both locations ensures: (a) agents can read files directly, (b) the UI can query the DB for display, (c) pipeline resume checks file existence on disk.

**Alternatives considered**:
- Database-only: Simpler but breaks the file-existence gate pattern used by `GateValidator`.
- Files-only: Works for agents but requires file reads for every UI display.

---

## R-003: Self-Correction Loop Architecture

**Decision**: Implement self-correction as a retry-with-validation pattern inside `Phase2BaseAgent::executeWithStreaming()`.

**Rationale**: After each agent produces output, a validation step checks for: confidence scores below 0.70, missing dual citations, quote mismatches against `03_statutes_index.jsonl`, and abrogated article references. If validation fails, the agent re-runs with the error context appended to its prompt (max 3 attempts). This keeps correction logic centralized in the base class.

**Alternatives considered**:
- Separate validator service per agent: More modular but 9x more code; validation rules are similar enough to centralize.
- Post-pipeline batch correction: Loses the benefit of correcting before downstream agents consume bad output.

**Implementation pattern**:
```
execute():
  for attempt in 1..3:
    output = callLLM(prompt + errorContext)
    violations = validate(output)
    if no violations:
      emit(agent.completed)
      return output
    else:
      emit(agent.correction, violations)
      log to memory/errors_log.md
      errorContext = format(violations)

  // All 3 attempts failed
  emit(agent.failed, "self-correction exhausted")
  pause pipeline with Retry/Cancel
```

---

## R-004: Pipeline Resume and Re-run Architecture

**Decision**: Implement resume and re-run as modifications to `LegalOrchestrator::runPhase2()`.

**Rationale**: The orchestrator already loops agents 1-9 sequentially. Resume = skip agents whose output files exist on disk. Re-run from agent N = delete output files for agents N through 9, then run normally.

**Implementation**:
- **Resume**: Before each agent, check if all its output files exist. If yes, skip. If no, execute.
- **Re-run from N**: Accept `$startFromAgent` parameter. Delete outputs for agents N-9. Run pipeline normally (resume logic handles the rest).
- **UI**: Add "Re-run from here" button on each completed agent card in the timeline.

**Alternatives considered**:
- Separate resume job class: Unnecessary complexity; same orchestrator logic applies.
- Database-driven skip logic: File existence is more reliable since files are the inter-agent contract.

---

## R-005: Error Memory — File-Based vs Database-Based

**Decision**: Use both file-based `memory/errors_log.md` (for agent prompt context) AND the existing `error_logs` database table (for UI display and querying).

**Rationale**: Agents need error history as part of their LLM prompt context. The simplest way to include it is as a markdown file appended to the prompt. The database table continues to serve the UI for displaying error history. Both are updated atomically when an error is logged.

**Storage path**: `storage/app/cases/{case_id}/memory/errors_log.md`

**File format** (per entry):
```markdown
---
### Error #{n} — {date}
- **Discovering Agent**: Agent {number} ({name})
- **Error Type**: {type}
- **Responsible Agent**: Agent {number}
- **Details**: {description}
- **Impact**: {impact assessment}
- **Fix Applied**: {correction description}
- **Lesson Learned**: {what to avoid in future}
---
```

---

## R-006: Structured Output Parsing from LLM

**Decision**: Use JSON code blocks within markdown output, parsed by PHP post-processing.

**Rationale**: The spec requires structured outputs like `.jsonl` and `.json` files alongside markdown. LLMs reliably produce JSON when instructed to wrap it in code blocks. Post-processing extracts JSON blocks, validates structure, and writes to the appropriate file format.

**Implementation**:
- Agent prompts in `skill.md` instruct the LLM to produce output in a specific format with clearly delimited JSON sections.
- `Phase2BaseAgent` post-processor extracts code blocks tagged as `json` or `jsonl`.
- Validation ensures required fields are present before writing files.
- If JSON parsing fails, log error and retry (part of self-correction loop).

**Alternatives considered**:
- Structured output mode (JSON mode): Not all OpenRouter models support it reliably.
- Two-pass approach (prose first, then structured): Doubles LLM calls and cost.

---

## R-007: Phase 3 Fortification Agent Design

**Decision**: Create `FortificationAgent` as a Phase 3 agent (Agent 12) that reads all upstream outputs plus Phase 3 review notes.

**Rationale**: The Fortification Agent is the most complex agent — it must: classify observations from judge/devil's advocate, correct critical issues by re-invoking agents 3/6/8, embed legal dilemma paragraphs, and apply full AI erasure. It should use the non-streaming `complete()` method (like other Phase 3 agents) since its output is a complete rewrite of the brief.

**Correction delegation**: When the Fortification Agent identifies a critical issue requiring Agent 3, 6, or 8 to re-run, it does NOT re-invoke the full pipeline. Instead, it includes the correction instruction in its own prompt and produces the corrected content directly, documenting what was changed and why.

**Alternatives considered**:
- Actually re-running upstream agents: Too complex, risks infinite loops, and breaks the sequential pipeline model.
- Manual correction by user: Defeats the purpose of automation.

---

## R-008: SSE Reconnection with Exponential Backoff

**Decision**: Implement reconnection logic in the frontend JavaScript using `EventSource` API with manual reconnection.

**Rationale**: The native `EventSource` API auto-reconnects but without configurable backoff. Constitution Principle I requires exponential backoff for transparent healing. Using a custom reconnection wrapper provides control over retry timing.

**Implementation**:
```javascript
// In agent-timeline-live.blade.php
class SSEConnection {
  connect() { this.source = new EventSource(url); }
  onError() {
    delay = Math.min(1000 * 2^attempt, 30000); // 1s, 2s, 4s, 8s, 16s, 30s max
    setTimeout(() => this.connect(), delay);
  }
}
```

**Current state**: `CaseStreamController` already handles reconnection by checking case status. Frontend needs the backoff wrapper added.

---

## R-009: Deprecating Per-Case Law Uploads

**Decision**: Keep `required_laws` and `case_laws` tables but stop using them for the pipeline gate. Phase 2 gate checks RAG database availability instead.

**Rationale**: The spec says all laws come from the RAG database. However, existing cases in the database have `required_laws` and `case_laws` records. Dropping these tables would break existing data. Instead, we deprecate their usage:
- Phase 1 still creates `RequiredLaw` records (for display purposes — showing which laws the system identified)
- Phase 2 gate no longer requires `CaseLaw` uploads
- Agent 3 queries RAG database instead of reading uploaded law files
- `CaseLaw` model remains but is no longer required for pipeline progression

**Migration**: No schema changes needed. Only logic changes in `GateValidator` and `CaseController::startPhase2()`.
