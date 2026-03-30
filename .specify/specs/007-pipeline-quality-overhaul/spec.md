# Feature Specification: Production-Ready Agent Pipeline Quality Overhaul

**Feature Branch**: `007-pipeline-quality-overhaul`
**Created**: 2026-03-26
**Status**: Draft
**Input**: User description: "Refactor the 13-agent legal orchestrator pipeline to guarantee high-quality, hallucination-free LLM output regardless of model tier. SKILL.md is the single source of truth."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Agent-Specific Focused Prompts (Priority: P1)

A legal professional submits a case using a budget LLM model. Instead of dumping the entire 617-line SKILL.md into every agent call, the system extracts only the relevant section for each agent and sends a focused, self-contained prompt. Even a small model can follow clear, scoped instructions.

**Why this priority**: This is the root cause of poor output quality. Generic prompts cause models to lose focus when processing 617 lines of mixed instructions. Every downstream fix depends on agents receiving clear, agent-specific prompts.

**Independent Test**: Submit a case with a budget model. Inspect agent outputs — each should produce structured content matching its SKILL.md section, not random or generic text.

**Acceptance Scenarios**:

1. **Given** a case submitted with any LLM model, **When** Agent 6 (Statute Matcher) processes the case, **Then** its prompt contains only the Statute Matcher section from SKILL.md (not the full file), plus the General Rules, plus context — totaling under 200 lines of instructions.
2. **Given** a case submitted with a budget model, **When** any agent executes, **Then** the prompt begins with the agent's specific role, required inputs, required outputs, and behavior rules — not a generic instruction.
3. **Given** the SKILL.md file is updated with new agent instructions, **When** the PromptBuilder extracts agent sections, **Then** the extracted sections reflect the updated content (no hardcoded prompt duplication).

---

### User Story 2 - Structured Output Templates with Few-Shot Examples (Priority: P1)

Each agent receives an explicit output template showing the exact format expected, with a concrete few-shot example. Even a weak model can fill in a template when shown a complete example.

**Why this priority**: Without structured templates, agents produce free-form text that downstream agents cannot reliably parse. Templates + examples are the highest-leverage fix for budget models.

**Independent Test**: Run a case and verify each agent's output matches its expected structure (correct JSON keys, required sections present, proper citation format).

**Acceptance Scenarios**:

1. **Given** Agent 6 processes a case, **When** it produces output, **Then** `06_statutes_map.jsonl` contains valid JSONL where each line has: `chunk_id`, `statute_id`, `article_no`, `quoted_text`, `confidence`, `match_type`, `abrogated` fields.
2. **Given** Agent 8 drafts a brief, **When** the brief is produced, **Then** it follows the mandatory 8-section structure defined in SKILL.md (preamble through appendices), with no sections missing.
3. **Given** critical agents (5, 6, 8) execute, **When** they produce output, **Then** the output format matches the few-shot example provided in the prompt.

---

### User Story 3 - Deterministic PHP Validation Between Agents (Priority: P1)

After each agent completes, a PHP-level validator cross-checks the output against upstream data — not relying on LLM self-judgment. For example: every `statute_id` in Agent 6's output must exist in Agent 3's `03_statutes_index.jsonl`. Every `LAW:{ref}` in Agent 8's brief must exist in Agent 6's accepted matches.

**Why this priority**: LLM-based QA (Agent 9) cannot reliably catch hallucinations. Deterministic validation is the only way to guarantee citation accuracy and prevent fabricated references from reaching the final brief.

**Independent Test**: Intentionally provide a case where an agent might hallucinate (limited statute index). Verify that the PHP validator catches invalid citations and triggers a re-run or flags violations.

**Acceptance Scenarios**:

1. **Given** Agent 6 produces `06_statutes_map.jsonl`, **When** the validator runs, **Then** every `statute_id` is verified to exist in `03_statutes_index.jsonl` — any non-existent ID is flagged as a hallucination and removed.
2. **Given** Agent 8 produces `08_final_brief.md`, **When** the validator runs, **Then** every `LAW:{statute_ref}` citation is verified against `06_statutes_map.jsonl` accepted matches.
3. **Given** Agent 6 produces a match with `quoted_text`, **When** the validator runs, **Then** the quoted text is verified to be a substring of the `content` field in the matching `03_statutes_index.jsonl` entry.
4. **Given** validation fails with critical violations, **When** the violation count is reported, **Then** the agent is automatically re-run with error context (up to 3 retries per SKILL.md rules).

