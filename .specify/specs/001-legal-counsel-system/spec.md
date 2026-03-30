# Feature Specification: Legal-Counsel — AI-Powered Legal Case Management System

**Feature Branch**: `001-legal-counsel-system`
**Created**: 2026-03-19
**Status**: Draft
**Input**: User description: "Build a complete AI-powered legal case management system called Legal-Counsel"

## Overview

Legal-Counsel is an AI-powered legal case management platform that guides a Saudi lawyer from raw case documents all the way to a court-ready legal brief. The system uses a sequential pipeline of 9 specialized AI agents across 3 phases, each agent building on the output of the previous one. All agent activity streams to the UI in real time. The final output is a polished Arabic legal brief with zero programmatic artifacts, exported as a PDF ready for submission on the Najiz platform. All LLM calls go through OpenRouter using the model selected in the user's settings page.

**Law Library**: All Saudi laws are pre-loaded into the system's database as RAG-embedded data (parsed, chunked, and vectorized). Agents retrieve relevant legal articles directly from the RAG database — the lawyer does NOT upload law files manually. The law library is managed separately via the Law Library admin pages and is available to every case automatically.

## Goals

- Allow a lawyer to upload case documents and receive a complete, court-ready legal brief with no manual assembly.
- Surface every agent's work in real time so the lawyer can follow the reasoning as it unfolds.
- Guarantee legal accuracy through strict anti-hallucination rules, dual citations, confidence thresholds, and abrogation checks.
- Produce a final Arabic brief indistinguishable from one drafted by a senior human lawyer — no AI traces, no JSON, no metadata.
- Export the final brief as a properly formatted Arabic RTL PDF in a single click.

## Non-Goals

- User authentication and billing are out of scope.
- Multi-case comparison or precedent search is out of scope.
- Direct Najiz API integration is out of scope (PDF is prepared for manual upload).
- Real-time multi-lawyer collaboration on the same case is out of scope.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Upload Case Documents (Priority: P1)

As a lawyer, I want to upload my case documents (intake form + supporting docs) so the system can analyze them and identify which laws from the RAG database are relevant to my case.

**Why this priority**: This is the entry point for the entire system. Without document upload, nothing else can function.

**Independent Test**: Upload an intake.txt and one or more supporting documents, verify the system produces a case summary and automatically identifies the relevant laws from the RAG database, streamed to the UI in real time.

**Acceptance Scenarios**:

1. **Given** I have case documents ready, **When** I upload intake.txt and one or more supporting files, **Then** the system reads all uploaded files and produces a summary of the case and a list of relevant laws identified from the RAG database by their official Saudi names.
2. **Given** the system is analyzing my documents, **When** output is being generated, **Then** it streams to the UI in real time as it is being generated.
3. **Given** the system identifies relevant laws, **When** it presents the list, **Then** each law is shown with the reason it is relevant and whether it is the currently active version (not abrogated).
4. **Given** Phase 1 completes, **When** the analysis is done, **Then** the system shows the identified laws and a "Start Phase 2" button to proceed with the full pipeline.

---

### User Story 2 — Review Identified Laws from RAG Database (Priority: P1)

As a lawyer, I want to see a clear list of the laws the system identified from its database so I can confirm the system has the right legal context before proceeding.

**Why this priority**: The lawyer needs to verify the system identified the correct laws before the pipeline runs. This is a quality gate.

**Independent Test**: After Phase 1 completes, verify the identified laws list shows official names, reasons, and law subjects from the RAG database — no JSON, no internal IDs.

**Acceptance Scenarios**:

1. **Given** Phase 1 has completed, **When** I view the identified laws list, **Then** it shows the official name of each law, its subject area, and the reason it is relevant to my case.
2. **Given** the list is displayed, **When** I read it, **Then** it is rendered in clean Arabic prose — no JSON, no internal IDs.
3. **Given** a required law is missing from the RAG database, **When** Phase 1 detects it, **Then** the system shows a clear Arabic warning naming the missing law and advising the user to add it via the Law Library before proceeding.

---

### User Story 3 — Start the 9-Agent Pipeline (Priority: P1)

As a lawyer, I want to trigger the 9-agent pipeline after confirming the identified laws so all agents run in sequence and I can watch each one work in real time.

**Why this priority**: This is the core execution mechanism. Without it, no legal analysis happens.

**Independent Test**: After Phase 1 confirms relevant laws exist in the RAG database, click "Start Phase 2", and verify agents run sequentially with real-time streaming into their respective UI panels.

**Acceptance Scenarios**:

