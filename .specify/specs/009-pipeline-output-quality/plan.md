# Implementation Plan: Pipeline Output Quality Overhaul

**Branch**: `009-pipeline-output-quality` | **Date**: 2026-03-27 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/009-pipeline-output-quality/spec.md`

## Summary

Overhaul the 13-agent legal analysis pipeline to produce high-quality, pure Arabic legal briefs. The core changes are: (1) add system messages to all LLM calls with Arabic legal personas, (2) add RAG search to Agent 0, (3) enforce Arabic-only output by eliminating internal markers and adding deterministic PHP post-processing, (4) add chain-of-thought reasoning to key agents, (5) halt pipeline on critical agent failures, (6) increase context budgets, and (7) add a programmatic quality gate before case completion.

## Technical Context

**Language/Version**: PHP 8.x / Laravel 11
**Primary Dependencies**: Guzzle HTTP (OpenRouter/Puter API), Livewire, Alpine.js, Tailwind CSS
**Storage**: SQLite (dev) / MySQL (prod) — no schema changes needed
**Testing**: Manual end-to-end testing with real case data; PHPUnit for unit tests on new services
**Target Platform**: Web application (Laravel)
**Project Type**: Web service with AI pipeline
**Performance Goals**: Same or better pipeline processing time (within 10% of current)
**Constraints**: Backward compatible; both OpenRouter and Puter providers; SSE streaming preserved
**Scale/Scope**: 13 agents, ~15 files modified, 2 new service classes

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Real-Time First | PASS | SSE streaming preserved — `executeWithStreaming()` unchanged. System messages are added to the messages array, not the streaming mechanism. BriefPostProcessor runs synchronously after agent output, before SSE completion event. |
| II. Zero-Cache UI | PASS | No frontend asset changes. Output format changes are backend-only. |
| III. Self-Testing After Every Change | PASS | Each implementation group ends in a testable state. Quality gate provides built-in self-testing. |
| IV. Human-Readable Output Always | PASS | This feature's primary goal IS human-readable output — eliminating JSON/English artifacts from briefs. |
| V. Agent Logic Comes From SKILL.md | PASS | SKILL.md will be updated first (Agent 8 section: remove markers, enforce Arabic). All agent behavior changes derive from SKILL.md updates. System messages are extracted from SKILL.md agent sections. |
| VI. No New Pages | PASS | No new pages created. Quality gate results shown on existing `cases/show.blade.php`. |
| VII. General Development Standards | PASS | Simple solutions preferred. BriefPostProcessor is straightforward PHP string processing. Context cap changes are config values. |

**Post-Design Re-Check**: All principles still pass. No violations.

## Project Structure

### Documentation (this feature)

```text
specs/009-pipeline-output-quality/
├── plan.md              # This file
├── research.md          # Phase 0 output — 7 design decisions resolved
├── data-model.md        # Phase 1 output — entity/state documentation
├── quickstart.md        # Phase 1 output — testing guide
└── tasks.md             # Phase 2 output (created by /speckit.tasks)
```

### Source Code (repository root)

```text
app/
├── Services/
│   ├── Orchestration/
│   │   ├── PromptBuilder.php          # MODIFY: add buildSystemPrompt(), buildUserPrompt()
│   │   ├── OutputValidator.php        # MODIFY: add Arabic validators, update brief validation
│   │   └── LegalOrchestrator.php      # MODIFY: quality gate, critical agent halt logic
│   ├── Agents/
│   │   ├── Phase1AnalysisAgent.php    # MODIFY: add system message, add RAG search
│   │   ├── Phase2/
│   │   │   └── Phase2BaseAgent.php    # MODIFY: system + user message split
│   │   └── Phase3/
│   │       ├── JudgeAgent.php         # MODIFY: add system message
│   │       ├── DevilsAdvocateAgent.php # MODIFY: add system message
│   │       └── FortificationAgent.php  # MODIFY: add system message
│   └── Output/                         # NEW directory
│       ├── BriefPostProcessor.php      # NEW: deterministic PHP brief cleanup
│       └── FinalArabicBriefComposer.php # NEW: merge + compose final brief
├── Jobs/
│   ├── ProcessPhase2Job.php           # MODIFY: quality gate after Phase 2
│   └── ProcessPhase3Job.php           # MODIFY: quality gate after Phase 3
config/
└── legal.php                          # MODIFY: context budget constants

