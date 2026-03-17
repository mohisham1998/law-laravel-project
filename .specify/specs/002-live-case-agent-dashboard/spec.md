# Feature Specification: Live Case Agent Dashboard with Real-Time Visualization

**Feature Branch**: `002-live-case-agent-dashboard`  
**Created**: 2026-03-16  
**Status**: Draft  
**Input**: Live animated case generation dashboard with real-time agent visualization, PDF export, case metrics, and SKILL.md alignment.

---

## Clarifications

### Session 2026-03-16

- Q: Real-time transport mechanism (WebSocket vs SSE vs Polling)? → A: Server-Sent Events (SSE) - simpler, sufficient for one-way streaming
- Q: Agent permanent failure handling (after 3 retries)? → A: Pause and allow retry - case stays paused, user can retry failed agent or abort
- Q: Output streaming granularity? → A: Character-by-character (typewriter effect) for engaging visual feedback
- Q: Concurrent case processing limit? → A: Limited to 3 concurrent cases per user
- Q: UI styling approach? → A: MUST follow existing dashboard styling (colors, typography, components, RTL layout) for visual consistency
- Q: Step visualization clarity? → A: Clear step indicators showing current position, completed steps, and agent-to-agent output handoff visualization
- Q: Implementation validation? → A: Full end-to-end cycle test required after implementation to confirm all agents execute correctly

---

## Executive Summary

This feature creates a real-time animated dashboard that visualizes the 3-phase case generation process as it happens. When a user submits a case, the dashboard displays each of the 9 agents (plus Phase 1 and Phase 3 agents) with their current status, animated output streaming, and progress indicators. The system uses the model selected in the user's settings, ensures agent outputs chain correctly (each agent's output becomes the next agent's input), and produces a final PDF-exportable legal brief with case insights and metrics.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - View Live Agent Processing During Case Generation (Priority: P1)

**Description**: As a legal professional, after submitting a case, I need to see each agent's work in real-time with animated text output, so I understand what the AI is doing and can trust the generated content.

**Why this priority**: This is the core value proposition—transparency into the AI processing. Without real-time visibility, users have no insight into what's happening during the 15-30 minute processing time.

**Independent Test**: Can be tested by creating a case and observing that the dashboard shows each agent activating in sequence, with streaming text output appearing character-by-character or line-by-line, and status transitions (pending → processing → completed).

**Acceptance Scenarios**:

1. **Given** I have submitted a new case, **When** Phase 1 starts processing, **Then** the dashboard shows "المرحلة الأولى: تحليل القضية" with a pulsing indicator and streaming output text
2. **Given** Phase 1 is processing, **When** the analysis agent writes to its output, **Then** I see the text appear character-by-character (typewriter effect)
3. **Given** Phase 1 completes, **When** the system identifies required laws, **Then** I see a list of required laws with visual confirmation that Phase 1 is complete
4. **Given** Phase 2 starts, **When** Agent 1 (Lead Counsel) begins, **Then** the Agent Timeline shows Agent 1 highlighted with "جارٍ" status and animated output
5. **Given** Agent 1 is processing, **When** I view the dashboard, **Then** I see the agent's output text streaming in a dedicated panel with syntax highlighting for legal references
6. **Given** Agent 1 completes, **When** Agent 2 starts, **Then** Agent 1 shows "مكتمل" with a checkmark and duration, Agent 2 becomes highlighted with streaming output
7. **Given** any agent encounters an error or low confidence, **When** the self-correction loop activates, **Then** I see a visual indicator showing the correction in progress with details
8. **Given** all 9 Phase 2 agents complete, **When** Phase 2 finishes, **Then** all agents show completed status with individual metrics (duration, tokens)
9. **Given** Agent 3 completes, **When** I view its card, **Then** I see: output files produced (e.g., "03_statutes_index.jsonl"), a brief summary, and an arrow/link showing this feeds into Agent 4
10. **Given** I want to understand the flow, **When** I open "سلسلة المخرجات" panel, **Then** I see a visual diagram: Agent 1 → (01_lead_plan.md) → Agent 2 → (02_chunks.jsonl) → ... showing the complete chain

---

### User Story 2 - View All Agents with Status Overview (Priority: P1)

**Description**: As a legal professional, I need to see all agents listed with their current status at a glance, so I can track overall progress and identify any bottlenecks.