1. **Given** Phase 1 has identified relevant laws from the RAG database, **When** I look at the UI, **Then** a "Start Phase 2" button is visible and active.
2. **Given** I click "Start Phase 2", **When** agents begin running, **Then** each agent has its own UI card that activates when that agent starts.
3. **Given** an agent is running, **When** it produces output, **Then** output streams into its panel in real time with a typewriter effect.
4. **Given** an agent completes, **When** its output file is confirmed to exist, **Then** the next agent activates only after the previous agent has completed.
5. **Given** a required input file is missing, **When** an agent tries to start, **Then** the panel shows a clear Arabic error: the name of the missing file and which agent should have produced it.

---

### User Story 4 — Agent 1: Lead Counsel Plan (Priority: P1)

As a lawyer, I want the Lead Counsel agent to read my case and produce a master plan that guides all subsequent agents.

**Why this priority**: This agent sets the strategic direction for the entire pipeline. Every downstream agent depends on its plan.

**Independent Test**: Run Agent 1 with case documents, verify it produces 01_lead_plan.md and 01_acceptance_criteria.json with the required structure, streamed in real time.

**Acceptance Scenarios**:

1. **Given** case documents are uploaded, **When** Agent 1 runs, **Then** it reads intake.txt and all docs/ files, and reads memory/errors_log.md if it exists.
2. **Given** Agent 1 completes, **When** I check the output, **Then** 01_lead_plan.md contains: case summary, scope, acceptance criteria (confidence ≥ 0.70, dual CASE+LAW citation mandatory), list of expected output files per agent, and strategic instructions for Agent 8 (three-tier defense structure).
3. **Given** Agent 1 completes, **When** I check the output, **Then** 01_acceptance_criteria.json contains measurable quality thresholds.
4. **Given** Agent 1 is running, **When** I watch the UI, **Then** output streams in real time and on completion the panel shows a green indicator with token count and duration.

---

### User Story 5 — Agent 2: Evidence Ingestion (Priority: P1)

As a lawyer, I want the Evidence agent to parse and chunk all case documents into a clean structured format for downstream agents.

**Why this priority**: All downstream agents depend on properly chunked evidence. This is the data foundation.

**Independent Test**: Run Agent 2 with docs/, verify 02_chunks.jsonl contains properly sized chunks with metadata, and 02_ingestion_report.md lists all processed files.

**Acceptance Scenarios**:

1. **Given** docs/ contains case files, **When** Agent 2 runs, **Then** it reads all files from docs/ and normalizes formatting without summarizing or altering meaning.
2. **Given** Agent 2 completes, **When** I check 02_chunks.jsonl, **Then** each chunk has: source_path, section_id, start_line, end_line, text (1200–1800 characters, 200-character overlap).
3. **Given** Agent 2 completes, **When** I check 02_ingestion_report.md, **Then** it lists files processed and any read errors, with corrupted files flagged as _needs_review.
4. **Given** any issue occurs during ingestion, **When** Agent 2 detects it, **Then** it logs the issue to memory/errors_log.md.

---

### User Story 6 — Agent 3: Chain of Custody & Statute Indexing (Priority: P1)

As a lawyer, I want the Integrity agent to verify document integrity and retrieve all relevant legal articles from the RAG database — including detecting abrogated statutes before they can be used.

**Why this priority**: Abrogation detection is critical to legal accuracy. Using an abrogated statute in court would be a fatal error.

**Independent Test**: Run Agent 3 with case documents and the RAG database, verify it produces fingerprints, a statute index retrieved from the database, and conflict warnings — especially abrogation detection.

**Acceptance Scenarios**:

1. **Given** case documents exist, **When** Agent 3 runs, **Then** it computes a fingerprint for every case document (first/last 64 chars, line count, char count).
2. **Given** Agent 3 queries the RAG database, **When** it retrieves relevant legal articles, **Then** 03_statutes_index.jsonl contains each entry with: statute_id, title, article_no, content, file_label, local_ref, effective_year, supersedes list — all sourced from the RAG law library.
3. **Given** Agent 3 detects abrogated or conflicting laws in the RAG results, **When** it completes, **Then** 03_conflict_warnings.md lists every detected abrogation or conflict.
4. **Given** Agent 3 completes, **When** I check the output, **Then** 03_chain_of_custody_summary.md exists and any abrogation is logged to memory/errors_log.md as a proactive warning.

---

### User Story 7 — Agent 4: Timeline & Entity Extraction (Priority: P1)

As a lawyer, I want the Timeline agent to convert case facts into a chronological event list fully referenced to the source documents.

