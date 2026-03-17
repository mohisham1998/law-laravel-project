# Implementation Plan: Live Case Agent Dashboard

**Branch**: `002-live-case-agent-dashboard` | **Date**: 2026-03-16 | **Spec**: [spec.md](./spec.md)

---

## Summary

Build a real-time animated dashboard for case generation that shows all 12 agents (Phase 1 + 9 Phase 2 + 2 Phase 3) with live status updates, typewriter-style output streaming via SSE, output chain visualization, PDF export, and case metrics. Implementation follows a **UI-first approach**: create the complete UI with static/simulated data, then update SKILL.md, then connect everything.

---

## Technical Context

**Language/Version**: PHP 8.4 / Laravel 11  
**Primary Dependencies**: Blade templates, Tailwind CSS (via CDN), mPDF for PDF generation  
**Real-time Transport**: Server-Sent Events (SSE) - native Laravel streaming responses  
**Storage**: PostgreSQL (existing), Redis for queue/cache  
**Testing**: PHPUnit, manual end-to-end validation  
**Target Platform**: Web (Docker containerized)  
**Project Type**: Web application (Laravel monolith with Blade views)  
**UI Framework**: Tailwind CSS with existing design system (primary: #006b34, Cairo font, RTL)

---

## Constitution Check

| Gate | Status | Notes |
|------|--------|-------|
| Docker Containerization | ✅ PASS | Already containerized, no changes needed |
| Agent Orchestration | ✅ PASS | Extends existing multi-agent system |
| Senior-Level Laravel | ✅ PASS | Following existing patterns |
| RTL Arabic Support | ✅ PASS | Existing Cairo font, RTL layout |
| Gate-by-file Validation | ✅ PASS | Already implemented in GateValidator |

---

## Implementation Approach

### User's Requested Sequence:
1. **Step 1: UI First** - Create complete dashboard UI with static/simulated data
2. **Step 2: SKILL.md Update** - Edit skill file to match the new portal-driven approach
3. **Step 3: Integration** - Connect UI to real data via SSE and backend events

---

## Phase 1: UI Implementation (Static Data)

### 1.1 Agent Timeline Component

Create a new Blade partial for the agent timeline with all 12 agents.

**File**: `resources/views/components/agent-timeline.blade.php`

**Structure**:
```
┌─────────────────────────────────────────────────────────────┐
│  📊 مراحل التحليل الذكي                    الخطوة 3 من 12  │
│  ════════════════════════════════════════════════════════   │
│  ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓░░░░░░░░░░░░░░░░░░░░░░░░░░  25%            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ✅ المرحلة الأولى: تحليل القضية              12.5 ثانية   │
│     └─ 00_required_laws.md                                  │
│                                                             │
│  ─────────── المرحلة الثانية: الوكلاء الـ9 ───────────       │
│                                                             │
│  ✅ 1. قائد القضية (Lead Counsel)             8.2 ثانية    │
│     └─ 01_lead_plan.md → يغذي الوكيل 2                     │
│                                                             │
│  ✅ 2. مدير الأدلة (Evidence)                 15.1 ثانية   │
│     └─ 02_chunks.jsonl → يغذي الوكيل 3                     │
│                                                             │
│  🔄 3. النزاهة والفهرسة (Indexing)            جارٍ...       │
│     ├─ أنت هنا ◀                                           │
│     └─ [الكتابة مباشرة...]                                 │
│                                                             │
│  ⏳ 4. الجدول الزمني (Timeline)               في الانتظار   │
│  ⏳ 5. مدير القانون (Law Lead)                في الانتظار   │
│  ⏳ 6. المطابقة النظامية (Matcher)            في الانتظار   │
│  ⏳ 7. فريق الدفاع (Defense)                  في الانتظار   │
│  ⏳ 8. صائغ المذكرة (Drafter)                 في الانتظار   │
│  ⏳ 9. المراجعة النهائية (Final)              في الانتظار   │
│                                                             │
│  ─────────── المرحلة الثالثة: التحكيم ───────────           │
│                                                             │
│  🔒 القاضي (Judge)                            مقفل         │
│  🔒 محامي الشيطان (Devil's Advocate)          مقفل         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Status Icons**:
- ✅ `مكتمل` (completed) - green background, checkmark
- 🔄 `جارٍ` (processing) - amber background, pulsing animation
- ⏳ `في الانتظار` (pending) - gray background
- ❌ `فشل` (failed) - red background, with retry button
- 🔒 `مقفل` (locked) - gray, Phase 3 only

### 1.2 Agent Output Panel Component

**File**: `resources/views/components/agent-output-panel.blade.php`

Expandable panel showing agent's streaming output with typewriter effect.

**Features**:
- Collapsible per agent
- Syntax highlighting for `CASE:xxx` and `LAW:xxx` references
- "عرض المزيد" for long outputs
- Copy button for output content

### 1.3 Output Chain Diagram Component

**File**: `resources/views/components/output-chain.blade.php`

Collapsible panel showing the full agent-to-agent data flow.

**Structure**:
```
┌─ سلسلة المخرجات ──────────────────────────────────────────┐
│                                                            │
│  intake.txt + docs/                                        │
│       ↓                                                    │
│  [Phase 1] → 00_required_laws.md                          │
│       ↓                                                    │
│  [Agent 1] → 01_lead_plan.md, 01_acceptance_criteria.json │
│       ↓                                                    │
│  [Agent 2] → 02_ingestion_report.md, 02_chunks.jsonl      │
│       ↓                                                    │
│  [Agent 3] → 03_chain_of_custody.jsonl, 03_statutes_index │
│       ↓                                                    │
│  ... (continues for all agents)                           │
│       ↓                                                    │
│  [Agent 9] → 09_final_brief_v2.md                         │
│       ↓                                                    │
│  📄 تصدير PDF                                              │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

### 1.4 Case Insights Panel Component

**File**: `resources/views/components/case-insights.blade.php`

Shows metrics after completion.

**Metrics**:
- إجمالي وقت المعالجة (Total processing time)
- عدد المواد المطابقة (Statutes matched)
- متوسط الثقة (Average confidence) - gauge visualization
- عدد التصحيحات (Corrections made)
- عناصر للمراجعة (Items flagged for review)

### 1.5 PDF Export Button Component

**File**: `resources/views/components/pdf-export-button.blade.php`

- Enabled only when case is completed
- Shows loading spinner during generation
- Disabled with tooltip when case not complete

### 1.6 Update Case Show Page

**File**: `resources/views/pages/cases/show.blade.php`

Replace the existing static "مراحل التحليل الذكي" section with the new components:

```blade
{{-- Agent Timeline with Live Status --}}
@include('components.agent-timeline', ['case' => $case])

{{-- Output Chain Diagram (collapsible) --}}
@include('components.output-chain', ['case' => $case])

{{-- Case Insights (shown when complete) --}}
@if($case->status === 'phase2_completed' || $case->status === 'phase3_completed')
    @include('components.case-insights', ['case' => $case])
@endif
```

### 1.7 Static Data for UI Development

Create a PHP array with sample agent data for UI development:

```php
$sampleAgents = [
    ['number' => 0, 'phase' => 1, 'name' => 'تحليل القضية', 'name_en' => 'Case Analysis', 'status' => 'completed', 'duration' => 12.5, 'outputs' => ['00_required_laws.md']],
    ['number' => 1, 'phase' => 2, 'name' => 'قائد القضية', 'name_en' => 'Lead Counsel', 'status' => 'completed', 'duration' => 8.2, 'outputs' => ['01_lead_plan.md', '01_acceptance_criteria.json']],
    ['number' => 2, 'phase' => 2, 'name' => 'مدير الأدلة', 'name_en' => 'Evidence', 'status' => 'completed', 'duration' => 15.1, 'outputs' => ['02_ingestion_report.md', '02_chunks.jsonl']],
    ['number' => 3, 'phase' => 2, 'name' => 'النزاهة والفهرسة', 'name_en' => 'Indexing', 'status' => 'processing', 'duration' => null, 'outputs' => ['03_chain_of_custody.jsonl', '03_statutes_index.jsonl']],
    ['number' => 4, 'phase' => 2, 'name' => 'الجدول الزمني', 'name_en' => 'Timeline', 'status' => 'pending', 'duration' => null, 'outputs' => ['04_timeline.json', '04_timeline.md']],
    ['number' => 5, 'phase' => 2, 'name' => 'مدير القانون', 'name_en' => 'Law Lead', 'status' => 'pending', 'duration' => null, 'outputs' => ['05_issues_to_statutes.md', '05_procedural_notes.md']],
    ['number' => 6, 'phase' => 2, 'name' => 'المطابقة النظامية', 'name_en' => 'Matcher', 'status' => 'pending', 'duration' => null, 'outputs' => ['06_statutes_map.jsonl', '06_accepted_matches.md']],
    ['number' => 7, 'phase' => 2, 'name' => 'فريق الدفاع', 'name_en' => 'Defense', 'status' => 'pending', 'duration' => null, 'outputs' => ['07_defense_skeleton.md']],
    ['number' => 8, 'phase' => 2, 'name' => 'صائغ المذكرة', 'name_en' => 'Drafter', 'status' => 'pending', 'duration' => null, 'outputs' => ['08_draft_brief.md']],
    ['number' => 9, 'phase' => 2, 'name' => 'المراجعة النهائية', 'name_en' => 'Final', 'status' => 'pending', 'duration' => null, 'outputs' => ['09_final_brief_v2.md']],
    ['number' => 10, 'phase' => 3, 'name' => 'القاضي', 'name_en' => 'Judge', 'status' => 'locked', 'duration' => null, 'outputs' => []],
    ['number' => 11, 'phase' => 3, 'name' => 'محامي الشيطان', 'name_en' => "Devil's Advocate", 'status' => 'locked', 'duration' => null, 'outputs' => []],
];
```

---

## Phase 2: SKILL.md Update

### 2.1 Add Portal Integration Section

Add a new section to SKILL.md explaining how agents interact with the portal:

```markdown
## 🌐 Portal Integration (Dashboard Visualization)

**Rule**: All agent outputs must be emitted as events for real-time dashboard visualization.

**Event Types**:
- `agent.started` - When an agent begins processing
- `agent.output` - Streaming output text (character-by-character)
- `agent.completed` - When an agent finishes with metrics
- `agent.failed` - When an agent fails (after retries)
- `agent.correction` - When self-correction loop activates

**Event Payload Structure**:
```json
{
  "case_id": "uuid",
  "agent_number": 3,
  "agent_name": "النزاهة والفهرسة",
  "event_type": "agent.output",
  "content": "فحص ملف نظام الإثبات...",
  "timestamp": "2026-03-16T12:00:00Z",
  "metrics": {
    "tokens_used": 1234,
    "confidence": 0.85,
    "duration_ms": 5000
  }
}
```

**Portal-Aware Rules**:
1. Every agent MUST emit `agent.started` before any processing
2. Output MUST be streamed incrementally (not buffered until end)
3. Every agent MUST emit `agent.completed` with final metrics
4. Gate failures MUST emit `agent.blocked` with missing file details
```

### 2.2 Update Agent Definitions

Ensure each agent definition in SKILL.md includes:
- Arabic display name (for dashboard)
- English identifier (for code)
- Expected output files (for chain visualization)
- Input dependencies (for gate-by-file)

---

## Phase 3: Backend Integration

### 3.1 SSE Endpoint

**File**: `app/Http/Controllers/CaseStreamController.php`

```php
public function stream(LegalCase $case)
{
    return response()->stream(function () use ($case) {
        while (true) {
            $events = Redis::lrange("case:{$case->id}:events", 0, -1);
            foreach ($events as $event) {
                echo "data: {$event}\n\n";
                ob_flush();
                flush();
            }
            Redis::del("case:{$case->id}:events");
            sleep(0.1); // 100ms polling
        }
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'Connection' => 'keep-alive',
    ]);
}
```

**Route**: `GET /cases/{case}/stream`

### 3.2 Agent Event Emission

Update `Phase2BaseAgent` to emit events during processing:

```php
protected function emitEvent(string $type, array $data): void
{
    $event = json_encode([
        'case_id' => $this->case->id,
        'agent_number' => $this->agentNumber,
        'agent_name' => $this->agentName,
        'event_type' => $type,
        'content' => $data['content'] ?? null,
        'timestamp' => now()->toISOString(),
        'metrics' => $data['metrics'] ?? null,
    ]);
    
    Redis::rpush("case:{$this->case->id}:events", $event);
}
```

### 3.3 Frontend JavaScript for SSE

```javascript
const eventSource = new EventSource(`/cases/${caseId}/stream`);

eventSource.onmessage = function(event) {
    const data = JSON.parse(event.data);
    
    switch (data.event_type) {
        case 'agent.started':
            updateAgentStatus(data.agent_number, 'processing');
            break;
        case 'agent.output':
            appendOutput(data.agent_number, data.content);
            break;
        case 'agent.completed':
            updateAgentStatus(data.agent_number, 'completed', data.metrics);
            break;
        case 'agent.failed':
            updateAgentStatus(data.agent_number, 'failed');
            showRetryButton(data.agent_number);
            break;
    }
};
```

### 3.4 PDF Export Service

**File**: `app/Services/PdfExportService.php`

Uses mPDF to generate Arabic RTL PDF from `09_final_brief_v2.md`.

---

## Phase 4: Testing & Validation

### 4.1 Manual UI Testing

1. Load case show page with static data
2. Verify all 12 agents display correctly
3. Verify status colors match spec
4. Verify output chain diagram renders
5. Verify PDF button is disabled when case not complete

### 4.2 SSE Integration Testing

1. Create test case
2. Start processing
3. Verify events stream to browser
4. Verify typewriter effect works
5. Verify status updates in real-time

### 4.3 End-to-End Validation

As specified in spec.md Implementation Validation section:
1. Case Creation → Phase 1 → Phase 2 (all 9 agents) → PDF export
2. All agent outputs verified
3. Dashboard shows correct state throughout

---

## Project Structure

```text
resources/views/
├── components/
│   ├── agent-timeline.blade.php      # New: Agent list with status
│   ├── agent-output-panel.blade.php  # New: Expandable output viewer
│   ├── output-chain.blade.php        # New: Flow diagram
│   ├── case-insights.blade.php       # New: Metrics panel
│   └── pdf-export-button.blade.php   # New: PDF download button
├── pages/cases/
│   └── show.blade.php                # Updated: Include new components

app/Http/Controllers/
└── CaseStreamController.php          # New: SSE endpoint

app/Services/
├── Agents/Phase2/
│   └── Phase2BaseAgent.php           # Updated: Event emission
└── PdfExportService.php              # New: PDF generation

.agent/skills/legal-counsel/
└── SKILL.md                          # Updated: Portal integration section

routes/
└── web.php                           # Updated: SSE route
```

---

## Implementation Order

| Step | Description | Deliverable |
|------|-------------|-------------|
| 1.1 | Create agent-timeline component | `agent-timeline.blade.php` |
| 1.2 | Create agent-output-panel component | `agent-output-panel.blade.php` |
| 1.3 | Create output-chain component | `output-chain.blade.php` |
| 1.4 | Create case-insights component | `case-insights.blade.php` |
| 1.5 | Create pdf-export-button component | `pdf-export-button.blade.php` |
| 1.6 | Update case show page | `show.blade.php` updated |
| 1.7 | Test UI with static data | Visual verification |
| 2.1 | Add portal section to SKILL.md | SKILL.md updated |
| 3.1 | Create SSE endpoint | `CaseStreamController.php` |
| 3.2 | Update agents to emit events | `Phase2BaseAgent.php` updated |
| 3.3 | Add frontend SSE listener | JavaScript in show.blade.php |
| 3.4 | Create PDF export service | `PdfExportService.php` |
| 4.1 | Manual UI testing | Test report |
| 4.2 | SSE integration testing | Test report |
| 4.3 | End-to-end validation | Full cycle verified |

---

## Artifacts Generated

- [x] `plan.md` - This file
- [ ] `research.md` - Not needed (no unknowns requiring research)
- [ ] `data-model.md` - Using existing models (AgentExecution, CaseOutput)
- [ ] `contracts/` - Not applicable (internal UI feature)