**Why this priority**: Users need situational awareness of the entire process, not just the currently active agent. This enables progress tracking and builds confidence.

**Independent Test**: Can be tested by viewing the dashboard during processing and confirming all agents are visible with appropriate status indicators matching their actual state.

**Acceptance Scenarios**:

1. **Given** I am viewing a case in processing, **When** the dashboard loads, **Then** I see all agents listed: Phase 1 Agent, 9 Phase 2 Agents (Lead Counsel through Final Brief), and Phase 3 Agents (Judge + Devil's Advocate)
2. **Given** agents are listed, **When** I view an agent row, **Then** I see: agent number, Arabic name, status icon, duration (if completed), and expand/collapse control for output
3. **Given** Agent 3 is currently processing, **When** I view the agent list, **Then** Agents 1-2 show "مكتمل" (green), Agent 3 shows "جارٍ" (pulsing amber), Agents 4-9 show "في الانتظار" (gray)
4. **Given** the case is in Phase 2, **When** I view Phase 3 agents, **Then** they show "مقفل" (locked) indicating they're not yet available
5. **Given** multiple agents have completed, **When** I click on a completed agent, **Then** I can expand to see its full output with scroll

---

### User Story 3 - Generate and Export Final PDF Document (Priority: P1)

**Description**: As a legal professional, after all phases complete, I need to download the final legal brief as a professionally formatted PDF in Arabic RTL, ready for court submission.

**Why this priority**: The PDF is the tangible deliverable—the entire system exists to produce this document. Without export, the system provides no usable output.

**Independent Test**: Can be tested by completing a case through all phases and clicking the PDF export button, then verifying the downloaded PDF is properly formatted, contains all sections, and is Arabic RTL.

**Acceptance Scenarios**:

1. **Given** all phases are complete, **When** I view the case dashboard, **Then** I see a prominent "تصدير PDF" button enabled
2. **Given** I click "تصدير PDF", **When** the PDF generates, **Then** I see a loading indicator and the PDF downloads automatically
3. **Given** the PDF is generated, **When** I open it, **Then** it displays in Arabic RTL with proper fonts (Cairo or similar)
4. **Given** the PDF content, **When** I review it, **Then** it contains: case header, facts section, legal analysis, statute citations, defense arguments, and requests to the court
5. **Given** the PDF is generated, **When** I check the footer, **Then** it shows generation date and case reference number
6. **Given** the case status is not "completed", **When** I view the dashboard, **Then** the "تصدير PDF" button is disabled with tooltip explaining why

---

### User Story 4 - View Case Insights and Metrics (Priority: P2)

**Description**: As a legal professional, after case generation completes, I need to see insights and metrics about the case analysis, so I can understand the AI's confidence levels and processing statistics.

**Why this priority**: Metrics build trust by showing the work done. They help users understand which parts of the analysis are strong vs. need review.

**Independent Test**: Can be tested by completing a case and viewing the metrics panel, verifying it shows accurate statistics that match the actual agent outputs.

**Acceptance Scenarios**:

1. **Given** all phases complete, **When** I view the dashboard, **Then** I see a "رؤى القضية" (Case Insights) panel
2. **Given** the insights panel, **When** I view statistics, **Then** I see: total processing time, number of statutes matched, average confidence score, number of corrections made
3. **Given** the insights panel, **When** I view the confidence breakdown, **Then** I see a visual indicator (e.g., gauge or bar) showing overall confidence level
4. **Given** low-confidence matches occurred, **When** I view insights, **Then** I see a list of items flagged for manual review with their confidence scores
5. **Given** the self-correction loop ran, **When** I view insights, **Then** I see how many errors were caught and corrected during processing

---

### User Story 5 - Use Settings Model for Processing (Priority: P2)

**Description**: As a legal professional, I want the system to use the AI model I selected in my settings for case processing, so I have control over the processing speed/quality tradeoff.

**Why this priority**: Different models have different cost/quality tradeoffs. Users should be able to choose based on their needs.

**Independent Test**: Can be tested by changing the model in settings, creating a case, and verifying the agent executions use the selected model (visible in logs or metrics).

**Acceptance Scenarios**:

1. **Given** I have selected "claude-3.5-sonnet" in settings, **When** I create a new case, **Then** all agents use claude-3.5-sonnet for processing
2. **Given** I change my model setting to a different model, **When** I create another case, **Then** the new case uses the new model
3. **Given** a case is processing, **When** I view agent details, **Then** I can see which model is being used for this case
4. **Given** a case was created with a specific model, **When** I change my settings, **Then** the existing case continues with its original model (no mid-processing model switch)

---

### User Story 6 - SKILL.md Integration with Dashboard Flow (Priority: P2)

**Description**: As a system administrator, I need the SKILL.md file to be properly integrated so the dashboard accurately reflects the agent workflow defined in the skill, and any updates to SKILL.md are reflected in the dashboard.

**Why this priority**: The SKILL.md is the source of truth for agent behavior. The dashboard must accurately represent what's defined there.

**Independent Test**: Can be tested by verifying that agent names, sequences, and expected outputs shown on the dashboard match exactly what's defined in SKILL.md.

**Acceptance Scenarios**:

1. **Given** SKILL.md defines 9 agents with Arabic names, **When** I view the dashboard, **Then** I see those exact names in the same order
2. **Given** SKILL.md defines Phase 1 outputs "00_required_laws.md", **When** Phase 1 completes, **Then** the dashboard shows this file was generated
3. **Given** SKILL.md defines gate-by-file rules, **When** an agent cannot start due to missing prerequisite, **Then** the dashboard shows which file is missing
4. **Given** SKILL.md version changes, **When** a new case is created, **Then** the case records the new skill version and uses updated behavior

---

### Edge Cases

- What happens if an agent fails after 3 retry attempts? → Show "فشل" status with error details, pause entire case processing, display "إعادة المحاولة" (retry) and "إلغاء" (abort) buttons; user decides next action
- What happens if the user closes the browser during processing? → Processing continues in background; user can return and see current state
- What happens if Phase 2 starts but RAG law library is empty? → Show warning that no laws are available and suggest adding laws first
- How does the system handle very long agent outputs? → Truncate display with "عرض المزيد" link; full content available on expand
- What if PDF generation fails? → Show error message with retry button and option to download raw markdown instead

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST display all agents (Phase 1 + 9 Phase 2 + Phase 3) in a vertical timeline on the case dashboard
- **FR-002**: System MUST show real-time status for each agent: pending (في الانتظار), processing (جارٍ), completed (مكتمل), failed (فشل)
- **FR-003**: System MUST stream agent output text in real-time using Server-Sent Events (SSE) with animated appearance
- **FR-004**: System MUST show agent output in an expandable panel with syntax highlighting for legal references (CASE:xxx, LAW:xxx)
- **FR-005**: System MUST display processing metrics: duration per agent, total tokens used, confidence scores
- **FR-006**: System MUST generate a PDF document from the final brief output using mPDF with Arabic RTL support
- **FR-007**: System MUST use the AI model specified in the user's settings (`users.selected_model`) for all agent processing
- **FR-008**: System MUST implement gate-by-file logic: each agent waits for previous agent's output file before starting
- **FR-009**: System MUST show a visual progress indicator (percentage or step count) reflecting overall case completion
- **FR-010**: System MUST display case insights after completion: total time, statutes matched, corrections made, confidence summary
- **FR-011**: System MUST load agent definitions (names, sequence, expected files) from SKILL.md at case creation time
- **FR-012**: System MUST persist agent execution state so users can close/reopen browser without losing progress visibility
- **FR-013**: System MUST show Phase 3 agents (Judge + Devil's Advocate) as locked until user explicitly requests Phase 3
- **FR-014**: System MUST update the case show page (`resources/views/pages/cases/show.blade.php`) to include the live agent dashboard
- **FR-015**: System MUST record skill_version and skill_hash on each case for traceability
- **FR-016**: System MUST pause case processing when an agent fails after 3 retries, showing retry and abort options to the user
- **FR-017**: System MUST limit each user to a maximum of 3 concurrent cases in processing state
- **FR-018**: All new UI components MUST follow the existing dashboard styling: primary color (#006b34), Cairo font family, RTL layout, rounded-xl corners, shadow-sm cards, and existing Tailwind utility patterns
- **FR-019**: Agent timeline and output panels MUST visually integrate with existing case show page components (same card styles, spacing, typography)
- **FR-020**: Dashboard MUST display a clear step indicator showing: current step number, total steps, step name in Arabic, and visual progress bar
- **FR-021**: Each agent card MUST show its output file(s) and visually indicate which output feeds into the next agent (input → output chain)
- **FR-022**: When an agent completes, the system MUST show a brief summary of what was produced and how it connects to the next agent's task
- **FR-023**: Dashboard MUST include a collapsible "سلسلة المخرجات" (Output Chain) panel showing the full agent-to-agent data flow diagram

### Key Entities

- **AgentExecution**: Records each agent's execution (agent_number, status, started_at, completed_at, tokens_used, output_file, error_message)
- **CaseOutput**: Stores generated output files (case_id, filename, content_type, file_path, agent_number)
- **CaseMetrics**: Aggregates case-level metrics (total_duration, total_tokens, average_confidence, corrections_count)

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can see agent status updates within 2 seconds of state change (real-time responsiveness)
- **SC-002**: Character-by-character typewriter animation displays at readable speed (50-100 characters per second) without lag
- **SC-003**: All 9 Phase 2 agents display with correct Arabic names matching SKILL.md
- **SC-004**: PDF export completes within 10 seconds for typical case size (20-page brief)
- **SC-005**: PDF displays correctly in Arabic RTL with no broken characters or layout issues
- **SC-006**: Case insights accurately reflect actual processing (e.g., if 3 corrections were made, insights show 3)
- **SC-007**: Model selection from settings is correctly applied (verifiable in agent execution logs)
- **SC-008**: Users can return to a processing case and see current state within 3 seconds of page load
- **SC-009**: 95% of users can locate the PDF export button without assistance (intuitive placement)
- **SC-010**: Dashboard remains responsive (no freezing) even with 10+ concurrent agent outputs streaming
- **SC-011**: New UI components are visually indistinguishable from existing dashboard components (same styling, no jarring transitions)
- **SC-012**: Users can identify current processing step within 2 seconds of viewing dashboard (clear "أنت هنا" indicator)
- **SC-013**: Users can trace the output chain from any agent to understand what feeds into it and what it produces
- **SC-014**: Full end-to-end test completes successfully: case creation → Phase 1 → Phase 2 (all 9 agents) → PDF export with all outputs verified

---

## Assumptions

- The existing `AgentExecution` model and `CaseOutput` model are sufficient or can be extended
- SSE infrastructure can be implemented in Laravel using native streaming responses (no external service required)
- The mPDF library (already in project) supports all required Arabic RTL formatting
- The current queue worker infrastructure can emit real-time events during processing
- The SKILL.md file is the authoritative source for agent definitions and will not change during a case's processing
- The existing `layouts/app.blade.php` and component styles serve as the design system reference for all new UI

---

## Implementation Validation

After implementation, a full end-to-end test cycle MUST be performed:

1. **Case Creation**: Create a test case with sample intake text and documents
2. **Phase 1 Execution**: Verify Phase 1 agent runs, outputs "00_required_laws.md", and dashboard shows completion
3. **Phase 2 Execution**: Verify all 9 agents execute sequentially:
   - Agent 1 (Lead Counsel) → produces 01_lead_plan.md, 01_acceptance_criteria.json
   - Agent 2 (Evidence) → produces 02_ingestion_report.md, 02_chunks.jsonl
   - Agent 3 (Indexing) → produces 03_chain_of_custody.jsonl, 03_statutes_index.jsonl
   - Agent 4 (Timeline) → produces 04_timeline.json, 04_timeline.md
   - Agent 5 (Law Lead) → produces 05_issues_to_statutes.md, 05_procedural_notes.md
   - Agent 6 (Matcher) → produces 06_statutes_map.jsonl, 06_accepted_matches.md
   - Agent 7 (Defense) → produces 07_defense_skeleton.md
   - Agent 8 (Drafter) → produces 08_draft_brief.md
   - Agent 9 (Final) → produces 09_final_brief_v2.md
4. **Dashboard Verification**: Confirm each agent's status, output, and handoff is visible
5. **PDF Export**: Generate PDF and verify Arabic RTL formatting, all sections present
6. **Metrics Check**: Verify insights panel shows accurate statistics

Test MUST pass before feature is considered complete.

---

## Out of Scope

- Editing agent behavior from the dashboard (SKILL.md changes are made by admins, not end users)
- Historical analytics across multiple cases (this spec covers single-case view only)
- Real-time collaboration (multiple users viewing the same case simultaneously)
- Voice or audio feedback of processing status
- Mobile-specific responsive layout (desktop-first, basic mobile support)