**Why this priority**: The timeline anchors every legal argument to verifiable facts. Agents 5–8 all depend on it.

**Independent Test**: Run Agent 4 with 02_chunks.jsonl, verify events are extracted with dates, parties, references, and confidence scores, with duplicates merged.

**Acceptance Scenarios**:

1. **Given** 02_chunks.jsonl exists, **When** Agent 4 runs, **Then** it extracts events with: id, date (ISO if explicit, null if ambiguous), date_raw, place, parties, event description, source_ref list, confidence.
2. **Given** duplicate events exist across chunks, **When** Agent 4 processes them, **Then** they are merged into one entry with multiple source_refs.
3. **Given** Hijri dates appear in the source, **When** Agent 4 processes them, **Then** Hijri dates are kept as-is unless explicitly converted in the source.
4. **Given** Agent 4 completes, **When** I check the output, **Then** 04_timeline.json, 04_timeline.md, and 04_entities_index.md all exist.

---

### User Story 8 — Agent 5: Legal Issues & Adversary Analysis (Priority: P1)

As a lawyer, I want the Law Lead agent to map every case fact to a legal issue and analyze the opponent's expected evidence using the three-step challenge methodology.

**Why this priority**: This agent transforms raw facts into actionable legal issues and identifies the opponent's likely strategy — essential for building a defense.

**Independent Test**: Run Agent 5 with upstream outputs, verify each event maps to a classified legal issue and adversary analysis uses the three-step challenge format.

**Acceptance Scenarios**:

1. **Given** upstream outputs exist, **When** Agent 5 runs, **Then** it reads 02_chunks.jsonl, 04_timeline.json, 03_statutes_index.jsonl, and 03_conflict_warnings.md, checking conflict warnings before using any article.
2. **Given** Agent 5 processes events, **When** it classifies them, **Then** every event becomes a legal issue classified as strong, medium, or weak.
3. **Given** Agent 5 completes, **When** I check the output, **Then** it produces 05_issues_to_statutes.md, 05_procedural_notes.md (jurisdiction, standing, limitation, res judicata), 05_adversary_evidence_analysis.md (three-step challenge: Fact → Legal Flaw → Effect), and 05_matching_guidelines.json.
4. **Given** the opponent has contradictory statements, **When** Agent 5 analyzes them, **Then** it flags the strongest contradiction as the opening defense challenge.

---

### User Story 9 — Agent 6: Statute Matching (Priority: P1)

As a lawyer, I want the Statute Matcher to pair every case fact with the most relevant legal article — with a literal quote and confidence score — and never use an abrogated article.

**Why this priority**: This is the legal backbone. Every argument in the final brief depends on accurate, verified statute matches.

**Independent Test**: Run Agent 6 with upstream outputs, verify every match includes a literal quote, confidence ≥ 0.70, and verified non-abrogation status.

**Acceptance Scenarios**:

1. **Given** upstream outputs exist, **When** Agent 6 runs, **Then** it reads 02_chunks.jsonl, 05_matching_guidelines.json, 03_statutes_index.jsonl, and 03_conflict_warnings.md, verifying every candidate article against conflict warnings before accepting.
2. **Given** Agent 6 matches articles, **When** confidence is ≥ 0.70, **Then** it accepts up to 5 articles per chunk, each with: chunk_ref, statute_ref, quoted_text (literal from index), rationale, confidence, status, supersession_check: "verified_not_abrogated".
3. **Given** a match has confidence below 0.70, **When** Agent 6 processes it, **Then** the item goes to 06_gaps_and_todo.md and triggers Logical Fallback (established Islamic legal maxims, never invented statutes).
4. **Given** an abrogated article is detected, **When** Agent 6 encounters it, **Then** the attempt is logged immediately to memory/errors_log.md and article numbers are double-verified against 03_statutes_index.jsonl.
5. **Given** Agent 6 completes, **When** I check the output, **Then** 06_statutes_map.jsonl, 06_accepted_matches.md, 06_rejections.md, and 06_gaps_and_todo.md all exist.

---

### User Story 10 — Agent 7: Strategy & Risk Assessment (Priority: P1)

As a lawyer, I want the Strategy agent to evaluate the risk of every claim and build a three-tier defense structure with primary, alternative, and consequential requests.

**Why this priority**: The defense strategy determines the structure of the final brief. Agent 8 cannot draft without it.

**Independent Test**: Run Agent 7 with upstream outputs, verify the risk matrix, three-tier defense layers, and burden-of-proof analysis are all produced.

**Acceptance Scenarios**:

