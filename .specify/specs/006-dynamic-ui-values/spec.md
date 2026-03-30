# Feature Specification: Productionize Smart Legal Advisor - Dynamic UI Values

**Feature Branch**: `006-dynamic-ui-values`  
**Created**: 2026-03-24  
**Status**: Draft  
**Input**: User description: "Productionize the Smart Legal Advisor application by replacing all remaining static and hardcoded UI values with dynamic, real-data-driven behavior so the system is ready for real legal case operations."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Trustworthy Case Progress Display (Priority: P1)

As a legal analyst, I want to open a case and immediately trust the displayed progress, so I know whether analysis is actually advancing.

**Why this priority**: This is the core value proposition - users must be able to trust the data they see. Without this, the entire application loses credibility for legal operations.

**Independent Test**: Can be tested by creating a new case, uploading documents, and observing that progress indicators update from 0% to reflect actual processing state without any page refresh or hardcoded values.

**Acceptance Scenarios**:

1. **Given** a new case with no documents, **When** the user visits the AI Analysis page, **Then** the progress shows 0% with appropriate "waiting for documents" message
2. **Given** a case with documents under processing, **When** the user views the AI Analysis page, **Then** progress reflects actual agent execution state (not static placeholder)
3. **Given** a completed case, **When** the user views the AI Analysis page, **Then** progress shows 100% with all stages marked complete

---

### User Story 2 - Accurate Document and Fact Counts (Priority: P1)

As a legal analyst, I want document, fact, and law-match counts to be accurate, so I can judge the completeness of the analysis.

**Why this priority**: Legal professionals need accurate counts to assess analysis completeness and determine if additional work is needed.

**Independent Test**: Can be tested by uploading multiple documents to a case and verifying the AI Insights section shows the correct document count.

**Acceptance Scenarios**:

1. **Given** a case with 5 uploaded documents, **When** viewing AI Analysis page, **Then** the document count in AI Insights shows exactly 5
2. **Given** a case that has extracted 10 legal facts, **When** viewing AI Analysis page, **Then** the facts count shows exactly 10
3. **Given** a case that has matched 15 laws, **When** viewing AI Analysis page, **Then** the law matches count shows exactly 15
4. **Given** a case with no documents yet, **When** viewing AI Analysis page, **Then** all counts show 0 or "No data yet" (not placeholder numbers)

---

### User Story 3 - Real Operational Dashboard (Priority: P1)

As an operations user, I want the dashboard to reflect real platform activity and trends, so I can monitor workload and outcomes.

**Why this priority**: Operations teams need accurate visibility into case volumes, completion rates, and trends for resource planning and performance monitoring.

**Independent Test**: Can be tested by creating multiple cases with different statuses and verifying the dashboard statistics and charts reflect actual counts.

**Acceptance Scenarios**:

1. **Given** 10 active cases and 5 completed cases in the system, **When** viewing the dashboard, **Then** the active cases count shows 10 and completed shows 5 (not hardcoded values)
2. **Given** cases created over the past 6 months, **When** viewing the dashboard, **Then** the monthly chart shows actual case creation counts per month
3. **Given** varying completion rates, **When** viewing the dashboard, **Then** doughnut chart percentages sum to 100% and reflect actual status distribution

---

### User Story 4 - Working Analysis Controls (Priority: P2)

As a user, I want controls like pause and refresh to work predictably, so I can manage or inspect live analysis safely.

**Why this priority**: Users need functional controls to manage case processing, especially when they need to pause analysis for review or refresh to see latest state.

**Independent Test**: Can be tested by starting case processing and using the pause button to verify it actually pauses the analysis.

**Acceptance Scenarios**:

1. **Given** a case actively processing, **When** clicking the pause button, **Then** the case status changes to paused and the button shows resume option
2. **Given** a paused case, **When** clicking the refresh button, **Then** the page displays the latest case state from the database
3. **Given** a completed case, **When** clicking pause, **Then** the user receives feedback that the action is not available (no effect on completed cases)

---

### User Story 5 - Case Detail Timeline Accuracy (Priority: P2)

As a reviewer, I want the case detail timeline and AI analysis stages to match the real execution history, so I can understand what happened and what remains.

**Why this priority**: Reviewers need accurate timeline information to track case progress, identify bottlenecks, and understand the complete analysis history.

**Independent Test**: Can be tested by processing a case through multiple phases and verifying the timeline shows accurate stage completion order.

**Acceptance Scenarios**:

1. **Given** a case that completed Phase 1 but not Phase 2, **When** viewing the case detail, **Then** the timeline shows Phase 1 complete and Phase 2 pending (not hardcoded)
2. **Given** a case that failed at agent 5, **When** viewing the case detail, **Then** the timeline shows the failure state with appropriate error indicator
3. **Given** a case with mixed agent outcomes, **When** viewing the case detail, **Then** each agent's status (completed, failed, in-progress, pending) matches actual execution state