---

### User Story 4 - Phase 1 Meaningful Analysis (Priority: P2)

Phase 1 (Agent 0) produces a comprehensive case analysis that identifies all relevant laws from the RAG database, instead of being limited to 150 tokens of output.

**Why this priority**: Phase 1 is the foundation — all downstream agents depend on its analysis. A truncated Phase 1 output cascades into poor quality across the entire pipeline.

**Independent Test**: Submit a multi-document case. Verify that Phase 1 output includes all relevant laws with official names, subject areas, relevance reasons, and key articles.

**Acceptance Scenarios**:

1. **Given** a case with multiple documents, **When** Phase 1 completes, **Then** the output identifies all relevant Saudi laws with structured entries (official name, subject area, relevance reason, abrogation status, key articles).
2. **Given** Phase 1 executes, **When** the LLM generates output, **Then** the max_tokens parameter allows at least 4,096 tokens of output (not 150).

---

### User Story 5 - Temperature and Token Configuration per Agent (Priority: P2)

Each agent has calibrated temperature and max_tokens settings appropriate to its task, configurable via a central configuration file.

**Why this priority**: Miscalibrated settings (e.g., Agent 6 at 0.2 temperature for creative matching, or Agent 11 at 0.4 for rigorous legal argument) degrade output quality in predictable ways.

**Independent Test**: Check that each agent uses its configured temperature and max_tokens, and verify output quality improves with correct calibration.

**Acceptance Scenarios**:

1. **Given** the system configuration, **When** Agent 8 (Legal Drafter) executes, **Then** it uses max_tokens >= 16,384 to accommodate a full legal brief.
2. **Given** the system configuration, **When** Agent 6 (Statute Matcher) executes, **Then** it uses temperature 0.3 (not 0.2) for better matching creativity within bounds.
3. **Given** any agent executes, **When** it reads its configuration, **Then** temperature and max_tokens are sourced from centralized agent-specific overrides, not hardcoded.

---

### User Story 6 - Enhanced RAG Context Without Truncation (Priority: P2)

Agents that need statute data receive relevant subsets of the index rather than a silently truncated full dump. Each agent receives only the statutes relevant to its task, preventing hallucination from invisible data.

**Why this priority**: Silent truncation of `03_statutes_index.jsonl` causes agents to cite statutes outside the visible window, which is a direct source of hallucination.

**Independent Test**: Submit a case with a large statute index. Verify that agents receive relevant statute subsets and no agent cites a statute that was truncated out of its context.

**Acceptance Scenarios**:

1. **Given** `03_statutes_index.jsonl` exceeds the context budget, **When** Agent 6 receives its context, **Then** it receives a relevant filtered subset (based on Agent 5's matching guidelines) rather than a truncated dump.
2. **Given** an agent receives statute context, **When** the context is trimmed, **Then** the agent is explicitly told which statutes are available and that no citations outside this set are permitted.

---

### User Story 7 - Strengthened Gate Validation in Orchestrator (Priority: P3)

The orchestrator's gate validation between phases includes deterministic checks (not just LLM self-assessment) to prevent a phase from advancing when outputs contain critical violations.

**Why this priority**: Currently the gate validator checks surface formatting. Adding deterministic checks at phase boundaries ensures broken output doesn't propagate through the pipeline.

**Independent Test**: Simulate a scenario where an agent produces output with invalid citations. Verify the gate validator blocks phase progression and triggers re-processing.

**Acceptance Scenarios**:

1. **Given** Phase 2 completes, **When** the gate validator checks Agent 6 output, **Then** it deterministically verifies all `statute_id` values exist in the index before allowing Phase 3 to start.
2. **Given** gate validation fails, **When** violations are critical, **Then** the pipeline re-runs the offending agent with error context before proceeding.

---

### Edge Cases

- What happens when the RAG database returns zero statute matches for a case? Agent 6 should produce an empty `06_statutes_map.jsonl` with a note in `06_gaps_and_todo.md`, and Agent 8 should use Islamic legal maxims as Logical Fallback per SKILL.md rules.
- How does the system handle when `03_statutes_index.jsonl` is empty? The pipeline should pause with a clear error indicating no statutes were found in the RAG database.
- What happens when deterministic validation finds violations on the 3rd retry? Per SKILL.md, the pipeline pauses and emits an SSE event with Arabic Retry/Cancel options.
- What happens when an agent's output is not valid JSON/JSONL? The PHP validator catches malformed structured output and triggers a correction attempt with the parse error in the prompt context.
- How does the system handle a case where Agent 5's matching guidelines reference statute IDs not in the index? The deterministic validator flags these as warnings in `05_matching_guidelines.json` validation.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST extract agent-specific prompt sections from SKILL.md rather than sending the entire file to every agent.
- **FR-002**: System MUST include the General Rules section (citations, confidence, anti-hallucination) in every agent's prompt alongside the agent-specific section.
- **FR-003**: System MUST provide structured output templates with required fields for each agent that produces structured data (JSONL, JSON).
- **FR-004**: System MUST include a few-shot example in prompts for critical agents (5, 6, 8) showing the exact expected output format.
- **FR-005**: System MUST run deterministic PHP validation after Agent 6 output, verifying every `statute_id` exists in `03_statutes_index.jsonl`.
- **FR-006**: System MUST run deterministic PHP validation after Agent 8 output, verifying every `LAW:{ref}` citation exists in Agent 6's accepted matches.
- **FR-007**: System MUST verify that `quoted_text` in Agent 6 output is a literal substring of the source entry in `03_statutes_index.jsonl`.
- **FR-008**: System MUST increase Phase 1 max_tokens from 150 to at least 4,096.
- **FR-009**: System MUST store per-agent temperature and max_tokens in a centralized configuration and read from config rather than hardcoding.
- **FR-010**: System MUST provide relevant statute subsets to agents rather than silently truncating the full index.
- **FR-011**: System MUST explicitly tell agents which statutes are available in their context and prohibit citations outside that set.
- **FR-012**: System MUST trigger automatic re-run (up to 3 retries) when deterministic validation finds critical violations, appending error context per SKILL.md self-correction rules.
- **FR-013**: System MUST strengthen gate validation between phases to include deterministic checks on structured outputs.
- **FR-014**: System MUST verify no abrogated articles are cited, by checking the `supersedes` field and `03_conflict_warnings.md`.
- **FR-015**: System MUST remain backward compatible — existing case data and outputs must remain valid.

### Key Entities

- **Agent Prompt**: The focused instruction set sent to each LLM call — composed of General Rules + agent-specific SKILL.md section + context + output template + few-shot example.
- **Deterministic Validator**: Logic that cross-checks agent outputs against upstream data (statute index, accepted matches) without relying on LLM judgment.
- **Agent Config**: Per-agent settings (temperature, max_tokens, retry limit) stored centrally.
- **Statute Subset**: A filtered view of `03_statutes_index.jsonl` relevant to a specific agent's task, avoiding silent truncation.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Every agent prompt contains under 200 lines of instructions (agent-specific section + general rules), not the full 617-line SKILL.md.
- **SC-002**: Phase 1 produces structured output with all relevant laws identified (at least 3 law entries for a typical case), not a truncated fragment.
- **SC-003**: 100% of `statute_id` values in Agent 6 output are verified to exist in `03_statutes_index.jsonl` — zero hallucinated citations pass validation.
- **SC-004**: 100% of `LAW:{ref}` citations in Agent 8's brief are verified against Agent 6's accepted matches — zero unverified citations in the final brief.
- **SC-005**: Pipeline produces well-structured Arabic legal output (all 8 mandatory sections present in the brief) even when using budget-tier LLM models.
- **SC-006**: Deterministic validation catches and blocks any abrogated article citation before it reaches the final brief.
- **SC-007**: The pipeline successfully completes end-to-end and produces a court-ready brief when tested via the UI, with no critical violations remaining.

## Assumptions

- The SKILL.md file at `.agent/skills/legal-counsel/SKILL.md` is the authoritative source for all agent behaviors and will not change during implementation of this feature.
- The RAG law library has been seeded with sufficient Saudi legal content to produce meaningful statute matches.
- Budget LLM models (e.g., Llama 3.x, Mistral, Gemma) can follow structured prompts with templates and examples when the instructions are focused and under 200 lines.
- The existing self-correction mechanism (3 retries with error context) is sufficient when combined with deterministic validation — no additional retry infrastructure is needed.
- Per-agent model-tier gating (forcing specific agents to use premium models) is out of scope for this feature — the focus is on making the pipeline work well with any model the user selects.