1. **Given** upstream outputs exist, **When** Agent 7 runs, **Then** it reads 06_statutes_map.jsonl, 04_timeline.json, 05_procedural_notes.md, and 05_adversary_evidence_analysis.md.
2. **Given** Agent 7 assesses claims, **When** it produces 07_risk_matrix.md, **Then** each entry has: claim_id, law references, penalty range (literal quote if present, null if not), aggravating/mitigating factors, gaps, aggregate confidence.
3. **Given** a claim has aggregate confidence below 0.70, **When** Agent 7 flags it, **Then** the claim is sent back to Agent 6 for re-matching.
4. **Given** Agent 7 completes, **When** I check the output, **Then** 07_defense_layers.md contains three defense lines (Primary, Alternative, Consequential), and 07_charges_scenarios.json and 07_mitigation_opportunities.md exist.
5. **Given** Agent 7 analyzes burden of proof, **When** it completes, **Then** it instructs Agent 8 on burden-shifting strategy and defines the three-part closing requests structure.

---

### User Story 11 — Agent 8: Drafting the Legal Brief (Priority: P1)

As a lawyer, I want the Drafting agent to produce a complete eloquent Arabic legal brief in the voice of a senior Saudi lawyer — structured as a real court document, not a list of bullet points.

**Why this priority**: This is the primary deliverable of the system — the court-ready brief.

**Independent Test**: Run Agent 8 with all upstream outputs, verify the brief follows the mandatory structure, uses legal syllogisms, contains dual citations, and reads like a human-drafted court document.

**Acceptance Scenarios**:

1. **Given** all upstream output files exist, **When** Agent 8 runs, **Then** it reads all upstream files and memory/errors_log.md.
2. **Given** Agent 8 produces the brief, **When** I read 08_final_brief.md, **Then** it opens with mandatory Islamic preamble "بسم الله الرحمن الرحيم" followed by address to the judges.
3. **Given** Agent 8 structures the brief, **When** I check the sections, **Then** the mandatory structure is: Introduction & Framing → Case Facts → Legal & Sharia Framework → Defense Arguments → Requests (Primary / Alternative / Consequential) → Closing & Prayer → Appendices.
4. **Given** Agent 8 writes analytical paragraphs, **When** a paragraph lacks both CASE:{...} and LAW:{...} references, **Then** it is marked "⚠️ غير مُسنَّدة" and excluded.
5. **Given** Agent 8 writes defense arguments, **When** I read them, **Then** each uses a legal syllogism: Major Premise (legal rule) → Minor Premise (applying the facts) → Inevitable Conclusion (legal effect).
6. **Given** Agent 8 writes evidence challenges, **When** I read them, **Then** they use the prescribed formula: "وحيث إن ما قدّمه المدعي من [نوع الدليل] يعتريه [نوع العيب]، وذلك لما نصت عليه صراحةً المادة..."
7. **Given** the brief is complete, **When** I check its formatting, **Then** there are no markdown tables anywhere, and the language is measured, firm, and objective.
8. **Given** Agent 8 completes, **When** I check the output, **Then** 08_defense_arguments.md, 08_final_brief.md, and 08_arguments_index.json all exist.

---

### User Story 12 — Agent 9: Quality Assurance & AI Erasure (Priority: P1)

As a lawyer, I want the QA agent to catch every error, validate every citation, and produce a final brief that looks and reads like it was typed by a human lawyer in a real law office.

**Why this priority**: This is the final gate before the brief reaches the lawyer. It ensures legal accuracy and removes all AI traces.

**Independent Test**: Run Agent 9 with the draft brief, verify all citations are validated, internal references converted to Arabic prose, AI traces removed, and 09_final_brief_v2.md is only produced if no critical violations remain.

**Acceptance Scenarios**:

1. **Given** 08_final_brief.md exists, **When** Agent 9 runs, **Then** it executes the full QA checklist: dual citations present, LAW quotes match 06_statutes_map.jsonl, no date contradictions, confidence ≥ 0.70 for all articles, no abrogated articles, article numbers match 03_statutes_index.jsonl, requests split into three parts, preamble present.
2. **Given** Agent 9 processes internal references, **When** it converts them, **Then** [LAW:{IS-M11}] becomes "بصريح المادة (الحادية عشرة) من نظام الإثبات التي نصت على..." and [CASE:{C004}] becomes "وهو ما يثبت من خلال ما تضمّنته مستندات القضية من..." — confidence scores, agent headers, and metadata are deleted entirely.
3. **Given** paragraphs marked "⚠️ غير مُسنَّدة" exist, **When** Agent 9 processes them, **Then** they are removed from the final file.
4. **Given** 09_violations.md contains unresolved critical errors, **When** Agent 9 finishes, **Then** 09_final_brief_v2.md is NOT produced.
5. **Given** Agent 9 completes successfully, **When** I check the output, **Then** 09_QA_summary.md, 09_violations.md, 09_fixes_applied.json, 09_todo_back_to_agents.md, and 09_final_brief_v2.md all exist.

