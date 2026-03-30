# Feature Specification: Arabic Output Quality & System Message Alignment

**Feature Branch**: `010-arabic-output-quality`
**Created**: 2026-03-28
**Status**: Draft

---

## Overview

The pipeline currently produces legal briefs that fall short of the desired court-ready quality standard. Three distinct problems exist:

1. **System message mismatch** — The portal's agent system message editor shows a 2-3 sentence persona per agent, while the SKILL.md specification defines a richer multi-step behavioral contract. The displayed messages do not reflect the full prompt that agents receive, and SKILL.md contains English instructions that appear in the Arabic prompts sent to the language model.

2. **RAG context English contamination** — When laws are retrieved from the knowledge base and passed to Agent 3 (Chain of Custody), the formatted output contains English field labels (law_registry_id, LAW_001) embedded inside Arabic law text, polluting the context.

3. **Output depth gap** — The final brief produced by Agent 8 (Legal Drafter) and Agent 12 (Fortification) lacks the appendix sections, per-witness impeachment depth, and precise document-reference format seen in the desired output sample (28-3-update/desired-output-sample.md).

**Success definition**: Running the sample case through the full 13-agent pipeline produces a 13_final_brief_v3.md that matches the desired sample in structure, citation richness, language purity, and courtroom readiness — zero English words, zero emojis, zero raw JSON, pure formal Arabic prose.

---

## User Scenarios & Testing

### User Story 1 — Court-Ready Arabic Brief from Sample Case (Priority: P1)

A lawyer uploads the sample criminal defamation case (sample case/) and runs the full pipeline. The final brief (13_final_brief_v3.md) must be indistinguishable in quality and format from the desired output sample: a complete witness-impeachment brief with full decree citations, Hijri dates in full Arabic words, and appendix sections listing the timeline and cited articles.

**Why this priority**: This is the core value of the system. Every other fix only matters if it results in a better final document. The desired output sample is the definition of correct for this feature.

**Independent Test**: Upload sample case, run full pipeline, read 13_final_brief_v3.md, verify it matches the desired output sample checklist point by point.

**Acceptance Scenarios**:

1. **Given** the sample case is uploaded with all documents and laws, **When** the full 13-agent pipeline completes, **Then** 13_final_brief_v3.md begins with "بسم الله الرحمن الرحيم" as the first line.
2. **Given** the pipeline has completed, **When** the final brief is examined, **Then** every statute citation uses the full decree format with the law name, royal decree number, Hijri year in full words, and the article text in guillemets.
3. **Given** the pipeline has completed, **When** the final brief is examined, **Then** every reference to an official document uses the prose format: "وقد ثبت بالمستند الرسمي رقم [الرقم] المستخرج من [الجهة] بتاريخ [التاريخ الهجري بالكلمات] أن [الواقعة]".
4. **Given** the pipeline has completed, **When** the final brief is examined, **Then** the brief contains appendix sections covering a chronological timeline of events and all cited articles listed with their full text.
5. **Given** the pipeline has completed, **When** the final brief is scanned for non-Arabic content, **Then** zero English words, zero emojis, and zero raw JSON blocks appear anywhere in the document body.
6. **Given** the pipeline has completed, **When** the requests section is examined, **Then** it contains distinct sections for the primary requests, alternative requests, and consequential requests.

---

### User Story 2 — System Messages Match SKILL.md in Portal (Priority: P2)

A lawyer or admin opens the agent system message editor in the portal and sees the full Arabic system prompt for each agent — matching what SKILL.md defines — with no English words, no emojis, and no JSON fragments. They can understand exactly what each agent is instructed to do, and saved edits are actually used when the agent runs.

**Why this priority**: Transparency and operator control. If the portal shows a misleading stub while the agent runs from a different prompt, operators cannot diagnose or improve pipeline behavior.

**Independent Test**: Open the portal agent editor for Agent 8. The displayed system message must contain the full Arabic citation format rules and the mandatory brief structure — not just a 2-3 sentence persona.

**Acceptance Scenarios**:

1. **Given** the portal is open, **When** an operator views the system message for any agent (0-12), **Then** the displayed message contains the agent's complete behavioral instructions in pure Arabic — no English section headers, no English keywords, no emoji markers.
2. **Given** the portal is open, **When** an operator edits and saves a system message override, **Then** the next pipeline run for that agent uses the saved override as its system prompt.
3. **Given** no override has been saved, **When** the default system message is displayed, **Then** it matches the SKILL.md content for that agent — in Arabic.

---

### User Story 3 — RAG Context is Pure Arabic (Priority: P3)

When Agent 3 (Chain of Custody) queries the law knowledge base and incorporates retrieved articles into its working context, the formatted law text contains no English labels, field names, or technical identifiers. The agent sees law articles as pure Arabic text.

**Why this priority**: English labels in the law context are a contamination vector that disrupts the Arabic-only reasoning pattern of downstream agents.

**Independent Test**: Run a case, inspect the context passed to Agent 3, confirm no law_registry_id, LAW_00x, or other English labels appear in the Arabic law text block.

**Acceptance Scenarios**:

1. **Given** a case with laws in the RAG database, **When** Agent 3 builds its law context, **Then** every law article entry is formatted as pure Arabic: law name, article number in Arabic ordinal form, article text — with no English identifiers.
2. **Given** the law context is formatted, **When** it is scanned for English words, **Then** no English words appear in the Arabic prose portions of the context.

---

### User Story 4 — Playwright End-to-End Test with Sample Case (Priority: P4)

A developer runs a Playwright test suite against the running application. The suite creates a new case from sample case/, runs all 13 agents, and asserts the final brief meets the Arabic quality criteria.