---

### User Story 6 - Dynamic Agent Progress (Priority: P2)

As an operations user, I want agent progress indicators to show real execution state, so I can monitor individual worker performance.

**Why this priority**: Understanding which agents are processing, blocked, or completed helps identify bottlenecks in the analysis pipeline.

**Independent Test**: Can be tested by starting multiple agents and verifying their progress bars reflect actual execution percentages.

**Acceptance Scenarios**:

1. **Given** an agent at 50% completion, **When** viewing dashboard agent section, **Then** the progress bar shows 50% (not hardcoded 92% or 45%)
2. **Given** a blocked agent, **When** viewing dashboard, **Then** the agent shows blocked/waiting state with appropriate status indicator
3. **Given** a completed agent, **Then** the progress shows 100% with completion status

---

### Edge Cases

- What happens when a case has no associated documents, agent executions, or outputs?
- How does the system display a case where one phase fails but others complete?
- How are dashboard statistics calculated when there are zero cases in the system?
- What displays when an API call fails and real data cannot be retrieved?
- How is the UI handled when there is a network timeout during a refresh?

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The AI Analysis page MUST retrieve case data from the LegalCase model and display the current phase and progress percentage
- **FR-002**: The AI Analysis page MUST query AgentExecution records to determine stage states (not_started, queued, in_progress, completed, paused, failed)
- **FR-003**: The AI Analysis page AI Insights section MUST count related records - documents from CaseDocument, facts from extracted outputs, laws from CaseLaw relationships
- **FR-004**: The pause button on AI Analysis page MUST call an API endpoint that updates case status and returns success/failure response
- **FR-005**: The refresh button on AI Analysis page MUST reload the current case data via AJAX and update the DOM with fresh values
- **FR-006**: The dashboard statistics cards MUST query the database for actual counts (active_cases, analyzing_cases, completed_briefs)
- **FR-007**: The dashboard monthly chart MUST query LegalCase for cases grouped by creation month for the last 6 months
- **FR-008**: The dashboard doughnut chart MUST calculate percentages from actual case status distribution
- **FR-009**: The dashboard agent progress section MUST retrieve agent execution progress from AgentExecution model ordered by agent_number
- **FR-010**: The cases index page "View All" link MUST navigate to a filtered view or paginated list
- **FR-011**: All progress indicators MUST show empty state messages when no data is available (not static placeholder values)
- **FR-012**: Case detail page timeline MUST display stage completion based on actual AgentExecution records

### Key Entities

- **LegalCase**: Core entity representing a legal case, contains status, phase, progress_percentage fields
- **AgentExecution**: Tracks individual agent runs, contains agent_number, status, progress_percentage, output_path
- **CaseDocument**: Stores uploaded documents linked to cases
- **CaseOutput**: Contains analysis outputs including extracted facts and law matches

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: No production-facing page shows hardcoded analytics, progress, or workflow values - verified by code review of all blade templates
- **SC-002**: Users can observe real case progress from dashboard to case detail to AI analysis without contradictions - verified by end-to-end test with sample case
- **SC-003**: Dashboard charts and percentages are derived from actual system records - verified by comparing dashboard values against database queries
- **SC-004**: Workflow controls (pause/refresh) operate meaningfully and safely - verified by functional testing of each control
- **SC-005**: Application behaves like a live operational product rather than a static prototype - verified by user acceptance testing with realistic scenarios

### Acceptance Criteria

- AI Analysis page progress bar shows value from actual case.progress_percentage
- AI Analysis stage statuses derived from AgentExecution.status for each agent_number
- AI Insights counts match actual document/fact/law counts from database
- Dashboard statistics cards display counts from database queries
- Dashboard monthly chart displays data from actual case creation dates
- Dashboard doughnut chart percentages sum to 100% based on real status distribution
- Agent progress section shows progress from AgentExecution.progress_percentage
- Pause button triggers API call that updates case status
- Refresh button fetches fresh data via API and updates page content

---

## Assumptions

- The existing LegalCase, AgentExecution, CaseDocument, and CaseOutput models contain the necessary data structure for this feature
- API endpoints exist or can be created to support pause/resume case operations
- Dashboard controller can be extended to provide the required data aggregation
- Frontend JavaScript can be modified to handle dynamic data loading without breaking existing functionality
- The application uses appropriate caching to balance performance with data freshness

---

## Out of Scope

- Major visual redesign of styling or layout
- Modifying the underlying legal workflow or adding new analysis stages
- Changes to the curated model configuration list (unless required by another feature)
- Implementing new agent types or pipeline stages