---

### User Story 13 — Trigger Phase 3: Judicial Arbitration (Priority: P2)

As a lawyer, I want to optionally run the judicial arbitration phase to stress-test the brief before submitting it to court.

**Why this priority**: Optional but high-value. The brief is already complete after Phase 2; Phase 3 is an enhancement.

**Independent Test**: After 09_final_brief_v2.md is produced, verify the "Start Phase 3" button appears and Phase 3 does not start automatically.

**Acceptance Scenarios**:

1. **Given** 09_final_brief_v2.md has been produced, **When** Phase 2 completes, **Then** Phase 3 is available and a "Start Phase 3 — Judicial Arbitration" button appears.
2. **Given** Phase 2 has not completed, **When** I look for Phase 3, **Then** it is not available.
3. **Given** Phase 3 is available, **When** I do nothing, **Then** Phase 3 never starts automatically — explicit user action is required.

---

### User Story 14 — The Judge Agent (Priority: P2)

As a lawyer, I want a simulated experienced Saudi judge to review my brief and identify every weakness that could cause it to be rejected.

**Why this priority**: Provides critical external perspective before court submission.

**Independent Test**: Run the Judge Agent with 09_final_brief_v2.md, verify 10_judge_notes.md covers formal requirements, substantive critique, procedural objections, likely questions, and preliminary leaning.

**Acceptance Scenarios**:

1. **Given** 09_final_brief_v2.md exists, **When** the Judge Agent runs, **Then** it reads all output files including the final brief.
2. **Given** the Judge Agent completes, **When** I check 10_judge_notes.md, **Then** it covers: formal requirements check, substantive argument critique, procedural objections, questions likely to be asked in session, fatal weaknesses, and preliminary leaning (accept/reject with confidence).

---

### User Story 15 — Devil's Advocate Agent (Priority: P2)

As a lawyer, I want a simulated opposing counsel to attack every argument in my brief.

**Why this priority**: Identifies vulnerabilities the opponent will exploit, allowing pre-emptive fortification.

**Independent Test**: Run the Devil's Advocate Agent with the final brief, verify it produces counter-evidence, stronger opponent articles, internal contradictions, and success probability.

**Acceptance Scenarios**:

1. **Given** 09_final_brief_v2.md exists, **When** the Devil's Advocate Agent runs, **Then** it reads all output files including the final brief.
2. **Given** the Devil's Advocate Agent completes, **When** I check 11_devils_advocate_notes.md, **Then** it covers: counter-evidence for each cited proof, stronger articles the opponent could cite, internal contradictions, procedural defenses available to the opponent, likely opponent evidence, and overall success probability assessment.

---

### User Story 16 — Final Fortification Agent (Priority: P2)

As a lawyer, I want the system to absorb the judge's notes and devil's advocate attacks and produce a hardened final brief that preemptively addresses every objection.

**Why this priority**: Transforms Phase 3 feedback into a demonstrably stronger brief.

**Independent Test**: Run the Fortification Agent with judge notes, devil's advocate notes, and the brief, verify it produces a hardened 13_final_brief_v3.md with AI erasure applied.

**Acceptance Scenarios**:

1. **Given** 09_final_brief_v2.md, 10_judge_notes.md, and 11_devils_advocate_notes.md exist, **When** the Fortification Agent runs, **Then** it reads all upstream files and classifies each observation as critical, important, or routine.
2. **Given** a critical observation is found, **When** the agent processes it, **Then** it returns to the responsible agent (3, 6, or 8) and requests correction.
3. **Given** the agent builds the fortified brief, **When** it writes defense paragraphs, **Then** it embeds Legal Dilemma (Catch-22) paragraphs that trap the opponent in logical contradictions.
4. **Given** the agent produces 13_final_brief_v3.md, **When** it applies formatting, **Then** the same AI-erasure rules as Agent 9 are applied.
5. **Given** the agent completes, **When** I check the output, **Then** 12_fortification_plan.md, 12_responses_to_judge.md, 12_counter_arguments.md, and 13_final_brief_v3.md all exist, and all newly discovered errors are logged to memory/errors_log.md with lessons learned.

---

### User Story 17 — Export Final Brief as PDF (Priority: P1)