.agent/skills/legal-counsel/
└── SKILL.md                           # MODIFY: Agent 8 section (remove markers, pure Arabic)
```

**Structure Decision**: Standard Laravel service-layer pattern. New `Output/` namespace under Services for brief post-processing. No new models, migrations, or controllers.

---

## Design Artifacts

### DA-1: PromptBuilder Refactor

**Current**: `buildPromptForAgent(int $agentNumber, string $context): string` — returns a single concatenated string.

**New methods added**:

```
buildSystemPrompt(int $agentNumber): string
  — Returns: Agent persona + General Rules + Agent-specific section + Anti-Hallucination (if applicable) + Output Template
  — All in Arabic for agents producing Arabic output
  — Includes chain-of-thought instructions for agents 5, 7, 8

buildUserPrompt(int $agentNumber, string $context): string
  — Returns: Context boundary instruction + Case context
  — Just the data and task, no rules

buildPromptForAgent(int $agentNumber, string $context): string
  — KEPT for backward compatibility
  — Now internally calls: buildSystemPrompt() + "---" + buildUserPrompt()
  — Existing callers continue working unchanged
```

**System prompt structure per agent**:
```
[Arabic Agent Persona — 2-3 sentences defining role, expertise, writing style]
---
[General Rules — from SKILL.md, translated to Arabic for Arabic-output agents]
---
[Agent-Specific Section — from SKILL.md]
---
[Anti-Hallucination Rules — for agents 3, 5, 6, 7, 8, 9]
---
[Output Template — expected format]
---
[Chain-of-Thought Instructions — for agents 5, 7, 8 only]
```

**User prompt structure**:
```
[Context Boundary Instruction — for agents 5, 6, 7, 8, 9]
---
[Case Context — intake, documents, law library, prior outputs]
```

### DA-2: Agent Message Construction

**Phase2BaseAgent.executeWithStreaming() change**:

```
BEFORE:
  $messages = [['role' => 'user', 'content' => $prompt]];

AFTER:
  $systemPrompt = $this->promptBuilder->buildSystemPrompt($this->agentNumber());
  $userPrompt = $this->promptBuilder->buildUserPrompt($this->agentNumber(), $context);
  $messages = [
      ['role' => 'system', 'content' => $systemPrompt],
      ['role' => 'user', 'content' => $userPrompt],
  ];
```

**Phase1AnalysisAgent.execute() change**: Same pattern — construct system + user messages.

**Phase3 agents (Judge, DevilsAdvocate, Fortification)**: Same pattern — each agent's execute() method constructs system + user messages.

**Key**: `buildContext()` logic stays in the agents. Only the message construction and prompt builder calls change.

### DA-3: Agent 0 RAG Integration

**New dependency**: `VectorSearchService` injected into `Phase1AnalysisAgent` constructor.

**Flow change in execute()**:
```
1. Build base context (intake + documents) — existing logic
2. NEW: Extract keywords from intake text (split Arabic text on whitespace, filter legal terms)
3. NEW: Call VectorSearchService::searchMultiple(keywords, topK=15, minSimilarity=0.60)
4. NEW: Format RAG results as context section:
   "## المواد القانونية المرشحة من قاعدة المعرفة\n\n"
   For each result: "### {lawName} - المادة {articleNumber}\n{articleText}\n\n"
5. Append RAG context to base context
6. Build prompt and call LLM — existing logic
```

**Keyword extraction**: Simple approach — split intake text on whitespace, keep words > 3 chars, filter against the 23 predefined legal keywords in LawParserService. No need for NLP — the vector search handles semantic matching.

### DA-4: SKILL.md Agent 8 Changes

**Remove from Agent 8 section**:
- All references to `CASE:{chunk_ref}` markers
- All references to `LAW:{statute_ref}` markers
- The instruction to "mark unsupported paragraphs with ⚠️ غير مُسنَّدة"

**Add to Agent 8 section**:
```markdown
**قواعد الاستشهاد (Citation Rules)**:
- كل استشهاد بمادة نظامية يُكتب بالنثر العربي مباشرةً
  مثال: "وفقاً للمادة الثمانين من نظام العمل" — وليس LAW:LABOR_LAW_ART_80
- كل إشارة إلى واقعة من المستندات تُكتب بالعربية
  مثال: "كما هو ثابت في المستند المؤرخ في..." — وليس CASE:DOC01_C003
