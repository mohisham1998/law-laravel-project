# Feature Specification: Saudi Legal Case Orchestration System

**Feature Branch**: `001-legal-orchestrator-system`  
**Created**: 2026-03-14  
**Status**: Draft  
**Stitch Project ID**: 18254556907662508752  
**Input**: Build a Laravel 11 legal case orchestration system that processes Saudi legal cases through AI-powered agents with Google Stitch frontend integration.

---

## Executive Summary

This system orchestrates AI agents to process Saudi legal cases through a 3-phase workflow, generating court-ready legal briefs in Arabic. The Laravel 11 backend provides a REST API that integrates with 8 Google Stitch screens, while OpenRouter API (Claude 3.5 Sonnet) powers the 9 specialized legal agents. The system ensures legal compliance with Saudi law through confidence thresholds, abrogation checking, and a self-correcting error loop.

---

## Clarifications

### Session 2026-03-14

- Q: What is the expected scale for user data and case volume? → A: Pilot scale (10-50 users, 10-20 cases/user/year, optimize for simplicity)
- Q: What email delivery mechanism should the system use for notifications? → A: No email functionality (not at this moment)
- Q: What PDF generation approach should be used for Arabic RTL legal briefs? → A: PHP library (mPDF or TCPDF with native Arabic RTL support)
- Q: What critical metrics and logs should be tracked for production monitoring? → A: Comprehensive monitoring (API latency, queue depth, agent execution times, OpenRouter API errors, cost per case)
- Q: How should the system detect and handle SKILL.md updates? → A: Manual version update (admin updates version in .env, system detects change on next case creation)

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Create Case and Analyze Requirements (Priority: P1)

**Description**: As a legal professional, I need to create a new case by uploading case documents and intake text, so the system can analyze the case and tell me which Saudi legal statutes I need to provide.

**Why this priority**: This is the entry point for all cases. Without this, no other functionality can be used. It's the foundation of the entire workflow and delivers immediate value by identifying required laws.

**Independent Test**: Can be fully tested by creating a case with sample documents and verifying that Phase 1 completes with a list of required Saudi legal statutes. Delivers value by automating legal research to identify applicable laws.

**Acceptance Scenarios**:

1. **Given** I am on the Dashboard Home screen, **When** I click "New Case" button, **Then** I am navigated to the New Case Form
2. **Given** I am on the New Case Form, **When** I enter a case title "قضية رقم 3461589" and intake text describing a criminal case, **Then** the form accepts Arabic RTL text
3. **Given** I have entered case details, **When** I upload 6 document files (.txt format, each < 10MB), **Then** the system displays each filename with size and a remove button
4. **Given** I have uploaded invalid files (.pdf, .docx), **When** I try to submit, **Then** the system shows validation error "Only .txt and .md files allowed"
5. **Given** I have valid case data and documents, **When** I click "Create Case", **Then** the system creates the case and redirects me to Case Detail View showing Phase 1 in progress
6. **Given** Phase 1 is processing, **When** I view the Case Detail screen, **Then** I see a progress indicator and current status
7. **Given** Phase 1 completes successfully, **When** the analysis finishes, **Then** the Laws Upload Modal appears automatically with a list of 4 required Saudi laws (نظام الإثبات, نظام المرافعات الشرعية, etc.)
8. **Given** Phase 1 completes, **When** I view the required laws list, **Then** each law shows its name in Arabic and the reason why it's needed for this case

---

### User Story 2 - Upload Laws and Process Case Through 9 Agents (Priority: P1)

**Description**: As a legal professional, I need to upload the required Saudi legal statutes and trigger the 9-agent processing workflow, so the system generates a comprehensive legal brief ready for court submission.

**Why this priority**: This is the core value proposition - automated legal brief generation. Without this, the system is just a document analyzer. This delivers the primary outcome users need.

**Independent Test**: Can be tested independently by starting with a case that has completed Phase 1, uploading the required law files, and verifying all 9 agents execute sequentially to produce 19 output files including the final brief.

**Acceptance Scenarios**:

1. **Given** Phase 1 is complete and Laws Upload Modal is displayed, **When** I upload "نظام الإثبات.txt" (UTF-8 encoded), **Then** the system marks this law as "uploaded" with a green checkmark
2. **Given** I have uploaded 3 out of 4 required laws, **When** I try to click "Confirm & Start Phase 2", **Then** the button is disabled with tooltip "Upload all required laws first"
3. **Given** I have uploaded all 4 required laws, **When** I click "Confirm & Start Phase 2", **Then** the modal closes and Agent Timeline shows Agent 1 (Lead Counsel) as "in_progress"
4. **Given** Phase 2 is processing, **When** I view the Agent Timeline, **Then** I see all 9 agents listed with Agent 1 highlighted and pulsing, others showing "pending" status
5. **Given** Agent 1 completes, **When** the timeline updates, **Then** Agent 1 shows "completed" status with duration "12.5s" and token count "8,234", and Agent 2 starts with "in_progress" status
6. **Given** any agent is processing, **When** I view the progress bar, **Then** it shows accurate percentage (e.g., "Agent 3/9 - 33% complete")
7. **Given** Agent 6 (Statute Matcher) finds a low-confidence match (0.65), **When** the agent completes, **Then** the Error Log Viewer shows the error with type "low_confidence" and the fix applied (logical fallback to fiqh principles)
8. **Given** all 9 agents complete successfully, **When** Phase 2 finishes, **Then** the Case Detail View shows status "completed", progress bar at 100%, and "Download Final Brief" button is enabled
9. **Given** Phase 2 completes, **When** I click on the Outputs tab, **Then** I see all 19 output files listed (01_lead_plan.md through 09_final_brief_v2.md) with download buttons

---

### User Story 3 - Monitor Real-Time Progress and View Outputs (Priority: P1)

**Description**: As a legal professional, I need to monitor the real-time progress of case processing and view intermediate outputs, so I can track the system's work and intervene if needed.

**Why this priority**: Transparency and control are critical for legal work. Users need to see what's happening and access intermediate outputs for review. This builds trust in the AI-generated content.

**Independent Test**: Can be tested by observing a processing case and verifying that the UI updates every 5 seconds without manual refresh, and that all output files are accessible as they're generated.

**Acceptance Scenarios**:

1. **Given** a case is in Phase 2 with Agent 4 processing, **When** I stay on the Case Detail View for 5 seconds, **Then** the page automatically polls the API and updates the progress without page refresh
2. **Given** Agent 5 just completed, **When** the polling updates the UI, **Then** the Agent Timeline shows Agent 5 as "completed" and Agent 6 as "in_progress" with smooth transition animation
3. **Given** Agent 3 generated 3 output files, **When** I click on the Outputs tab, **Then** I see "03_chain_of_custody.jsonl", "03_statutes_index.jsonl", and "03_conflict_warnings.md" with download icons
4. **Given** I am viewing outputs, **When** I click "Download" on "03_statutes_index.jsonl", **Then** the file downloads with proper Content-Type header (application/x-ndjson)
5. **Given** Agent 8 is processing, **When** I view the estimated completion time, **Then** it shows "Estimated: 8 minutes remaining" based on average agent duration
6. **Given** the case status changes from "processing" to "completed", **When** the polling detects this, **Then** the UI stops polling and shows final completion status with total duration "18 minutes 32 seconds"

---

### User Story 4 - View and Export Final Legal Brief (Priority: P1)

**Description**: As a legal professional, I need to view the generated legal brief in a readable format and export it for court submission, so I can review the content and submit it to the Saudi court system (Najiz).

**Why this priority**: This is the final deliverable - the court-ready legal brief. Without this, all previous work has no output. This completes the primary user journey.

**Independent Test**: Can be tested by opening a completed case, viewing the final brief in the Output Viewer, and verifying it renders correctly in Arabic RTL with proper formatting and can be exported.

**Acceptance Scenarios**:

1. **Given** Phase 2 is complete, **When** I click "Download Final Brief" button, **Then** the Output Viewer opens displaying "09_final_brief_v2.md"
2. **Given** the Output Viewer is open, **When** the brief loads, **Then** it renders in Arabic RTL layout with Tajawal font and proper Markdown formatting (headings, lists, bold text)
3. **Given** I am viewing the brief in "internal view" mode, **When** I scroll through the content, **Then** I see CASE:{C001} and LAW:{نظام_الإثبات_م11} references highlighted in yellow for traceability
4. **Given** I want a court-ready version, **When** I toggle to "clean version", **Then** all CASE:{} and LAW:{} references are hidden and the brief shows only the legal text
5. **Given** the brief is displayed, **When** I click "Copy to Clipboard", **Then** the entire brief content is copied in plain text format
6. **Given** I need to submit to court, **When** I click "Download as .md", **Then** the file downloads as "القضية_3461589_المذكرة_النهائية.md" with UTF-8 encoding
7. **Given** I want a PDF version, **When** I click "Download as .pdf", **Then** the system generates a PDF with Arabic RTL support and downloads it
8. **Given** the brief contains statute citations, **When** I review the content, **Then** each citation includes the statute name, article number, and quoted text in Arabic

---

### User Story 5 - Review Self-Correcting Errors and Quality Assurance (Priority: P2)

**Description**: As a legal professional, I need to review any errors that occurred during processing and see how the system corrected them, so I can verify the quality and reliability of the generated brief.

**Why this priority**: Legal work requires high accuracy. Users need to see what went wrong and how it was fixed to trust the output. This is important for quality assurance but not blocking for basic functionality.

**Independent Test**: Can be tested by processing a case that triggers low-confidence matches or other errors, and verifying the Error Log Viewer displays all errors with fixes applied.

**Acceptance Scenarios**:

1. **Given** Agent 6 detected a low-confidence statute match (0.65), **When** I click on the "Error Log" tab, **Then** I see an entry with timestamp, agent "6 - Statute Matcher", error type "low_confidence", and details "Statute match confidence 0.65 < 0.70"
2. **Given** an error entry is displayed, **When** I read the "Fix Applied" column, **Then** it shows "Used logical fallback to fiqh principle: البينة على من ادعى واليمين على من أنكر"
3. **Given** an error has a lesson learned, **When** I view the "Lesson Learned" column, **Then** it shows "Always check statute supersession before matching - نظام الإثبات 1443هـ supersedes نظام المرافعات الشرعية Bab 9"
4. **Given** multiple errors exist, **When** I use the filter dropdown, **Then** I can filter by agent number (1-9), error type (low_confidence, missing_reference, abrogated_statute), or date range
5. **Given** I want to keep a record, **When** I click "Export Error Log", **Then** the system downloads "errors_log.md" with all errors formatted in Markdown
6. **Given** errors exist for the case, **When** I view the Error Log tab, **Then** a red badge shows the error count (e.g., "3") on the tab label

---

### User Story 6 - Trigger Optional Phase 3 Judicial Arbitration (Priority: P2)

**Description**: As a legal professional, I need to optionally trigger a Phase 3 review process where a Judge Agent and Devil's Advocate Agent review and strengthen the brief, so I can get a fortified version for high-stakes cases.

**Why this priority**: This is an optional enhancement for critical cases. Most users will be satisfied with Phase 2 output, but this adds extra quality assurance for important cases.

**Independent Test**: Can be tested by completing Phase 2, clicking "Start Phase 3", and verifying that two additional agents run to produce a v3 brief with strengthened arguments.

**Acceptance Scenarios**:

1. **Given** Phase 2 is complete, **When** I view the Case Detail View, **Then** I see a "Start Phase 3 (Optional)" button with description "Judicial review and argument fortification"
2. **Given** I want extra quality assurance, **When** I click "Start Phase 3", **Then** the Agent Timeline expands to show Agent 10 (Judge) and Agent 11 (Devil's Advocate) as "pending"
3. **Given** Phase 3 is processing, **When** Agent 10 (Judge) runs, **Then** the timeline shows it reviewing the brief for legal soundness with status "in_progress"
4. **Given** Agent 10 completes, **When** Agent 11 (Devil's Advocate) starts, **Then** it challenges weak arguments and identifies vulnerabilities
5. **Given** Phase 3 completes, **When** I view the Outputs tab, **Then** I see three brief versions: "09_final_brief_v2.md" (Phase 2), "12_fortification_plan.md", and "13_final_brief_v3.md" (Phase 3 fortified)
6. **Given** I want to compare versions, **When** I open both v2 and v3 in the Output Viewer, **Then** I can see the strengthened arguments and additional legal citations in v3

---

### User Story 7 - Manage User Settings and View System Status (Priority: P2)

**Description**: As a legal professional, I need to configure my preferences, select AI models, track token usage and costs in SAR, manage my API token, and see which SKILL.md version is being used, so I can optimize costs and customize my experience.

**Why this priority**: Cost control and model selection are critical for production use. Users need visibility into spending and ability to choose cost-effective models. Elevated from P3 to P2 due to cost management importance.

**Independent Test**: Can be tested by navigating to Settings page, selecting a different OpenRouter model, viewing token usage statistics in SAR, changing the confidence threshold, and regenerating the API token.

**Acceptance Scenarios**:

1. **Given** I am on any screen, **When** I click the Settings icon in the header, **Then** I navigate to the Settings Page
2. **Given** I am on the Settings Page, **When** the page loads, **Then** I see my user profile (name, email), current API token (partially masked), and rate limit status "7/10 cases this hour"
3. **Given** I want to select a different AI model, **When** I click the "Model Selector" dropdown, **Then** the system fetches available models from OpenRouter API and displays them in a searchable dropdown
4. **Given** the model dropdown is open, **When** I type "gpt" in the autocomplete search, **Then** the list filters to show only models containing "gpt" (e.g., "openai/gpt-4", "openai/gpt-3.5-turbo")
5. **Given** I am viewing the model list, **When** I hover over a model, **Then** I see a tooltip showing: model name, provider, context window (e.g., "128K tokens"), input price (e.g., "$3/M tokens"), output price (e.g., "$15/M tokens")
6. **Given** I want to switch models, **When** I select "anthropic/claude-3-opus" from the dropdown, **Then** the system saves my preference and shows "New cases will use: Claude 3 Opus"
7. **Given** I want to track my spending, **When** I view the "Token Usage Statistics" section, **Then** I see: "Total tokens consumed: 1,234,567", "Estimated cost: 145.50 SAR" (converted from USD at current exchange rate)
8. **Given** I want detailed cost breakdown, **When** I click "View Cost Breakdown", **Then** I see a table with columns: Case Number, Date, Tokens Used, Cost (SAR), Model Used
9. **Given** I want to export cost data, **When** I click "Export Cost Report", **Then** the system downloads a CSV file with all my case costs in SAR
10. **Given** I want to change the confidence threshold, **When** I drag the slider from 0.70 to 0.75, **Then** the system updates the threshold and shows "New cases will use confidence threshold: 0.75"
11. **Given** my API token is compromised, **When** I click "Regenerate Token", **Then** the system generates a new token, displays it once, and updates localStorage
12. **Given** I want to track system updates, **When** I view the "System Settings" section, **Then** I see "Current SKILL.md version: v2.4.0" and "Last updated: 2026-03-14 10:30 AM"
13. **Given** I want notifications, **When** I view notification preferences, **Then** I see "Email notifications: Coming soon" (deferred to future release)
14. **Given** I want to see real-time cost estimates, **When** I view a processing case in Case Detail View, **Then** I see "Estimated cost so far: 12.50 SAR" updating as agents complete

---

### User Story 8 - View Dashboard Statistics and Case List (Priority: P3)

**Description**: As a legal professional, I need to see an overview of all my cases with statistics and quick access to case details, so I can manage multiple cases efficiently.

**Why this priority**: Dashboard is important for multi-case management but not critical for single-case workflow. Users can still access cases directly via URL.

**Independent Test**: Can be tested by creating multiple cases in different states, then verifying the Dashboard Home displays accurate statistics and allows navigation to case details.

**Acceptance Scenarios**:

1. **Given** I have 10 total cases (3 processing, 6 completed, 1 failed), **When** I load the Dashboard Home, **Then** I see 4 statistics cards with correct counts
2. **Given** the statistics cards are displayed, **When** I view them, **Then** they show: "Total Cases: 10", "Processing: 3" (with spinner icon), "Completed: 6" (with checkmark), "Failed: 1" (with alert icon)
3. **Given** I have cases in the system, **When** I view the case list table, **Then** I see columns: Case Number, Title (in Arabic), Status (badge), Phase, Current Agent, Progress Bar, Actions (View button)
4. **Given** a case is processing, **When** I view its row in the table, **Then** the progress bar shows real-time percentage (e.g., "66%" for Agent 6/9) and updates every 5 seconds
5. **Given** I want to access a specific case, **When** I click the "View" button on a case row, **Then** I navigate to that case's Case Detail View
6. **Given** I have many cases, **When** I use pagination controls, **Then** I can navigate through pages showing 20 cases per page
7. **Given** I want to filter cases, **When** I select "Processing" from the status filter dropdown, **Then** the table shows only cases with status "processing"

---

### Edge Cases

- **What happens when a user uploads a law file that is corrupted or not UTF-8 encoded?**
  - System validates encoding during upload and shows error: "Invalid file encoding. Please upload UTF-8 encoded .txt or .md files."
  - Case remains in "awaiting_laws" status until valid file is uploaded.

- **What happens when OpenRouter API is down or returns 500 errors?**
  - System retries with exponential backoff (2s, 4s, 8s) up to 3 attempts.
  - If all retries fail, agent execution is marked as "failed" with error message.
  - User sees error in Case Detail View with option to "Retry Agent X".
  - Case status changes to "failed" and user receives notification.

- **What happens when an agent times out after 5 minutes (OpenRouter timeout)?**
  - System logs timeout error to `agent_executions` table.
  - Agent status changes to "failed" with error "Agent X timed out after 300 seconds".
  - User can retry the specific agent without restarting entire Phase 2.

- **What happens when Phase 2 is processing and the queue worker crashes?**
  - Laravel Horizon detects failed job and marks it for retry.
  - Job retries from the last completed agent (gate-by-file validation ensures no duplicate work).
  - User sees status "processing" with note "Resuming from Agent X".

- **What happens when a user tries to upload laws before Phase 1 completes?**
  - "Upload Laws" button is disabled with tooltip "Phase 1 must complete first".
  - Laws Upload Modal only appears after Phase 1 status = "completed".

- **What happens when Agent 6 cannot find any statute matches with confidence >= 0.70?**
  - Agent logs all attempted matches to `06_gaps_and_todo.md`.
  - Agent 8 (Drafter) uses logical fallback to fiqh principles per constitution.
  - Case completes with status "completed_with_warnings".
  - User sees warning badge on Error Log tab: "Low confidence matches detected".

- **What happens when a user deletes a case that is currently processing?**
  - System performs soft delete (sets `deleted_at` timestamp).
  - Queue job checks for soft delete before each agent execution.
  - If deleted, job stops gracefully and marks case as "cancelled".
  - Case still appears in user's list with status "cancelled" for audit trail.

- **What happens when SKILL.md is updated mid-processing?**
  - Case continues using the `skill_version` and `skill_hash` captured at start.
  - Admin updates SKILL_VERSION in .env file to trigger version change.
  - New cases created after .env update use the new SKILL.md version.
  - Case Detail View shows which version was used: "Generated with SKILL.md v2.4.0".

- **What happens when two agents generate conflicting outputs?**
  - Agent 9 (QA) detects conflicts during validation.
  - QA agent logs conflict to `errors_log.md` with details.
  - QA agent resolves conflict based on constitution principles (e.g., newer statute supersedes older).
  - Resolution is documented in `09_QA_summary.md`.

- **What happens when a user's rate limit (10 cases/hour) is exceeded?**
  - API returns 429 Too Many Requests with header `Retry-After: 3600`.
  - Stitch screen shows toast notification: "Rate limit exceeded. You can create 10 cases per hour. Try again in 45 minutes."
  - Settings page shows "10/10 cases this hour" in red.

- **What happens when the PostgreSQL database is full or unavailable?**
  - API returns 503 Service Unavailable.
  - Stitch screens show error state: "System temporarily unavailable. Please try again later."
  - Laravel logs critical error to monitoring system.
  - Admin receives alert via monitoring dashboard.

- **What happens when a user tries to start Phase 3 before Phase 2 completes?**
  - "Start Phase 3" button is disabled with tooltip "Phase 2 must complete first".
  - Button only becomes enabled when Phase 2 status = "completed".

- **What happens when the final brief exceeds 10MB in size?**
  - System stores brief in database (PostgreSQL text field has no practical limit for legal briefs).
  - Download endpoint streams the file to avoid memory issues.
  - If PDF generation fails due to size, user gets .md version with note "PDF generation unavailable for briefs > 10MB".

- **What happens when OpenRouter API changes model pricing or removes a model?**
  - System caches model list for 24 hours, so changes appear after cache expires.
  - If user's selected model is removed, system falls back to default (anthropic/claude-3.5-sonnet) with warning notification.
  - Cost calculations use pricing at time of execution (stored in `agent_executions` table).
  - Historical cost data remains accurate even if current pricing changes.

- **What happens when SAR exchange rate changes?**
  - System fetches current USD to SAR exchange rate from external API (e.g., exchangerate-api.com) daily.
  - Cost displays use current exchange rate for real-time estimates.
  - Historical costs are recalculated with current rate for consistency in reports.
  - Exchange rate is cached for 24 hours to reduce API calls.

- **What happens when a user selects a model that is more expensive than Claude 3.5 Sonnet?**
  - System displays warning: "This model costs $X/M tokens (Y% more expensive than default). Proceed?"
  - User must confirm before model is saved.
  - Settings page shows cost comparison: "Current model: +45% cost vs default".
  - Cost breakdown table highlights cases using expensive models in orange.

---

## Requirements *(mandatory)*

### Functional Requirements

#### Case Management
- **FR-001**: System MUST allow authenticated users to create new legal cases by providing a case title, intake text (Arabic/English), and multiple document files
- **FR-002**: System MUST validate uploaded documents: only .txt and .md files allowed, max 10MB per file, UTF-8 encoding required
- **FR-003**: System MUST support Arabic RTL text input in all text fields
- **FR-004**: System MUST display uploaded documents with filename, size, and remove button before case creation

#### Phase 1: Case Analysis
- **FR-005**: System MUST analyze case intake text and documents to determine legal domain and identify required Saudi legal statutes
- **FR-006**: System MUST generate a list of required laws with Arabic names and reasons why each is needed
- **FR-007**: System MUST automatically display Laws Upload interface when Phase 1 completes
- **FR-008**: System MUST prevent Phase 2 from starting until all required laws are uploaded

#### Phase 2: Nine-Agent Processing
- **FR-009**: System MUST execute 9 agents sequentially: Lead Counsel → Evidence → Chain of Custody → Timeline → Law Manager → Statute Matcher → Defense Strategy → Drafter → QA
- **FR-010**: System MUST enforce gate-by-file validation: each agent checks for prerequisite outputs before executing
- **FR-011**: System MUST generate 19 output files total across all 9 agents
- **FR-012**: System MUST track execution metrics for each agent: duration, tokens used, cost
- **FR-013**: System MUST update progress percentage after each agent completes
- **FR-014**: System MUST apply confidence threshold (default 0.70, user-configurable) to statute matching
- **FR-015**: System MUST log statute matches with confidence < threshold and use logical fallback to fiqh principles
- **FR-016**: System MUST check for abrogated statutes and prevent their use
- **FR-017**: System MUST generate final legal brief in Arabic with proper RTL formatting

#### Phase 3: Judicial Arbitration (Optional)
- **FR-018**: System MUST allow users to optionally trigger Phase 3 after Phase 2 completes
- **FR-019**: System MUST execute 2 additional agents: Judge Agent → Devil's Advocate Agent
- **FR-020**: System MUST preserve Phase 2 output when Phase 3 runs, making all versions available

#### Self-Correcting Error Loop
- **FR-021**: System MUST log all errors with structure: timestamp, agent number, error type, details, fix applied, lesson learned
- **FR-022**: System MUST make error log available to all subsequent agents after first error
- **FR-023**: System MUST detect error types: low_confidence, missing_reference, abrogated_statute, temporal_contradiction

#### Real-Time Progress Tracking
- **FR-024**: System MUST provide real-time progress updates that refresh every 5 seconds during processing
- **FR-025**: System MUST calculate and display estimated completion time based on average agent duration
- **FR-026**: System MUST show current agent name and status during processing
- **FR-027**: System MUST display progress percentage calculated as (current_agent / 9) * 100

#### Output Management
- **FR-028**: System MUST store all output files and make them available for download
- **FR-029**: System MUST provide the highest version brief (v3 if Phase 3 ran, v2 otherwise) as primary output
- **FR-030**: System MUST support two view modes: "internal view" (shows references) and "clean view" (hides references)
- **FR-031**: System MUST generate PDF version of final brief with Arabic RTL support using PHP library (mPDF or TCPDF)

#### Model Selection & Cost Tracking
- **FR-032**: System MUST fetch available AI models from OpenRouter and display in searchable dropdown with autocomplete
- **FR-033**: System MUST display model details: name, provider, context window, input/output pricing
- **FR-034**: System MUST allow users to select and save preferred OpenRouter model
- **FR-035**: System MUST use user's selected model for all new cases
- **FR-036**: System MUST track total token usage per user (sum of prompt + completion tokens)
- **FR-037**: System MUST calculate costs in USD and convert to SAR using current exchange rate (1 USD = 3.75 SAR)
- **FR-038**: System MUST display token usage statistics: total tokens consumed and estimated cost in SAR
- **FR-039**: System MUST provide cost breakdown showing: case number, date, tokens, cost in SAR, model used
- **FR-040**: System MUST allow users to export cost breakdown as CSV file
- **FR-041**: System MUST display real-time cost estimate during case processing
- **FR-042**: System MUST cache OpenRouter models list for 24 hours
- **FR-043**: System MUST warn users when selecting models more expensive than default

#### Authentication & Security
- **FR-044**: System MUST authenticate users via token-based authentication
- **FR-045**: System MUST allow users to regenerate API tokens from Settings
- **FR-046**: System MUST enforce rate limiting: 10 cases per hour per user
- **FR-047**: System MUST display rate limit status in Settings page

#### Dashboard & Statistics
- **FR-048**: System MUST display dashboard statistics: total cases, processing, completed, failed
- **FR-049**: System MUST provide case list with pagination, filtering by status, and sorting
- **FR-050**: System MUST show real-time progress for processing cases in dashboard table

#### Settings & Configuration
- **FR-051**: System MUST allow users to configure confidence threshold (0.50 to 0.90 range)
- **FR-052**: System MUST display current SKILL.md version (from .env SKILL_VERSION) and last updated timestamp in Settings page
- **FR-053**: Email notifications are out of scope for initial release (deferred)

#### Error Handling
- **FR-054**: System MUST retry API calls with exponential backoff on failures
- **FR-055**: System MUST allow users to retry individual failed agents
- **FR-056**: System MUST gracefully handle system crashes by resuming from last completed agent

### Key Entities

- **Case**: Represents a legal case with intake text, documents, laws, and processing status
- **Document**: Case document uploaded by user
- **Law**: Saudi legal statute uploaded by user
- **Output**: Generated file from an agent (19 files per case)
- **Agent Execution**: Record of single agent run with metrics (duration, tokens, cost, API latency)
- **Error Log**: Self-correcting error entry with fix and lesson learned
- **User**: Authenticated user with model preferences, token usage tracking, and cost history
- **System Metric**: Monitoring data (queue depth, API response times, OpenRouter errors, cost per case)

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can create a case and receive Phase 1 analysis within 2 minutes
- **SC-002**: System processes Phase 2 (9 agents) in under 30 minutes
- **SC-003**: System responds to status checks in under 200ms
- **SC-004**: System handles 10 concurrent cases without performance degradation (pilot scale: 10-50 users)
- **SC-005**: 95% of statute matches achieve confidence >= 0.70
- **SC-006**: Final legal briefs render correctly in Arabic RTL format 100% of the time
- **SC-007**: Self-correcting error loop detects and logs 100% of errors
- **SC-008**: UI updates every 5 seconds via polling without manual refresh
- **SC-009**: All 19 output files are accessible for successful completions
- **SC-010**: System maintains 99.9% uptime
- **SC-011**: Average cost per case is 0.98 SAR (Phase 2) to 1.20 SAR (Phase 3) at 1 USD = 3.75 SAR
- **SC-012**: Zero data loss with audit trail for all operations
- **SC-013**: 90% of users successfully complete full workflow on first attempt
- **SC-014**: System correctly prevents use of abrogated statutes 100% of the time
- **SC-015**: Token usage and cost tracking is accurate within 1% margin
- **SC-016**: Model selector loads all available models within 2 seconds
- **SC-017**: Cost breakdown export generates within 3 seconds for up to 200 cases per user (pilot scale)
- **SC-018**: Real-time cost estimates update within 5 seconds of agent completion

---

## Review & Acceptance Checklist

### Specification Quality
- [ ] All user stories are prioritized and independently testable
- [ ] Each user story has clear Given/When/Then acceptance scenarios
- [ ] Edge cases cover error scenarios and boundary conditions
- [ ] Functional requirements are specific and measurable
- [ ] Success criteria have specific metrics and thresholds
- [ ] Key entities are defined

### Constitution Alignment
- [ ] API-First Architecture (no frontend in Laravel)
- [ ] Docker containerization will be specified in plan phase
- [ ] Agent orchestration with gate-by-file validation specified
- [ ] Senior-level Laravel standards will be applied in implementation
- [ ] Stitch dashboard integration in all user stories
- [ ] SKILL.md integration and hot-reload workflow specified
- [ ] Legal domain compliance (0.70 confidence, zero hallucination)
- [ ] Model selection and cost tracking in SAR specified

### Stitch Screen Coverage
- [ ] Dashboard Home (User Story 8)
- [ ] New Case Form (User Story 1)
- [ ] Case Detail View (User Stories 2, 3)
- [ ] Laws Upload Modal (User Story 2)
- [ ] Output Viewer (User Story 4)
- [ ] Error Log Viewer (User Story 5)
- [ ] Agent Timeline (User Stories 2, 3)
- [ ] Settings Page with model selection and cost tracking (User Story 7)

### Completeness
- [ ] All 3 phases specified
- [ ] All 9 agents named
- [ ] Real-time progress with 5-second polling specified
- [ ] Self-correcting error loop specified
- [ ] Model selection with autocomplete specified
- [ ] Token usage and cost tracking in SAR specified
- [ ] Authentication and rate limiting specified
- [ ] Arabic RTL support specified

---

## Next Steps

1. Review this specification with stakeholders
2. Run `/speckit.clarify` to address any underspecified areas (optional)
3. Run `/speckit.plan` to define Docker, Laravel, PostgreSQL, Redis architecture and API endpoints
4. Run `/speckit.tasks` to break down into development tasks
5. Run `/speckit.implement` to build milestone by milestone

---

**Specification Status**: Ready for Review  
**Estimated Complexity**: High  
**Key Features**: 3-phase workflow, 9 AI agents, model selection, cost tracking in SAR, real-time updates, Arabic RTL support