As a lawyer, I want to export the final brief as a clean Arabic PDF with a single button click.

**Why this priority**: The PDF is the final deliverable. Without it, the lawyer cannot submit to court.

**Independent Test**: After a final brief is produced, click the export button and verify the PDF uses RTL Arabic layout, has no AI traces, and downloads with the correct filename.

**Acceptance Scenarios**:

1. **Given** 09_final_brief_v2.md or 13_final_brief_v3.md is produced, **When** I look at the UI, **Then** an export button is visible.
2. **Given** I click the export button, **When** the PDF is generated, **Then** it uses right-to-left Arabic layout with proper court-submission margins.
3. **Given** the PDF is generated, **When** I inspect it, **Then** there are zero programmatic artifacts, no metadata, and no AI traces.
4. **Given** I export the PDF, **When** it downloads, **Then** the filename defaults to case name and date (e.g., قضية-الأحمدي-2026-03-19.pdf).
5. **Given** I want to export, **When** I click the button, **Then** the export does not require a page reload.

---

### User Story 18 — Persistent Error Memory (Priority: P1)

As a lawyer, I want the system to remember every error it corrects across all agents so the same mistake is never repeated within this case.

**Why this priority**: Prevents cascading errors and ensures the pipeline learns from its own corrections within a case run.

**Independent Test**: Trigger an error in any agent, verify memory/errors_log.md is created with the proper entry structure, and verify the next agent reads it before starting.

**Acceptance Scenarios**:

1. **Given** any agent detects an error, **When** it is the first error, **Then** memory/errors_log.md is created automatically.
2. **Given** memory/errors_log.md exists, **When** any agent starts, **Then** it reads this file before beginning its work.
3. **Given** an error is logged, **When** I check the entry, **Then** it contains: discovery date, discovering agent, error type, responsible agent, error details, impact, applied fix, and lesson learned.

---

### User Story 19 — Self-Correcting Loop (Priority: P1)

As a lawyer, I want the system to automatically detect and fix errors without stopping and asking me every time.

**Why this priority**: Minimizes pipeline interruptions and keeps the workflow flowing without constant human intervention for recoverable errors.

**Independent Test**: Introduce a known error condition (e.g., confidence below 0.70), verify the agent self-corrects, logs the correction, and the UI shows a correction event.

**Acceptance Scenarios**:

1. **Given** any agent detects confidence below 0.70, quote mismatch, date conflict, or abrogated article, **When** it processes the error, **Then** it logs and self-corrects before proceeding.
2. **Given** a self-correction occurs, **When** I view the agent's panel, **Then** the UI shows an agent.correction event as a visible notification.
3. **Given** an error cannot be self-corrected after 3 attempts, **When** the pipeline pauses, **Then** it shows a clear Arabic message with options: Retry or Cancel.

---

### Edge Cases