**Why this priority**: Prevents regression. Without an automated test, future changes can silently break output quality.

**Independent Test**: Run the Playwright test suite — it passes, confirming the sample case produces a final brief matching the Arabic quality checklist.

**Acceptance Scenarios**:

1. **Given** the application is running, **When** the Playwright test executes, **Then** it successfully creates a new case, uploads all sample case documents and laws, and starts the pipeline without errors.
2. **Given** the pipeline has been started, **When** the test waits for all 13 agents to complete, **Then** the test confirms the final brief exists and contains the required Arabic structural markers.
3. **Given** the final brief exists, **When** the test reads the document, **Then** no English words appear in the body prose of the brief.

---

### Edge Cases

- What happens when a law in RAG has very few articles — will Agent 3 still produce a valid statutes index?
- What if the pipeline runs without any laws in the RAG database — does Agent 3 fail gracefully with an Arabic error message?
- What if a system message override was saved before this fix — will the old override be preserved or reset to the new Arabic default?
- What if a case intake requests a document type not yet modeled in SKILL.md — does the pipeline degrade gracefully without producing English contamination?

---

## Requirements

### Functional Requirements

**System Message Alignment**

- **FR-001**: The portal agent system message editor MUST display the full Arabic behavioral specification for each agent (as defined in SKILL.md), not a short 2-3 sentence persona excerpt.
- **FR-002**: The default system message for every agent MUST be written entirely in Arabic — no English section headers, no English keywords, no emojis, no JSON fragments.
- **FR-003**: Any operator-saved override for an agent system message MUST be used as the actual system prompt when that agent next executes.
- **FR-004**: The SKILL.md agent specifications MUST be expressed in Arabic so that the instructions embedded in LLM prompts are consistent with the Arabic output requirement.

**RAG Context Quality**

- **FR-005**: The law context block passed to Agent 3 MUST format each retrieved article as pure Arabic: law name, article number in Arabic ordinal form, article text — with no English field names or internal identifiers.
- **FR-006**: The law context MUST NOT include any English labels such as law_registry_id, LAW_xxx, file_label, or similar internal keys.

**Output Template Depth**

- **FR-007**: The output template for Agent 8 (Legal Drafter) MUST require a mandatory appendix section containing: a chronological events list and a list of all cited articles with their full text.
- **FR-008**: The output template for Agent 8 MUST specify the exact document-reference prose format for every evidence citation.
- **FR-009**: The output template for Agent 12 (Fortification) MUST preserve and enrich the appendix sections from the Agent 8/9 output.
- **FR-010**: The quality validation step MUST flag as a violation any final brief that does not contain an appendix section.

**Final Brief Purity**

- **FR-011**: The final brief (13_final_brief_v3.md) MUST contain zero English words in its body prose sections.
- **FR-012**: The final brief MUST contain zero emojis anywhere in the document.
- **FR-013**: The final brief MUST contain zero raw JSON fragments or code blocks in its body.
- **FR-014**: All Hijri dates in the final brief MUST be written in full Arabic words, never as numerals.

**Playwright Test**

- **FR-015**: A Playwright test suite MUST exist that creates a case from the sample case/ directory, runs the full 13-agent pipeline, and asserts Arabic quality criteria on the final brief.
- **FR-016**: The test MUST assert: "بسم الله" as first line, ordinal Arabic section headings (from first to sixth ordinal), presence of appendix section, three-tier requests, and zero English words in body prose.

### Key Entities

- **Agent System Message**: The full behavioral specification delivered to the language model. Composed of persona, behavioral rules, and output template — all in Arabic.
- **RAG Law Context**: The formatted block of retrieved law articles passed to Agent 3. Must be pure Arabic: law name, article number in Arabic ordinal form, article text.
- **Final Brief**: The court-ready document produced as 13_final_brief_v3.md. Must meet all Arabic purity and structural requirements.
- **Appendix Section**: Mandatory terminal section of every brief: (1) chronological event timeline and (2) all cited articles with full text.

---

## Success Criteria

### Measurable Outcomes

- **SC-001**: The sample case run through the full pipeline produces a final brief that passes 100% of the structural checklist: opening with "بسم الله", ordinal sections, full decree citations, Hijri dates in words, three-tier requests, appendices with timeline and article list.
- **SC-002**: Zero English words appear in the body prose of the final brief produced from the sample case.
- **SC-003**: Zero emojis appear anywhere in any final brief.
- **SC-004**: The portal system message editor displays the full Arabic behavioral specification (containing citation format rules and output structure) for Agent 8 — not a 2-3 sentence stub.
- **SC-005**: The RAG law context passed to Agent 3 contains zero instances of English labels.
- **SC-006**: The Playwright test suite completes successfully and all quality assertions pass without manual intervention.
- **SC-007**: Agent 8 self-correction loop count is zero for a clean sample case run — the enriched output template produces a valid brief on the first attempt with no correction retries.

---

## Assumptions

- The sample case laws are imported into the RAG database before the Playwright test runs.
- The SKILL.md Arabic conversion preserves the same logical structure as the current English version — same agents, same pipeline logic, same output filenames — only the language of the instructions changes.
- The agent-system-messages.md file at the project root is the authoritative reference for what each agent's Arabic system message should contain.
- Intermediate machine-readable files (.jsonl, .json) may retain English JSON key names — only the final brief and human-readable Markdown outputs must be pure Arabic.
- The desired output sample (28-3-update/desired-output-sample.md) is the ground-truth reference for brief quality.
- Phase 3 is triggered via the existing manual POST endpoint during the Playwright test.