- لا تستخدم أي علامات مرجعية داخلية أو مفاتيح إنجليزية في المذكرة
- لا تذكر درجات الثقة أو أرقام تعريف المواد الداخلية
```

**Cascade effect on Agent 9**:
- Agent 9 no longer needs to perform "AI Erasure" (converting markers to prose)
- Agent 9's QA checklist updated: remove "dual citation format" check, add "Arabic-only content" check
- Agent 9 still performs: structural validation, legal coherence check, QA summary

**Cascade effect on Agent 12**:
- Agent 12 no longer needs AI Erasure pass
- Agent 12 focuses purely on fortification: strengthening arguments, adding counter-arguments

### DA-5: OutputValidator New Methods

**Add**:

```
validateArabicFinalBrief(string $brief): array
  — Check Arabic character ratio ≥ 95% (using mb_strlen and Arabic Unicode range)
  — Check no JSON blocks (regex: /```json/)
  — Check no internal markers (regex: /\b(CASE|LAW):[A-Z0-9_]+/i)
  — Check no technical English terms (regex: /\b(statute_id|chunk_id|confidence|match_type|abrogated)\b/)
  — Return violations array (empty = valid)

validateNoEnglishLeak(string $brief): array
  — Check for common English sentence patterns (3+ consecutive ASCII words)
  — Allow: proper nouns, single English words, abbreviations
  — Return violations array
```

**Modify**:

```
validateBriefCitations(string $brief, string $statutesMap): array
  — REMOVE the LAW:{ref} pattern check (markers no longer used)
  — ADD check: verify Arabic citation mentions match statute names in the index
  — Pattern: look for "المادة [number] من [law_name]" and verify the law exists
```

### DA-6: BriefPostProcessor Service

**Location**: `app/Services/Output/BriefPostProcessor.php`

**Public method**:
```
static function process(string $brief): string
```

**Operations (in order)**:
1. Strip remaining `CASE:{...}` markers → replace with empty string
2. Strip remaining `LAW:{...}` markers → replace with empty string
3. Remove confidence score patterns: `/confidence[:\s]*[\d.]+/i` → empty
4. Remove agent headers: lines starting with `## Agent` or `### Agent`
5. Remove `⚠️ غير مُسنَّدة` paragraphs (paragraph = text between double newlines)
6. Remove code fence blocks: ` ```json ... ``` ` → empty
7. Remove lines with only English technical terms
8. Ensure first non-empty line is "بسم الله الرحمن الرحيم" — if missing, prepend it
9. Normalize whitespace (collapse triple+ newlines to double)
10. Return cleaned string

**Integration points**:
- Called in `LegalOrchestrator` after Agent 9 saves `09_final_brief_v2.md`
- Called in `ProcessPhase3Job` after Agent 12 saves `13_final_brief_v3.md`
- The post-processed content overwrites the saved file and updates the DB record

### DA-7: FinalArabicBriefComposer Service

**Location**: `app/Services/Output/FinalArabicBriefComposer.php`

**Public method**:
```
static function compose(LegalCase $case): ?string
```

**Logic**:
1. Look for `13_final_brief_v3.md` output → if found and non-empty, use it
2. Else look for `09_final_brief_v2.md` → if found and non-empty, use it
3. Else look for `08_final_brief.md` → if found and non-empty, use it
4. If none found, return null
5. Apply BriefPostProcessor::process() to the selected brief
6. Return the final clean Arabic string

**Usage**: Called at the end of the pipeline to produce the definitive output. Can also be called on-demand when displaying the case to the user.

### DA-8: Quality Gate

**Location**: Two integration points.

**In LegalOrchestrator (after Phase 2 Agent 9)**:
```
After Agent 9 completes:
  1. Run BriefPostProcessor on 09_final_brief_v2.md
  2. Run quality gate checks:
     - OutputValidator::validateBriefStructure()
     - OutputValidator::validateArabicFinalBrief()
     - OutputValidator::validateNoEnglishLeak()
  3. If all pass → status remains Phase2Completed
  4. If any fail → log violations, set low_quality flag on case
```

**In ProcessPhase3Job (after Agent 12)**:
```
After Agent 12 completes:
  1. Run FinalArabicBriefComposer::compose()
  2. Run quality gate checks on composed brief
  3. If all pass → CaseStatus::Phase3Completed
  4. If any fail → CaseStatus::CompletedWithWarnings
```

### DA-9: Critical Agent Halt Logic

**In LegalOrchestrator Phase 2 loop**:

```
Current behavior (agents 1-9):
  On self_correction_exhausted → log warning, continue pipeline with best-effort

New behavior:
  For agents 1, 2, 3, 4, 5, 7 → keep current (continue with best-effort)
  For agents 6, 8, 9 (critical) → HALT pipeline:
    - Set case status to Halted
    - Emit pipeline.halted SSE event
    - Log the specific violations
    - Do NOT execute remaining agents
    - Return early from orchestrator
```

**Rationale**: Agents 6 (Statute Matcher), 8 (Legal Drafter), and 9 (QA) produce the core outputs that define the brief quality. Passing bad output from these agents is worse than stopping.

### DA-10: Agent 9 Fallback for Missing Marker

**In QualityAssuranceAgent**:

```
Current: Parses output for ---FINAL_BRIEF_V2--- section
  If not found → no brief_v2 saved

New: After parsing, if brief_v2 section not found:
  1. Check if 08_final_brief.md exists
  2. If yes → copy it as the base for brief_v2
  3. Apply any fixes from the 09_fixes_applied.json (if parseable)
  4. Run BriefPostProcessor::process() on the result
  5. Save as 09_final_brief_v2.md
```

### DA-11: Context Budget Changes

**In config/legal.php** — no changes (config doesn't store these).

**In Phase2BaseAgent.php**:
```
LAW_CONTEXT_MAX_CHARS: 50_000 → 100_000
PER_FILE_CAPS['03_statutes_index.jsonl']: 80_000 → 120_000
```

### DA-12: Chain-of-Thought Instructions

**Added to system prompts for agents 5, 7, 8** (via PromptBuilder):

```arabic
## مرحلة التفكير المنظم

قبل كتابة المخرج النهائي، فكّر بشكل منظم في النقاط التالية:

1. ما هي المسائل القانونية الجوهرية في هذه القضية؟
2. ما هي المواد النظامية الأكثر صلة وانطباقاً؟
3. ما هي نقاط القوة في موقف الموكل؟ وما هي نقاط الضعف؟
4. ما هي أقوى حجة يمكن أن يقدمها الخصم؟ وكيف نرد عليها؟
5. هل توجد ثغرات في الأدلة أو التسلسل الزمني؟

ابدأ إجابتك بتحليل موجز لهذه النقاط، ثم انتقل إلى المخرج المطلوب.
```

This appears at the end of the system prompt for these agents, before the output template.

---

## Implementation Order

### Phase A: Core Quality Infrastructure (Immediate)

| Step | Change | Depends On | Files |
|------|--------|-----------|-------|
| A1 | PromptBuilder: add `buildSystemPrompt()` and `buildUserPrompt()` | None | `PromptBuilder.php` |
| A2 | Write Arabic system prompts for all 13 agents | A1 | `PromptBuilder.php` |
| A3 | Phase2BaseAgent: use system + user messages | A1 | `Phase2BaseAgent.php` |
| A4 | Phase1AnalysisAgent: use system + user messages | A1 | `Phase1AnalysisAgent.php` |
| A5 | Phase3 agents: use system + user messages | A1 | `JudgeAgent.php`, `DevilsAdvocateAgent.php`, `FortificationAgent.php` |
| A6 | Phase1AnalysisAgent: add RAG search | A4 | `Phase1AnalysisAgent.php` |
| A7 | Update SKILL.md Agent 8: remove markers, enforce Arabic | None | `SKILL.md` |
| A8 | Update PromptBuilder Agent 8 template: remove markers | A7 | `PromptBuilder.php` |
| A9 | Build BriefPostProcessor service | None | New: `BriefPostProcessor.php` |
| A10 | Build FinalArabicBriefComposer service | A9 | New: `FinalArabicBriefComposer.php` |
| A11 | OutputValidator: add Arabic validators, update brief validation | None | `OutputValidator.php` |
| A12 | Integrate BriefPostProcessor after Agent 9 and Agent 12 | A9 | `LegalOrchestrator.php`, `ProcessPhase3Job.php` |
| A13 | Quality gate in orchestrator and Phase 3 job | A11, A12 | `LegalOrchestrator.php`, `ProcessPhase3Job.php` |
| A14 | Critical agent halt logic (6, 8, 9) | None | `LegalOrchestrator.php` |
| A15 | Agent 9 fallback for missing FINAL_BRIEF_V2 marker | A9 | `QualityAssuranceAgent.php` |
| A16 | Context budget increase | None | `Phase2BaseAgent.php` |
| A17 | Chain-of-thought instructions for agents 5, 7, 8 | A1 | `PromptBuilder.php` |

### Phase B: RAG & Advanced Quality (1-2 Weeks)

| Step | Change | Depends On | Files |
|------|--------|-----------|-------|
| B1 | Expand law library (15-20 Saudi laws) | None | `database/seeders/`, `storage/laws/` |
| B2 | Evaluate multilingual embedding model | B1 | `EmbeddingService.php`, `config/openrouter.php` |
| B3 | Implement search cache | None | `VectorSearchService.php` |
| B4 | Update Agent 9 SKILL.md section (QA without marker validation) | A7 | `SKILL.md` |
| B5 | Update Agent 12 SKILL.md section (fortification without erasure) | A7 | `SKILL.md` |
| B6 | End-to-end testing with expanded law library | B1, A1-A17 | Testing only |

## Complexity Tracking

No constitution violations. All changes use simple, maintainable patterns:
- System messages: Array prepend, no new abstractions
- Post-processing: Straightforward PHP string operations
- Quality gate: Static validator methods (existing pattern)
- Context budget: Constant value changes