- What happens when the lawyer uploads zero documents? The system shows a clear Arabic error asking the user to upload at least one document.
- What happens when a required law is missing from the RAG database? Phase 1 warns the user with the law's official name and advises them to add it via the Law Library before proceeding.
- What happens when the RAG database returns no relevant articles for a chunk? The system applies Logical Fallback (established Islamic legal maxims) and logs to 06_gaps_and_todo.md.
- What happens when all articles for a chunk have confidence below 0.70? The system applies Logical Fallback (established Islamic legal maxims) and logs to 06_gaps_and_todo.md.
- What happens when Agent 9 finds critical violations that prevent brief production? 09_final_brief_v2.md is NOT produced. The system shows which violations must be resolved and which agents need to re-run.
- What happens when the network drops during agent streaming? The SSE/WebSocket connection automatically reconnects with exponential back-off and resumes streaming from the last received position.
- What happens when the OpenRouter API or network fails mid-pipeline? The pipeline resumes from the last completed agent — agents whose output files already exist are skipped, and execution continues from the first agent missing its output.
- What happens when the same case is re-processed? Previous agent outputs are overwritten. The existing memory/errors_log.md is read to avoid previously corrected mistakes.
- What happens when Phase 3 agents find critical issues? The Fortification Agent returns to the responsible agent (3, 6, or 8) for correction before producing the hardened brief.
- What happens when the lawyer wants to re-run a specific agent? The system re-runs from that agent forward (e.g., Agent 6 triggers 6→7→8→9), overwriting downstream outputs to maintain consistency.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST accept document uploads (intake.txt + supporting docs) and process them through Phase 1 analysis to identify relevant laws from the RAG database.
- **FR-002**: System MUST display the identified laws list in clean Arabic prose with official Saudi law names, subject areas, and reasons for relevance — all sourced from the RAG law library.
- **FR-003**: System MUST warn the user if a required law is not found in the RAG database and advise them to add it via the Law Library before proceeding.
- **FR-004**: System MUST execute 9 agents in strict sequential order, with each agent activating only after the previous agent's output file is confirmed.
- **FR-005**: System MUST stream all agent output to the UI in real time with a typewriter effect.
- **FR-006**: Agent 1 (Lead Counsel) MUST produce 01_lead_plan.md and 01_acceptance_criteria.json with case summary, scope, acceptance criteria (confidence ≥ 0.70), and strategic instructions.
- **FR-007**: Agent 2 (Evidence) MUST produce 02_chunks.jsonl with chunks of 1200–1800 characters and 200-character overlap, plus 02_ingestion_report.md.
- **FR-008**: Agent 3 (Integrity) MUST compute case document fingerprints, retrieve relevant legal articles from the RAG database, produce 03_statutes_index.jsonl, 03_conflict_warnings.md, and 03_chain_of_custody_summary.md, and detect abrogated statutes.
- **FR-009**: Agent 4 (Timeline) MUST extract chronological events from chunks with source references, merge duplicates, preserve Hijri dates, and produce 04_timeline.json, 04_timeline.md, and 04_entities_index.md.
- **FR-010**: Agent 5 (Law Lead) MUST classify events into legal issues (strong/medium/weak), perform three-step adversary analysis, check conflict warnings, and produce 05_issues_to_statutes.md, 05_procedural_notes.md, 05_adversary_evidence_analysis.md, and 05_matching_guidelines.json.
- **FR-011**: Agent 6 (Statute Matcher) MUST match articles with confidence ≥ 0.70, verify non-abrogation, include literal quotes, double-verify article numbers, and produce 06_statutes_map.jsonl, 06_accepted_matches.md, 06_rejections.md, and 06_gaps_and_todo.md.
- **FR-012**: Agent 7 (Strategy) MUST produce 07_risk_matrix.md with claim-level risk assessment, 07_defense_layers.md with three-tier defense (Primary/Alternative/Consequential), 07_charges_scenarios.json, and 07_mitigation_opportunities.md.
- **FR-013**: Agent 8 (Drafter) MUST produce an Arabic legal brief with: Islamic preamble, mandatory court document structure, legal syllogism format, dual CASE+LAW citations, evidence challenge formula, no markdown tables, and measured objective language.
- **FR-014**: Agent 9 (QA) MUST validate all citations, convert internal references to Arabic prose, remove AI traces, exclude unsupported paragraphs, and produce 09_final_brief_v2.md only if no critical violations remain.
- **FR-015**: Phase 3 (optional) MUST only be available after 09_final_brief_v2.md is produced and MUST require explicit user action to start.
- **FR-016**: The Judge Agent MUST produce 10_judge_notes.md with formal, substantive, and procedural review including preliminary leaning.
- **FR-017**: The Devil's Advocate Agent MUST produce 11_devils_advocate_notes.md with counter-evidence, opponent strategies, contradictions, and success probability.
- **FR-018**: The Fortification Agent MUST classify observations, correct critical issues, embed legal dilemma paragraphs, apply AI erasure, and produce 13_final_brief_v3.md.
- **FR-019**: System MUST export the final brief as a right-to-left Arabic PDF with court-submission margins, no AI traces, and automatic filename generation.
- **FR-020**: System MUST maintain a persistent memory/errors_log.md that every agent reads before starting, with structured entries for each error.
- **FR-021**: System MUST self-correct recoverable errors (confidence < 0.70, quote mismatches, date conflicts, abrogated articles) without stopping, logging corrections and showing UI notifications.
- **FR-022**: System MUST pause and alert the user only when self-correction fails after 3 attempts, with clear Arabic options: Retry or Cancel.
- **FR-023**: All UI output MUST be human-readable Arabic prose — never raw JSON, internal IDs, error stack traces, or machine-formatted data.
- **FR-024**: On infrastructure failure (API outage, network error), the pipeline MUST be resumable from the last completed agent — agents whose output files already exist are skipped, and execution restarts from the first agent missing its output.
- **FR-025**: The system MUST allow re-running the pipeline from any chosen agent forward — selecting an agent re-runs it and all downstream agents sequentially (e.g., selecting Agent 6 re-runs 6→7→8→9), overwriting their previous outputs.

### Key Entities

