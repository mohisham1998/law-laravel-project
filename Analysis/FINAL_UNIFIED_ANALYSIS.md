# Saudi Legal Orchestrator — Final Unified Analysis & Implementation Plan

**Date**: 2026-03-27
**Source**: Synthesized from 3 parallel deep investigations (Claude, Codex, MiniMax)
**Branch**: `008-puter-provider-switch`
**Feature ID**: `009-pipeline-output-quality`

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Current System Architecture](#2-current-system-architecture)
3. [How Case Analysis Works](#3-how-case-analysis-works)
4. [How RAG Integrates with Agents](#4-how-rag-integrates-with-agents)
5. [Root Cause Analysis — Why Outputs Are Poor](#5-root-cause-analysis)
6. [Per-Approach Verdict (Approve / Improve / Reject)](#6-per-approach-verdict)
7. [Improvement Plan — 4 Phases](#7-improvement-plan)
8. [New Tools & Services to Build](#8-new-tools--services-to-build)
9. [Revised SKILL.md Agent Prompts (Summary)](#9-revised-skillmd-agent-prompts)
10. [Spec-Kit Prompts for Implementation](#10-spec-kit-prompts)

---

## 1. Executive Summary

Three independent investigations (Claude Opus, Codex, MiniMax) analyzed every agent class, the RAG pipeline, prompt construction, output validation, and display layer. **All three converge on the same core problems.** Below is the unified root-cause table, ordered by severity, incorporating unique findings from each analysis:

| # | Root Cause | Severity | Source | Impact |
|---|-----------|----------|--------|--------|
| 1 | **No system message** — entire prompt sent as single `user` message | CRITICAL | All 3 | LLM has no persistent legal identity; weak behavioral anchoring |
| 2 | **Bloated single-message prompt** — rules + templates + context concatenated | CRITICAL | All 3 | "Lost in the middle" problem; case data buried under 2000+ words of rules |
| 3 | **Agent 0 has NO RAG search** — identifies laws from LLM knowledge only | CRITICAL | Codex, MiniMax | Wrong laws identified at start → entire pipeline produces wrong analysis |
| 4 | **Only 4 laws seeded** in RAG database | HIGH | All 3 | Most Saudi legal domains missing (labor, commercial, civil, criminal substantive) |
| 5 | **English/JSON artifacts leak into final brief** — CASE/LAW markers, confidence scores, English keys | HIGH | All 3 | Output is not pure Arabic legal prose |
| 6 | **AI Erasure (Agent 9) is unreliable** — LLM doing string replacement fails often | HIGH | Claude, Codex | No deterministic post-processing fallback |
| 7 | **No Arabic-only enforcement in OutputValidator** | HIGH | Codex | Validator checks structure and citations but never checks for English text leakage |
| 8 | **`text-embedding-3-small` is English-optimized** — poor Arabic embeddings | HIGH | Claude, MiniMax | RAG retrieval misses relevant Arabic legal articles |
| 9 | **No structured thinking/reasoning step** — agents jump to output immediately | MEDIUM | Claude, MiniMax | Shallow legal analysis, weak syllogisms, formulaic output |
| 10 | **Context truncation hides critical data** — 50K law library cap, 80K statute index cap | MEDIUM | All 3 | Agents work with incomplete legal material in complex cases |
| 11 | **No deterministic final output composer** — brief assembly depends on LLM markers | MEDIUM | Codex | If Agent 9/12 miss section markers, output may be lost or corrupted |
| 12 | **Self-correction exhaustion produces best-effort output** — pipeline continues with violations | MEDIUM | Codex | Critical agents (6, 8, 9) may pass low-quality output downstream |
| 13 | **Agent 3 (statute index) generates article text via LLM** instead of copying from DB | MEDIUM | Claude | Source of truth contains LLM-hallucinated legal text instead of verbatim law |
| 14 | **No legal reasoning tools** — no programmatic dispute tree, contradiction detection, or procedure checks | MEDIUM | Codex | LLM must reason about everything with no guardrails |

---

## 2. Current System Architecture

### Pipeline Structure (13 Agents, 3 Phases)

```
Phase 1 — Case Intake (1 agent):
  Agent 0: تحليل المرحلة الأولى — reads intake + docs, identifies required laws

Phase 2 — Legal Analysis (9 agents, sequential):
  Agent 1: القائد القانوني — strategic plan + acceptance criteria
  Agent 2: مدير الأدلة — chunk documents into JSONL
  Agent 3: سلسلة الحفظ — build statute index from RAG [SOURCE OF TRUTH]
  Agent 4: الجدول الزمني — extract chronological events
  Agent 5: مدير القانون — map legal issues to statutes + adversary analysis
  Agent 6: مطابق الأنظمة — match evidence chunks to statute articles
  Agent 7: الاستراتيجي — risk matrix + 3-tier defense strategy
  Agent 8: الصائغ القانوني — draft complete 8-section legal brief
  Agent 9: ضبط الجودة — 10-point QA + AI erasure → brief_v2

Phase 3 — Judicial Arbitration (3 agents, sequential):
  Agent 10: القاضي — critique from Saudi judge perspective
  Agent 11: محامي الخصم — attack from opponent perspective
  Agent 12: وكيل التحصين — fortify brief → brief_v3 (final)
```

### LLM Call Structure (Current — Problematic)

```php
// Phase2BaseAgent::executeWithStreaming() line 80
$messages = [['role' => 'user', 'content' => $prompt]];
// ↑ ONE user message containing: General Rules + Agent Section + Anti-Hallucination
//   + Output Template + Context Boundary + Case Context (up to 240K chars)
```

### Key Files

| Component | Path |
|-----------|------|
| SKILL.md (all agent prompts) | `.agent/skills/legal-counsel/SKILL.md` |
| PromptBuilder | `app/Services/Orchestration/PromptBuilder.php` |
| Phase2BaseAgent | `app/Services/Agents/Phase2/Phase2BaseAgent.php` |
| OutputValidator | `app/Services/Orchestration/OutputValidator.php` |
| LegalOrchestrator | `app/Services/Orchestration/LegalOrchestrator.php` |
| VectorSearchService | `app/Services/RAG/VectorSearchService.php` |
| EmbeddingService | `app/Services/RAG/EmbeddingService.php` |
| Config | `config/legal.php`, `config/openrouter.php` |
| Phase 1 Agent | `app/Services/Agents/Phase1AnalysisAgent.php` |
| Phase 3 Agents | `app/Services/Agents/Phase3/{Judge,DevilsAdvocate,Fortification}Agent.php` |

---

## 3. How Case Analysis Works

### Full Lifecycle

1. **User creates case** → intake text + documents uploaded → `ProcessPhase1Job` dispatched
2. **Phase 1 (Agent 0)** → reads intake + docs (50KB/doc cap) → LLM identifies required laws → parses JSON from output via regex → creates `RequiredLaw` records → saves `00_required_laws.md` → status = `awaiting_laws`
3. **Phase 2 (Agents 1-9)** → `LegalOrchestrator` runs each agent sequentially → per agent: gate validation → build context (intake + docs + law library + prior outputs, 240K char budget) → build prompt via `PromptBuilder` → LLM call with streaming → parse multi-section output (split on `---SECTION_NAME---` markers) → validate (OutputValidator) → self-correction loop (max 3 attempts) → save outputs to disk + DB → emit SSE events
4. **Phase 3 (Agents 10-12)** → judge review → opponent attack → fortification → `13_final_brief_v3.md`

### Data Dependencies

```
Agent 0 → 00_required_laws.md
Agent 1 → 01_lead_plan.md, 01_acceptance_criteria.json (uses: intake, docs)
Agent 2 → 02_chunks.jsonl, 02_ingestion_report.md (uses: lead_plan only)
Agent 3 → 03_statutes_index.jsonl [SOURCE OF TRUTH], 03_conflict_warnings.md (uses: chunks, RAG)
Agent 4 → 04_timeline.json, 04_timeline.md (uses: chunks only)
Agent 5 → 05_issues_to_statutes.md, 05_matching_guidelines.json (uses: chunks, statutes_index, timeline, RAG)
Agent 6 → 06_statutes_map.jsonl (uses: chunks, statutes_index, matching_guidelines, conflict_warnings, RAG)
Agent 7 → 07_defense_layers.md, 07_risk_matrix.md (uses: lead_plan, timeline, procedural_notes, statutes_map)
Agent 8 → 08_final_brief.md (uses: 10 prior outputs — NO docs, NO law library)
Agent 9 → 09_final_brief_v2.md (uses: 6 prior outputs — QA + AI erasure)
Agent 10 → 10_judge_notes.md (uses: 5 prior outputs)
Agent 11 → 11_devils_advocate_notes.md (uses: 6 prior outputs)
Agent 12 → 13_final_brief_v3.md (uses: 6 prior outputs)
```

---

## 4. How RAG Integrates with Agents

### RAG Pipeline

```
Law Text Files → LawParserService (10 regex patterns) → LawArticle records
                                                            ↓
                  EmbeddingService (text-embedding-3-small via OpenRouter) → LawEmbedding (binary blob, 1536-dim)
                                                            ↓
Query → EmbeddingService → Query Vector → VectorSearchService (cosine similarity in PHP, O(n) linear) → Top-K
```

### RAG Usage Per Agent

| Agent | Uses RAG? | Method | Top-K | Min Sim | Critical Note |
|-------|-----------|--------|-------|---------|---------------|
| Agent 0 | **NO** | — | — | — | **CRITICAL GAP**: Identifies laws without any RAG search |
| Agent 1-2, 4, 7 | No direct RAG | Law library from DB | — | — | Uses `buildLawContextFromLibrary()` (50K cap) |
| Agent 3 | Yes | `searchMultiple()` | 20 | 0.60 | Builds the statute index (source of truth for all downstream) |
| Agent 5 | Yes | `searchMultiple()` | 10/query | 0.70 | Maps issues to statutes |
| Agent 6 | Yes | `searchMultiple()` | 15 | 0.70 | Matches chunks to articles |

### Current Law Library (Only 4 Laws!)

1. نظام الإثبات (Evidence Law) — 1443H
2. نظام المرافعات الشرعية (Sharia Court Procedures) — 1435H
3. اللائحة التنفيذية لنظام الإجراءات الجزائية (Criminal Procedures Regs) — 1435H
4. اللوائح التنفيذية لنظام المرافعات الشرعية (Sharia Procedures Regs) — 1435H

**Missing**: نظام العمل, نظام المحكمة التجارية, نظام المعاملات المدنية, نظام الأحوال الشخصية, نظام الشركات, النظام الجزائي, and 10+ more commonly used laws.

---

## 5. Root Cause Analysis

### Why Outputs Are Poor — Detailed Explanation

#### RC-1: No System Message (CRITICAL)

**Location**: `Phase2BaseAgent.php:80`, `Phase1AnalysisAgent.php:68`, `Phase3/*Agent.php`

Every LLM call sends one `user` message containing everything. The LLM has no persistent identity — it processes "General Rules + Agent Section + Anti-Hallucination + Template + Context" as a user request, not as its own expertise.

**Effect**: Output reads like a generic AI response. No consistent legal voice. Rules in the prompt get ignored when context is large (>100K chars).

#### RC-2: Bloated Single Prompt (CRITICAL)

**Location**: `PromptBuilder.php:205-247`

6 sections concatenated with `---` separators into one string:
- General Rules (~1500 chars)
- Agent Section (~800 chars)
- Anti-Hallucination (~1200 chars)
- Output Template (~600-1200 chars)
- Context Boundary (~200 chars)
- Case Context (up to 240K chars)

**Effect**: "Lost in the middle" — the LLM attends more to the beginning (General Rules) and end (case context tail) than to the agent-specific instructions in the middle. For agents with 240K context, instructions are <2% of the prompt.

#### RC-3: Agent 0 Has No RAG (CRITICAL)

**Location**: `Phase1AnalysisAgent.php:116` — `buildContext()` only includes intake + docs, no VectorSearchService call.

Phase 1 identifies required laws purely from LLM knowledge. If the LLM doesn't know a Saudi law or gets the name wrong, the entire pipeline works with wrong/missing laws. The fallback is نظام الإثبات (Evidence Law) — completely generic.

**Effect**: Wrong laws at start → every downstream agent produces wrong analysis.

#### RC-4: English/JSON Artifact Leakage (HIGH)

Agent 8 writes with `CASE:{chunk_ref}` and `LAW:{statute_ref}` markers. Agent 9 should transform these to Arabic prose. But this "AI Erasure" is unreliable — it's an LLM doing string replacement, which it often botches:
- Misses some markers
- Leaves confidence scores
- Keeps English field names
- Produces mixed Arabic/English text

Agent 12 does another erasure pass, compounding errors.

#### RC-5: No Arabic-Only Validator (HIGH — from Codex)

`OutputValidator.php` checks: JSONL format, statute IDs, quoted text, abrogation, confidence, brief structure, brief citations. But it **never checks for English text leakage** in the final brief. There is no `validateArabicOnly()` or `validateNoEnglishLeak()` method.

**Effect**: Even when the brief passes all existing validation, it may still contain English technical terms.

#### RC-6: No Deterministic Final Composer (MEDIUM — from Codex)

The final brief assembly depends on LLM-generated section markers (`---FINAL_BRIEF_V2---`, `---FINAL_BRIEF_V3---`). If an agent fails to produce the marker, the output may be lost or contain raw intermediate data.

**Effect**: Inconsistent output format. Sometimes `09_final_brief_v2.md` is empty or malformed.

#### RC-7: No Legal Reasoning Tools (MEDIUM — from Codex)

The LLM must reason about everything: legal issues, procedure, contradictions, burden of proof, remedies. No programmatic tools assist it. This is like asking a lawyer to write a brief without access to a case management system.

**Effect**: Generic reasoning, missed procedural issues, undetected contradictions.

---

## 6. Per-Approach Verdict

| # | Current Approach | Verdict | Key Issue | Required Action |
|---|-----------------|---------|-----------|----------------|
| 6.1 | Agent Orchestration (sequential 1→12) | APPROVE WITH IMPROVEMENTS | Needs quality gate before Agent 8; needs targeted replay not full-phase retry | Add Legal Reasoning Synthesizer before Agent 8; add per-agent retry |
| 6.2 | PromptBuilder (single user message) | **NEEDS MAJOR REWORK** | No system message; bloated prompt | Split into system + user messages; per-agent Arabic persona |
| 6.3 | RAG in Agents 3/5/6 | APPROVE | Good integration with clear thresholds | Add re-ranker; add metadata filtering |
| 6.4 | RAG in Agent 0 | **REJECT** | No RAG search at all | Add VectorSearchService before law identification |
| 6.5 | OutputValidator | APPROVE WITH CRITICAL GAP | No Arabic-only enforcement | Add `validateArabicFinalBrief()`, `validateNoEnglishLeak()` |
| 6.6 | Self-Correction (max 3) | NEEDS IMPROVEMENT | Best-effort on exhaustion for critical agents | Halt on critical agents (6, 8, 9) when exhausted; don't pass bad output |
| 6.7 | Context Budget (240K chars) | NEEDS IMPROVEMENT | Fixed caps may cut essential material | Dynamic budget per case complexity; raise law library cap to 100K |
| 6.8 | AI Erasure (LLM-based) | **REJECT** | Unreliable string replacement | Replace with PHP `BriefPostProcessor`; or eliminate markers entirely |
| 6.9 | UI Rendering | APPROVE (partially) | Filters JSON from display (good) | Add final brief as primary view; hide intermediate outputs |
| 6.10 | Agent 2 (LLM chunking) | NEEDS IMPROVEMENT | LLM-based chunking is wasteful and unreliable | Make programmatic (PHP DocumentChunker) |
| 6.11 | Agent 3 (LLM statute index) | NEEDS IMPROVEMENT | LLM generates article text instead of copying from DB | Hybrid: LLM filters/queries, PHP copies verbatim from DB |

---

## 7. Improvement Plan — 4 Phases

### Phase A: Critical Fixes (Immediate — High Impact, Low-Medium Effort)

| ID | Change | Files | Impact | Effort |
|----|--------|-------|--------|--------|
| A1 | **Add system messages** — Split prompt into system (persona + rules) and user (case data + task) | `Phase2BaseAgent.php`, `Phase1AnalysisAgent.php`, `Phase3/*Agent.php`, `PromptBuilder.php`, `LLMServiceInterface.php`, `OpenRouterService.php`, `PuterService.php` | CRITICAL | Medium |
| A2 | **Arabic-first system prompts** — Strong Arabic legal persona per agent | `PromptBuilder.php`, `SKILL.md` | HIGH | Low |
| A3 | **Add RAG search to Agent 0** — VectorSearchService before law identification | `Phase1AnalysisAgent.php` | CRITICAL | Low |
| A4 | **Add Arabic-only validator** — `validateArabicFinalBrief()`, `validateNoEnglishLeak()` in OutputValidator | `OutputValidator.php` | HIGH | Low |
| A5 | **Eliminate CASE/LAW markers** — Agent 8 writes pure Arabic citations directly | `PromptBuilder.php` (Agent 8 template), `SKILL.md` | HIGH | Low |
| A6 | **PHP BriefPostProcessor** — Deterministic final cleanup (strip English, format citations, remove metadata) | New: `app/Services/Output/BriefPostProcessor.php` | HIGH | Medium |
| A7 | **Agent 9 fallback** — If `FINAL_BRIEF_V2` marker missing, build from brief_v1 + fixes | `QualityAssuranceAgent.php` | MEDIUM | Low |

### Phase B: RAG & Quality Improvements (1-2 Weeks)

| ID | Change | Files | Impact | Effort |
|----|--------|-------|--------|--------|
| B1 | **Expand law library** — Add 15-20 major Saudi laws (نظام العمل, التجارية, المعاملات, الأحوال الشخصية, الشركات, الجزائي, etc.) | `database/seeders/`, `storage/laws/` | CRITICAL | Medium |
| B2 | **Switch embedding model** — Use multilingual model (e.g., `text-embedding-3-large`, or `BAAI/bge-m3`, or `Cohere/embed-multilingual-v3.0`) | `EmbeddingService.php`, `config/openrouter.php` | HIGH | Medium |
| B3 | **Programmatic statute index** — Agent 3: LLM generates queries + filters, PHP copies article text verbatim from DB | `ChainOfCustodyAgent.php` | HIGH | Medium |
| B4 | **Raise context caps** — Law library 50K→100K, statute index 80K→120K | `Phase2BaseAgent.php` | MEDIUM | Low |
| B5 | **Implement search cache** — Use existing `law_search_cache` table | `VectorSearchService.php` | MEDIUM | Low |
| B6 | **Add chain-of-thought** — Structured thinking instructions for Agents 5, 7, 8 | `PromptBuilder.php` | MEDIUM | Low |
| B7 | **Strict self-correction for critical agents** — Halt pipeline (not best-effort) when Agents 6, 8, 9 exhaust retries | `LegalOrchestrator.php`, `Phase2BaseAgent.php` | MEDIUM | Low |
| B8 | **FinalArabicBriefComposer service** — Merges Agent 8/9/12 outputs, strips all artifacts, ensures pure Arabic | New: `app/Services/Output/FinalArabicBriefComposer.php` | HIGH | Medium |

### Phase C: Architecture Improvements (2-4 Weeks)

| ID | Change | Files | Impact | Effort |
|----|--------|-------|--------|--------|
| C1 | **Programmatic document chunking** — Replace Agent 2 with PHP `DocumentChunker` service | New: `app/Services/DocumentChunker.php` | MEDIUM | Medium |
| C2 | **Dynamic prompts by case type** — Adapt system messages based on legal domain (labor, commercial, criminal, etc.) | `PromptBuilder.php`, `LegalOrchestrator.php` | MEDIUM | Medium |
| C3 | **Hierarchical RAG search** — Broad (all laws, 0.60) → Medium (relevant laws, 0.70) → Specific (target articles, 0.80) | `VectorSearchService.php` | MEDIUM | Medium |
| C4 | **Legal reasoning tools** — Programmatic engines integrated into pipeline | See Section 8 | HIGH | High |
| C5 | **"No-final-output-unless-pass" policy** — Quality gate before any brief is published | `LegalOrchestrator.php`, `QualityAssuranceAgent.php` | HIGH | Medium |
| C6 | **Display only final brief to user** — Hide intermediate outputs behind "advanced view" toggle | `agent-timeline-live.blade.php`, `show.blade.php` | MEDIUM | Low |
| C7 | **Two-pass drafting for Agent 8** — (a) outline with key arguments, (b) full prose draft | `LegalDrafterAgent.php`, `PromptBuilder.php` | MEDIUM | Medium |

### Phase D: Advanced (Long-term)

| ID | Change | Impact |
|----|--------|--------|
| D1 | **Vector database** — Migrate to pgvector or Qdrant for scalable search | Scalable to 100K+ articles |
| D2 | **Cross-encoder re-ranking** — Re-rank RAG results with fine-tuned Arabic model | Better retrieval precision |
| D3 | **Arabic Refinement Agent (Agent 13)** — Dedicated Arabic language polishing agent | Guaranteed Arabic prose quality |
| D4 | **Arabic Legal Knowledge Graph** — Structured relationships between laws, articles, and legal concepts | Deep legal reasoning |
| D5 | **Multi-turn agent conversations** — Agents can ask clarifying questions | Deeper analysis |
| D6 | **Parallel agent execution** — Run independent agents (1, 2, 4) concurrently | Faster pipeline |
| D7 | **Metadata-enriched RAG** — Filter by law category, chapter, effective date, abrogation status | More precise retrieval |

---

## 8. New Tools & Services to Build

### 8.1 BriefPostProcessor (Phase A — Required)

**Purpose**: Deterministic PHP cleanup of any brief before it's saved as final output.

**Operations**:
1. Strip any remaining `CASE:{ref}` and `LAW:{ref}` markers → replace with Arabic references using a lookup table
2. Remove all confidence scores (regex: `/confidence[:\s]+[\d.]+/i`)
3. Remove all agent headers, metadata, and internal comments
4. Remove any paragraph still marked `⚠️ غير مُسنَّدة`
5. Strip any remaining English text (check Arabic character ratio ≥ 95%)
6. Ensure بسم الله الرحمن الرحيم is first line
7. Validate 8-section structure

**Location**: `app/Services/Output/BriefPostProcessor.php`

### 8.2 FinalArabicBriefComposer (Phase B — Required)

**Purpose**: Merge outputs from Agents 8, 9, and 12 into a single clean Arabic document.

**Operations**:
1. Take the latest available brief version (v3 > v2 > v1)
2. Apply BriefPostProcessor
3. Convert any remaining JSON blocks to Arabic prose tables
4. Unify writing style to formal Saudi legal Arabic
5. Final output is the ONLY thing displayed to the user

**Location**: `app/Services/Output/FinalArabicBriefComposer.php`

### 8.3 Arabic Output Validators (Phase A — Required)

**New methods in OutputValidator**:

```
validateArabicFinalBrief(string $brief): array
  - Check Arabic character ratio ≥ 95%
  - No JSON blocks in final text
  - No English sentences (allow single English proper nouns)
  - No technical field names (statute_id, chunk_id, confidence, etc.)

validateNoEnglishLeak(string $brief): array
  - Scan for common English terms that indicate leakage
  - Pattern: /\b(statute_id|chunk_id|confidence|match_type|abrogated|source_refs)\b/
  - Pattern: /\b(CASE|LAW|ARG|CLM|EVT|DOC)\d*[_:]/
```

### 8.4 Legal Reasoning Tools (Phase C — from Codex)

**Tool 1: Legal Issue Tree Builder**
- Input: Case intake + timeline + statute index
- Output: Structured dispute tree: Facts → Legal Issues → Applicable Articles → Defense Arguments
- Used by: Agents 5, 7, 8

**Tool 2: Citation Integrity Engine**
- Input: Final brief + statute index + chunks
- Output: Every LAW/CASE reference verified, with pass/fail per citation
- Used by: Agent 9, BriefPostProcessor

**Tool 3: Procedural Gate Engine**
- Input: Case metadata + timeline
- Output: Checks jurisdiction, standing, limitation period, res judicata
- Used by: Agent 5 (before substantive analysis)

**Tool 4: Contradiction Detector**
- Input: Timeline + evidence chunks + defense layers
- Output: Internal contradictions flagged
- Used by: Agent 9 (QA), Agent 11 (Devil's Advocate)

**Tool 5: Confidence Arbitration Layer** (from Codex)
- When confidence is below threshold on a critical point (Agents 6, 8, 9):
  - Don't continue with best-effort
  - Re-route through an alternative reasoning path
  - Generate targeted RAG search with reformulated query
  - Retry with enriched context

---

## 9. Revised SKILL.md Agent Prompts (Summary)

### Approach: System + User Message Split

**System Message** (per agent) contains:
- Agent identity/persona in Arabic
- Core rules (Arabic language, citation format)
- Agent-specific behavior instructions
- Anti-hallucination rules (for relevant agents)
- Output format specification

**User Message** contains:
- Case context (intake, documents, law library, prior outputs)
- Specific task instruction

### Example System Messages

**Agent 0**: "أنت محلل قانوني سعودي في مكتب محاماة رائد. تحلل القضايا وتحدد الأنظمة المعمول بها في المملكة العربية السعودية. تستعين بقاعدة المعرفة القانونية (RAG) لتحديد المواد النظامية ذات الصلة. مخرجاتك بالعربية الفصحى القانونية حصراً."

**Agent 1**: "أنت المستشار القانوني الأول المسؤول عن الخطة الاستراتيجية. تضع خارطة التحليل وتحدد أولويات الدفاع ومعايير القبول. تكتب بأسلوب قانوني سعودي رفيع."

**Agent 8**: "أنت صائغ قانوني محترف في مكتب محاماة سعودي مرموق. تكتب مذكرات قانونية تُقدَّم مباشرةً للمحاكم السعودية. أسلوبك رسمي، دقيق، ومبني على الأنظمة السعودية والشريعة الإسلامية. لا تستخدم أي مصطلحات إنجليزية أو تقنية أو علامات مرجعية داخلية. كل استشهاد يُكتب بالنثر العربي مباشرةً (مثال: وفقاً للمادة الثمانين من نظام العمل)."

**Agent 10**: "أنت قاضٍ سعودي خبير في الدائرة المختصة. تراجع المذكرات المقدمة بعين ناقدة. تكتب ملاحظاتك بضمير المتكلم كقاضٍ يجلس على منصة الحكم."

**Agent 11**: "أنت المحامي الذي يمثل الطرف المقابل. مهمتك إيجاد كل ثغرة ممكنة في مذكرة الخصم ومهاجمتها. تكتب من منظور المحامي المنافس."

**Agent 12**: "أنت خبير التحصين القانوني. تأخذ ملاحظات القاضي وهجمات الخصم وتحصّن المذكرة النهائية. مخرجك هو المذكرة الجاهزة للمحكمة — عربية قانونية خالصة بدون أي أثر تقني."

---

## 10. Spec-Kit Prompts for Implementation

### 10.1 Specify Prompt

```
/speckit.specify

Feature: Pipeline Output Quality Overhaul (009-pipeline-output-quality)

The Saudi Legal Orchestrator produces case analysis briefs that suffer from
poor quality: shallow legal reasoning, mixed Arabic/English output, JSON
artifacts in final text, and generic formulaic analysis. This feature overhaul
addresses 14 root causes identified across 3 independent deep investigations.

Core changes:

1. SYSTEM MESSAGE ARCHITECTURE — Split the current single user-message prompt
   into system message (Arabic legal persona + rules + behavior) and user message
   (case data + task). Every agent (0-12) gets a dedicated Arabic system message
   defining its role as a Saudi legal expert. Refactor PromptBuilder to expose
   buildSystemPrompt(agentNumber) and buildUserPrompt(agentNumber, context).
   Refactor Phase2BaseAgent, Phase1AnalysisAgent, and all Phase3 agents to send
   messages as [system, user] instead of [user].

2. RAG IN AGENT 0 — Add VectorSearchService call to Phase1AnalysisAgent before
   law identification. Agent 0 should search the RAG database with extracted
   keywords from intake text, then include relevant statute candidates in context
   before asking the LLM to identify required laws.

3. ARABIC-ONLY OUTPUT ENFORCEMENT — Eliminate CASE:{ref} and LAW:{ref} markers
   from Agent 8's prompt. Instruct Agent 8 to write pure Arabic prose citations
   directly (e.g., "وفقاً للمادة الثمانين من نظام العمل"). Add
   validateArabicFinalBrief() and validateNoEnglishLeak() methods to
   OutputValidator. Build a PHP BriefPostProcessor service for deterministic
   cleanup of any remaining English/JSON artifacts.

4. BRIEF POST-PROCESSING — Build BriefPostProcessor (app/Services/Output/) that
   performs deterministic PHP cleanup: strip remaining markers, remove confidence
   scores, enforce Arabic character ratio ≥95%, validate 8-section structure,
   ensure بسم الله الرحمن الرحيم first line. Build FinalArabicBriefComposer that
   merges Agent 8/9/12 outputs into single clean Arabic document.

5. CHAIN-OF-THOUGHT — Add structured thinking instructions to Agents 5, 7, 8
   prompts. Before producing output, the LLM should reason through: key legal
   points, applicable statutes, strengths/weaknesses, strongest opponent argument.

6. SELF-CORRECTION STRICTNESS — For critical agents (6, 8, 9), halt the pipeline
   when self-correction exhausts 3 attempts instead of continuing with best-effort.
   Add Agent 9 fallback: if FINAL_BRIEF_V2 marker not found, build from v1 + fixes.

7. CONTEXT BUDGET INCREASE — Raise LAW_CONTEXT_MAX_CHARS from 50K to 100K. Raise
   03_statutes_index.jsonl cap from 80K to 120K.

8. QUALITY GATE — No final brief published unless it passes all programmatic
   validators (structure, Arabic-only, citation integrity). The pipeline status
   should be "completed_with_issues" if quality gate fails, not "completed".

Constraints:
- Must not break existing case processing — backward compatible
- Must work with both OpenRouter and Puter providers
- All Arabic output in العربية الفصحى القانونية (formal legal Arabic)
- No new database tables (use existing schema)
- Must support streaming (SSE) for real-time output display
```

### 10.2 Plan Prompt

```
/speckit.plan

Plan the implementation of feature 009-pipeline-output-quality based on the
spec. Consider the following architectural constraints:

EXISTING ARCHITECTURE:
- PromptBuilder.php builds prompts by parsing SKILL.md sections
- Phase2BaseAgent.php is the base class for Agents 1-9 (buildContext,
  executeWithStreaming, executeWithSelfCorrection, validateOutput)
- Phase1AnalysisAgent.php handles Agent 0 independently
- Phase3 agents (Judge, DevilsAdvocate, Fortification) each handle their own execution
- LLMServiceInterface defines complete() and completeStream() methods used by
  both OpenRouterService and PuterService
- OutputValidator.php has static validation methods
- CaseEventService handles SSE streaming

KEY DESIGN DECISIONS TO PLAN:
1. How to add system message support to LLMServiceInterface without breaking
   existing calls (the interface currently takes messages array — system message
   can be the first element with role:'system')
2. How to refactor PromptBuilder into buildSystemPrompt() + buildUserPrompt()
   while keeping backward compat with agents that haven't been migrated yet
3. Where BriefPostProcessor fits in the pipeline — after Agent 9? After Agent 12?
   Both?
4. How to integrate VectorSearchService into Phase1AnalysisAgent (it currently
   has no dependency on RAG services)
5. How to update SKILL.md to remove CASE/LAW markers and enforce pure Arabic
   for Agent 8 without breaking the existing validation that checks for those markers
6. How the quality gate interacts with ProcessPhase2Job and ProcessPhase3Job
   status management

IMPLEMENTATION ORDER:
Phase A (immediate): A1-A7 from the analysis
Phase B (1-2 weeks): B1-B8 from the analysis
Focus on Phase A first, plan Phase B dependencies.

Output a plan.md with clear design artifacts for each component change.
```

### 10.3 Tasks Prompt

```
/speckit.tasks

Generate implementation tasks for feature 009-pipeline-output-quality based on
the plan. Break down into atomic, dependency-ordered tasks.

TASK GRANULARITY RULES:
- Each task should be completable in 1-4 hours
- Each task should be independently testable
- Group by component, not by phase
- Mark dependencies explicitly

EXPECTED TASK GROUPS:

Group 1: LLM Interface & System Message Support
- Update LLMServiceInterface (if needed, or document that messages[] already supports system role)
- Refactor PromptBuilder: add buildSystemPrompt(), buildUserPrompt()
- Update Phase2BaseAgent.executeWithStreaming() to use system + user messages
- Update Phase1AnalysisAgent to use system + user messages
- Update each Phase3 agent to use system + user messages
- Write Arabic system prompts for all 13 agents

Group 2: Agent 0 RAG Integration
- Add VectorSearchService dependency to Phase1AnalysisAgent
- Implement keyword extraction from intake text
- Add RAG search before law identification
- Include RAG results in Agent 0 context
- Test with existing seeded laws

Group 3: Arabic Output Enforcement
- Update SKILL.md Agent 8 section: remove CASE/LAW markers, enforce pure Arabic
- Update PromptBuilder Agent 8 template accordingly
- Update OutputValidator: remove citation marker validation for Agent 8
- Add validateArabicFinalBrief() to OutputValidator
- Add validateNoEnglishLeak() to OutputValidator
- Build BriefPostProcessor service
- Build FinalArabicBriefComposer service
- Integrate BriefPostProcessor after Agent 9 and Agent 12

Group 4: Self-Correction & Quality Gate
- Update self-correction for critical agents (6, 8, 9): halt instead of best-effort
- Add Agent 9 fallback for missing FINAL_BRIEF_V2 marker
- Add quality gate check in ProcessPhase2Job and ProcessPhase3Job
- Update case status logic for quality gate failures

Group 5: Context & Chain-of-Thought
- Raise LAW_CONTEXT_MAX_CHARS to 100K
- Raise statute index cap to 120K
- Add chain-of-thought instructions to Agent 5, 7, 8 system prompts
- Test with complex case to verify context improvements

Group 6: Testing & Validation
- Test system message architecture with both OpenRouter and Puter providers
- Test Arabic-only validator on sample briefs (with and without English leakage)
- Test BriefPostProcessor on existing case outputs
- Test Agent 0 RAG integration with seeded laws
- End-to-end test: create case, run full pipeline, verify clean Arabic output

Include test criteria for each task.
```

---

## Appendix: What Each Analysis Contributed

| Insight | Claude | Codex | MiniMax |
|---------|--------|-------|---------|
| No system message | Yes | Yes | Yes |
| Bloated single prompt | Yes | Yes | Yes |
| Agent 0 no RAG | Partial | **Full** | **Full** |
| English/JSON leakage | Yes | Yes | Yes |
| Arabic-only validator missing | No | **Yes** | No |
| BriefPostProcessor needed | Yes | **Yes** (FinalArabicBriefComposer) | **Yes** (ArabicOutputFormatter) |
| Legal reasoning tools (5 tools) | No | **Yes** | No |
| Confidence Arbitration Layer | No | **Yes** | No |
| Agent 9 fallback for missing markers | No | **Yes** | No |
| "No-final-output-unless-pass" policy | No | **Yes** | No |
| Arabic Refinement Agent (Agent 13) | No | No | **Yes** |
| Hierarchical 3-level RAG search | No | No | **Yes** |
| Raise LAW_CONTEXT_MAX_CHARS to 100K | No | No | **Yes** |
| Chain-of-thought / thinking step | Yes | No | Yes |
| Per-agent system message examples | Yes | Yes | **Yes** (with code) |
| Targeted agent replay (not full phase) | No | **Yes** | No |
| Strict self-correction for critical agents | No | **Yes** | No |
| Dynamic prompt by case type | Yes | No | No |
| Two-pass drafting for Agent 8 | Yes | No | No |
| Programmatic document chunking (Agent 2) | Yes | No | No |
| Programmatic statute index (Agent 3) | Yes | No | No |

---

*Generated 2026-03-27 — Unified from 3 independent deep investigations*