- **Case**: The legal case being analyzed. Contains intake documents, supporting documents, law files, and all agent outputs. Has a status (Phase 1 / Phase 2 / Phase 3 / Complete).
- **Agent**: One of the 9+ specialized AI processors in the pipeline. Each has a defined role, input dependencies, and output files. Has a status (pending / running / completed / error).
- **Agent Output**: A file produced by an agent (e.g., 01_lead_plan.md, 06_statutes_map.jsonl). Serves as input for downstream agents. Confirmed to exist before next agent starts.
- **Law Library Entry**: A Saudi law stored in the RAG database. Parsed into articles, chunked, and vectorized for semantic retrieval. Each entry has a subject area classification and is managed via the Law Library admin pages.
- **Statute**: A legal article retrieved from the RAG law library. Has: statute_id, title, article_no, content, effective_year, supersedes list, abrogation status, and law subject area.
- **Chunk**: A 1200–1800 character segment of a case document with metadata (source_path, section_id, line range). The atomic unit of evidence for downstream agents.
- **Timeline Event**: A chronological fact extracted from chunks with date, place, parties, description, source references, and confidence score.
- **Legal Issue**: A case fact mapped to a legal classification (strong/medium/weak) with associated statute matches.
- **Statute Match**: A pairing of a chunk to a legal article with literal quote, confidence score (≥ 0.70), rationale, and verified non-abrogation status.
- **Error Log Entry**: A record in memory/errors_log.md with: discovery date, discovering agent, error type, responsible agent, details, impact, fix, and lesson learned.
- **Legal Brief**: The final court-ready document in Arabic, structured per Saudi court conventions, with all AI traces removed.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A lawyer can go from uploading case documents to receiving a complete court-ready brief without leaving the application or performing manual assembly.
- **SC-002**: Every agent's output appears in the UI within 2 seconds of generation, streaming in real time with no page refresh required.
- **SC-003**: 100% of statute citations in the final brief are verified against the provided law files with confidence ≥ 0.70.
- **SC-004**: Zero abrogated articles appear in any final brief (09_final_brief_v2.md or 13_final_brief_v3.md).
- **SC-005**: The final brief contains zero programmatic artifacts — no JSON, no internal IDs, no confidence scores, no agent headers, no metadata.
- **SC-006**: Every analytical paragraph in the final brief contains both a case-fact reference and a law-article reference (dual citation).
- **SC-007**: The PDF export completes in a single click with correct RTL Arabic layout and court-appropriate margins.
- **SC-008**: 90% of recoverable errors (confidence drops, quote mismatches, date conflicts) are self-corrected without interrupting the user.
- **SC-009**: The system completes the full 9-agent pipeline (Phase 2) without requiring more than one user interaction after clicking "Start Phase 2".
- **SC-010**: The final brief reads as if written by a senior Saudi lawyer — measured, firm, objective language with proper Islamic preamble and court document structure.

## Clarifications

### Session 2026-03-19

- Q: When infrastructure fails mid-pipeline (e.g., OpenRouter API goes down), can the pipeline resume from the last completed agent? → A: Yes — resume from last completed agent, skipping agents whose output files already exist.
- Q: When re-processing a case, what happens to previous agent outputs? → A: Overwrite all previous outputs — each run replaces the prior results.
- Q: What is the maximum acceptable pipeline completion time? → A: No fixed time limit. Each agent takes as long as needed to produce the best output. Real-time streaming with smooth animations keeps the user engaged throughout execution.
- Q: Are there limits on document uploads (file count or total size)? → A: No limits — accept any number and size of documents.
- Q: Can a lawyer re-run a specific agent without restarting the full pipeline? → A: Yes — re-run from a chosen agent forward (e.g., selecting Agent 6 re-runs 6→7→8→9).

## Assumptions

- The lawyer provides case documents in readable text format (.txt, .md, or .pdf parsed to text).
- All Saudi laws are pre-loaded into the system's RAG database (parsed, chunked, embedded as vectors). The lawyer does NOT upload law files per case — agents query the RAG law library directly.
- The law library is managed separately via the Law Library admin pages (`law-library/create`, `law-library/edit`). Each law entry includes its subject area for classification.
- All LLM calls go through OpenRouter using the model configured in the settings page.
- The system operates on one case at a time per session.
- There is no fixed time limit for the pipeline. Each agent runs as long as needed for optimal output quality. Real-time streaming with smooth animations keeps the user engaged throughout.
- No limits on document uploads — the system accepts any number and size of case documents.
- Arabic language proficiency is assumed for all output — the system does not translate.
- The lawyer is familiar with Saudi court procedures and can evaluate the brief's legal quality.
- Network connectivity is available for LLM API calls during agent execution.
