# Saudi Legal Orchestrator — Deep Pipeline Analysis & Improvement Plan

**Date**: 2026-03-27
**Branch**: `008-puter-provider-switch`
**Scope**: Full investigation of case analysis quality, RAG integration, agent prompting, and output formatting

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Current Architecture Overview](#2-current-architecture-overview)
3. [How Case Analysis Works (Step by Step)](#3-how-case-analysis-works)
4. [How RAG Integrates with Agents](#4-how-rag-integrates-with-agents)
5. [Critical Issues Found](#5-critical-issues-found)
6. [Per-Agent Analysis & Improvement Suggestions](#6-per-agent-analysis--improvement-suggestions)
7. [RAG System Issues & Improvements](#7-rag-system-issues--improvements)
8. [Prompt Architecture Issues & Improvements](#8-prompt-architecture-issues--improvements)
9. [Output Quality Issues & Improvements](#9-output-quality-issues--improvements)
10. [Proposed Improvement Plan](#10-proposed-improvement-plan)

---

## 1. Executive Summary

After deep investigation of every agent class, the RAG pipeline, prompt construction, output validation, and display layer, the following root causes explain the poor output quality:

| # | Root Cause | Severity | Impact |
|---|-----------|----------|--------|
| 1 | **No system message — everything sent as a single user message** | CRITICAL | LLM has no persistent role identity; treats the entire SKILL.md + context as a single user request rather than as an expert persona |
| 2 | **Prompt is massively bloated** — General Rules + Agent Section + Anti-Hallucination + Output Template + Context Boundary + Full Case Context all concatenated into one user message | CRITICAL | The LLM drowns in instructions; the actual case data is buried at the bottom after ~2000+ words of rules |
| 3 | **RAG retrieval is weak** — only 4 seeded laws, cosine similarity in PHP with no re-ranking, `text-embedding-3-small` is English-optimized | HIGH | Agents can't find relevant statutes; Agent 3 produces thin statute indexes that starve downstream agents |
| 4 | **Agents produce English/JSON artifacts that leak into final output** — chunk_id, statute_id, confidence scores, JSONL formatting | HIGH | Final brief contains technical artifacts instead of pure Arabic legal prose |
| 5 | **AI Erasure (Agent 9) is unreliable** — it's an LLM task asking another LLM to clean up LLM formatting | HIGH | The transformation from `LAW:{ref}` to prose Arabic depends on the LLM correctly executing string replacements, which it often does poorly |
| 6 | **No dedicated Arabic legal persona** — agents lack a strong identity/role definition | MEDIUM | Output reads like a generic AI response, not like a Saudi lawyer drafting a court submission |
| 7 | **Context budget truncation hides critical data** — 240K char budget with aggressive truncation | MEDIUM | Statute index gets cut at 80K chars; law library at 50K; agents work with incomplete data |
| 8 | **Temperature too uniform (0.2-0.3)** — doesn't differentiate between analytical and creative tasks | LOW | Legal drafting needs higher creativity; QA needs near-zero temperature |
| 9 | **No thinking/reasoning step** — agents jump straight to output without structured reasoning | MEDIUM | Complex legal analysis requires chain-of-thought, but prompts don't request or structure it |

---

## 2. Current Architecture Overview

### Pipeline Structure

```
Phase 1 (1 agent):
  Agent 0: Case Intake → identifies required laws from RAG

Phase 2 (9 agents, sequential):
  Agent 1: Lead Counsel → strategic plan + acceptance criteria
  Agent 2: Evidence Manager → chunk documents into JSONL
  Agent 3: Chain of Custody → build statute index from RAG (SOURCE OF TRUTH)
  Agent 4: Timeline Extractor → extract events chronology
  Agent 5: Law Manager → map issues to statutes
  Agent 6: Statute Matcher → match evidence chunks to statute articles
  Agent 7: Defense Strategist → risk matrix + defense layers
  Agent 8: Legal Drafter → draft the complete brief
  Agent 9: Quality Assurance → QA checklist + AI erasure → brief_v2

Phase 3 (3 agents, sequential):
  Agent 10: Judge → critique from judge perspective
  Agent 11: Devil's Advocate → attack from opponent perspective
  Agent 12: Fortification → harden brief → brief_v3 (final)
```

### LLM Call Structure (Current)

```php
// Phase2BaseAgent::executeWithStreaming()
$messages = [['role' => 'user', 'content' => $prompt]];
// ^ This is the ONLY message sent — no system message at all

$result = $this->openRouter->completeStream($model, $messages, ...);
```

**The prompt ($content) is constructed as:**
```
[General Rules - ~1500 chars of rules about language, citations, anti-hallucination]
---
[Agent-specific section - ~800 chars of role definition + behavior]
---
[Anti-Hallucination Rules - ~1200 chars for agents 3,5,6,7,8,9]
---
[Output Template - ~600-1200 chars of expected format]
---
[Context Boundary Instruction - ~200 chars for agents 5,6,7,8,9]
---
[Case Context - variable, up to 240K chars including intake, documents, law library, prior outputs]
```

**Total prompt size**: 5,000-250,000+ characters sent as a single `user` message.

---

## 3. How Case Analysis Works

### Step-by-Step Flow

**1. Case Creation**
- User submits: title, intake text (case description), document files
- Files stored at `storage/app/cases/{id}/documents/`
- Intake text stored as `cases/{id}/intake.txt`
- `ProcessPhase1Job` dispatched to queue

**2. Phase 1 — Law Identification (Agent 0)**
- Reads intake text + all documents (up to 50KB each)
- Concatenates everything into one prompt
- LLM identifies which Saudi laws apply
- Parses JSON block from response: `{"required_laws": [...]}`
- Creates `RequiredLaw` DB records
- If no laws found, defaults to نظام الإثبات (Evidence Law)
- Saves `00_required_laws.md`
- Case status → `awaiting_laws`

**3. Phase 2 — Legal Analysis (Agents 1-9)**
- `ProcessPhase2Job` dispatched
- `LegalOrchestrator` runs each agent sequentially
- For each agent:
  1. Gate validation (check prerequisites exist)
  2. Build context (intake + docs + law library + prior outputs)
  3. Build prompt via `PromptBuilder::buildPromptForAgent()`
  4. Execute LLM call with streaming
  5. Parse multi-section output (split on `---SECTION_NAME---` markers)
  6. Validate output (JSONL format, statute IDs, quoted text, confidence)
  7. Self-correction loop if violations found (max 3 attempts)
  8. Save outputs to disk + database
  9. Emit SSE events for real-time UI

**4. Phase 3 — Judicial Review (Agents 10-12)**
- `ProcessPhase3Job` dispatched
- Three review agents run sequentially
- Agent failures don't halt pipeline (continues to next)
- Final output: `13_final_brief_v3.md`

### Data Dependencies Between Agents

```
Agent 0 → required_laws
Agent 1 → lead_plan (uses: intake, docs)
Agent 2 → chunks (uses: lead_plan)
Agent 3 → statutes_index [SOURCE OF TRUTH] (uses: chunks, RAG search)
Agent 4 → timeline (uses: chunks)
Agent 5 → issues_to_statutes (uses: chunks, statutes_index, timeline, RAG search)
Agent 6 → statutes_map (uses: chunks, statutes_index, matching_guidelines, RAG search)
Agent 7 → defense_layers (uses: lead_plan, timeline, procedural_notes, statutes_map)
Agent 8 → final_brief (uses: 10 prior outputs — no docs, no law library)
Agent 9 → final_brief_v2 (uses: 6 prior outputs — QA + AI erasure)
Agent 10 → judge_notes (uses: 5 prior outputs)
Agent 11 → devils_advocate (uses: 6 prior outputs)
Agent 12 → final_brief_v3 (uses: 6 prior outputs)
```

---

## 4. How RAG Integrates with Agents

### RAG Architecture

```
Law Files (txt) → LawParserService → LawArticle records → EmbeddingService → LawEmbedding (binary blob)
                                                                                      ↓
User Query → EmbeddingService → Query Vector → VectorSearchService (cosine similarity in PHP) → Top-K Results
```

### RAG Components

**1. Law Parser** (`app/Services/RAG/LawParserService.php`)
- Splits law text files into articles using 10 regex patterns
- Detects Arabic article boundaries: "المادة الأولى", "المادة (1)", "المادة 1", etc.
- No semantic chunking — stores full article text as-is
- Extracts 23 predefined Arabic legal keywords

**2. Embedding Service** (`app/Services/RAG/EmbeddingService.php`)
- Model: `openai/text-embedding-3-small` (1536 dimensions) via OpenRouter
- Fallback: Deterministic TF-IDF-like algorithm when API unavailable
- Prepares text as: `"{LawName} - المادة {Number}: {ArticleText}"`
- Batches up to 2048 texts per API call

**3. Vector Search** (`app/Services/RAG/VectorSearchService.php`)
- Loads ALL embeddings into memory (no DB-level filtering)
- Computes cosine similarity in PHP for each article
- Returns top-K results above minimum similarity threshold
- No re-ranking, no metadata filtering, no caching

### Which Agents Use RAG

| Agent | RAG Method | Top-K | Min Similarity | How Results Are Used |
|-------|-----------|-------|----------------|---------------------|
| Agent 0 | Indirect (law library from DB) | N/A | N/A | Identifies which laws to load |
| Agent 3 | `VectorSearchService::search()` | 20 | 0.60 | Builds `03_statutes_index.jsonl` — the source of truth |
| Agent 5 | `VectorSearchService::searchMultiple()` | 10/query | 0.70 | Maps issues to statutes |
| Agent 6 | `VectorSearchService::searchMultiple()` | 15 | 0.70 | Matches chunks to specific articles |

### How RAG Context Is Injected

**Pattern A — Law Library (most agents):**
```
## Law Library (مكتبة الأنظمة والقوانين)
الأنظمة أدناه من مكتبة الأنظمة المعرّفة في النظام.

### نظام الإثبات
المادة 1: [full article text]
المادة 2: [full article text]
...
(capped at 50K chars total, 15K per law)
```

**Pattern B — RAG Search Results (Agents 3, 5, 6):**
```
## المواد القانونية من قاعدة المعرفة (RAG)
المواد المرشحة من قاعدة المعرفة (مرتبة حسب الصلة):

## [1] نظام الإثبات - المادة 15 (confidence: 0.89)
[Full article text]

## [2] نظام المرافعات - المادة 22 (confidence: 0.78)
[Full article text]
```

### Current Law Library (Seeded)

Only **4 laws** are currently seeded:
1. نظام الإثبات (Evidence Law) — 1443H
2. نظام المرافعات الشرعية (Sharia Court Procedures) — 1435H
3. اللائحة التنفيذية لنظام الإجراءات الجزائية (Criminal Procedures) — 1435H
4. اللوائح التنفيذية لنظام المرافعات الشرعية (Sharia Procedures Rules) — 1435H

**Missing critical laws**: Labor Law (نظام العمل), Commercial Court Law (نظام المحكمة التجارية), Civil Transactions Law (نظام المعاملات المدنية), Personal Status Law (نظام الأحوال الشخصية), Companies Law (نظام الشركات), and many more.

---

## 5. Critical Issues Found

### Issue 1: No System Message (CRITICAL)

**File**: `app/Services/Agents/Phase2/Phase2BaseAgent.php:80`

```php
$messages = [['role' => 'user', 'content' => $prompt]];
```

The entire prompt — rules, role definition, anti-hallucination, output template, and case context — is sent as a single `user` message. There is no `system` message.

**Why this matters:**
- The LLM has no persistent identity or role. It processes everything as "a user asked me to do this" rather than "I AM a Saudi legal expert analyzing this case"
- System messages provide stronger behavioral anchoring — the model is more likely to follow formatting rules, language requirements, and domain constraints when they're in the system message
- The actual case data gets lost at the bottom of a 5K+ word instruction block
- Without a system identity, the model defaults to its general assistant persona, producing generic/shallow analysis

### Issue 2: Bloated Single-Message Prompt (CRITICAL)

**File**: `app/Services/Orchestration/PromptBuilder.php:205-247`

The `buildPromptForAgent()` method concatenates 6 sections with `---` separators into one string:

```
General Rules (~1500 chars)
---
Agent Section (~800 chars)
---
Anti-Hallucination (~1200 chars)  [for 6 agents]
---
Output Template (~600-1200 chars)
---
Context Boundary (~200 chars)  [for 5 agents]
---
Case Context (up to 240K chars)
```

**Why this matters:**
- LLMs have "lost in the middle" problem — instructions at the beginning and end get more attention than those in the middle
- The General Rules section (language, citations, anti-hallucination protocol) is at the very top, far from the actual case data
- The agent's specific role definition is sandwiched between generic rules and output templates
- Case context (the most important part) is at the very bottom, preceded by thousands of characters of instructions
- For agents with 240K chars of context, the instruction section is <2% of the total prompt — it gets overwhelmed

### Issue 3: RAG Retrieval is Weak (HIGH)

**Multiple files in `app/Services/RAG/`**

Problems:
- **Only 4 laws seeded** — most Saudi legal domains (labor, commercial, civil, family, criminal substantive) are not in the database
- **`text-embedding-3-small` is English-optimized** — Arabic text gets poor embeddings compared to multilingual models like `multilingual-e5-large` or `jina-embeddings-v3`
- **No re-ranking** — results are returned purely by cosine similarity with no cross-encoder re-ranking
- **All embeddings loaded in PHP memory** — O(n) linear scan, no vector index (acceptable for ~200 articles, but no room to grow)
- **No query expansion** — single query embedding used, no synonym/variant queries
- **No metadata filtering** — can't prioritize laws by category, relevance to case type, or recency
- **Fallback embeddings are useless** — the TF-IDF fallback produces essentially random vectors for Arabic text
- **Search cache table exists but is never used** — `law_search_cache` migration created but never populated

### Issue 4: English/JSON Artifacts in Final Output (HIGH)

The pipeline produces intermediate artifacts in English/JSON format that are supposed to be cleaned up by Agent 9's "AI Erasure" process. But this process is unreliable because:

1. **Agent 8 (Legal Drafter)** produces a brief with `CASE:{chunk_ref}` and `LAW:{statute_ref}` markers
2. **Agent 9 (QA)** is supposed to transform these to Arabic prose citations
3. But Agent 9 is an LLM doing string replacement — it often:
   - Misses some markers
   - Leaves confidence scores in the text
   - Keeps English field names (`statute_id`, `chunk_id`)
   - Produces mixed Arabic/English paragraphs
4. **Agent 12 (Fortification)** does another AI erasure pass, but compounds errors

### Issue 5: No Structured Thinking Step (MEDIUM)

No agent prompt asks the LLM to think through the problem before producing output. Each agent receives instructions and immediately produces final output. For complex legal analysis, this leads to:
- Shallow reasoning on legal arguments
- Missing connections between facts and law
- Weak syllogisms (Major → Minor → Conclusion that don't actually follow)
- Formulaic output that doesn't adapt to case specifics

---

## 6. Per-Agent Analysis & Improvement Suggestions

### Agent 0: Phase 1 Analysis (تحليل المرحلة الأولى)

**Current approach**: Reads intake + docs, identifies required laws, outputs markdown + JSON.

**Issues found**:
- Falls back to نظام الإثبات (Evidence Law) if no laws identified — this is too generic
- JSON parsing uses regex (`/```json\s*([\s\S]*?)\s*```/`) which is fragile
- No system message = no legal expert persona
- Limited to 4096 max_tokens — may be insufficient for complex cases

**Improvement suggestions**:
1. Add a system message: "أنت محلل قانوني سعودي متخصص في تحليل القضايا وتحديد الأنظمة المعمول بها"
2. Use structured output (JSON mode) instead of regex parsing
3. Increase max_tokens to 8192
4. Instead of defaulting to Evidence Law, ask the LLM to reason about which legal domains apply based on case facts
5. Include a brief description of all available laws in the RAG library so the LLM can match intelligently

### Agent 1: Lead Counsel (القائد القانوني)

**Current approach**: Creates strategic plan + acceptance criteria JSON.

**Issues found**:
- Uses `executeWithSelfCorrection()` but has no agent-specific validation in `validateOutput()`
- Requires NO prior outputs (empty array) — works only from intake + docs
- The acceptance criteria JSON is always the same template — it's not dynamic based on case

**Improvement suggestions**:
1. System message: "أنت مستشار قانوني أول في مكتب محاماة سعودي رائد. مسؤوليتك وضع الخطة الاستراتيجية للتحليل القانوني"
2. Make acceptance criteria dynamic based on case complexity
3. Add a thinking step: "فكّر أولاً في الجوانب القانونية المعقدة لهذه القضية قبل وضع الخطة"
4. The plan should explicitly prioritize which legal arguments are strongest

### Agent 2: Evidence Manager (مدير الأدلة)

**Current approach**: Chunks documents into JSONL with 1200-1800 char chunks.

**Issues found**:
- Requires only `01_lead_plan.md` but `needsDocuments()` returns false — so it works from the plan only, not original documents
- Chunk splitting is done by LLM, not programmatically — wasteful and unreliable
- Temperature 0.2 is appropriate for this task

**Improvement suggestions**:
1. This should be a **programmatic** operation, not an LLM task — chunking documents doesn't require intelligence
2. Move document chunking to PHP code (save LLM tokens and improve reliability)
3. If LLM must be used, it should work on original documents, not the lead plan

### Agent 3: Chain of Custody (سلسلة الحفظ)

**Current approach**: Builds `03_statutes_index.jsonl` from RAG search results — this is the SOURCE OF TRUTH for all citations.

**Issues found**:
- This agent is the **bottleneck of the entire pipeline** — if it produces a poor statute index, every downstream agent suffers
- RAG search uses min similarity 0.60 (broader) but only 20 results
- The LLM is asked to produce JSONL with exact statute content — but it's generating content that should come directly from the database
- If RAG returns poor results, the statute index will be thin/incomplete

**Improvement suggestions**:
1. **This should be mostly programmatic** — query RAG, then format results as JSONL directly from database records, not LLM-generated
2. The LLM's role should be limited to: (a) generating good search queries, (b) filtering irrelevant results, (c) identifying conflicts
3. Article content in the statute index should be **copied verbatim from the database**, not LLM-generated
4. Increase RAG results to 40-50 and let the LLM filter down
5. System message: "أنت أمين سجل المحكمة مسؤول عن بناء فهرس المواد النظامية الدقيق"

### Agent 4: Timeline Extractor (مستخلص الجدول الزمني)

**Current approach**: Extracts events from chunks into JSON + prose narrative.

**Issues found**:
- Works only on `02_chunks.jsonl` (no original documents)
- Chunk data may be LLM-generated (from Agent 2), adding a layer of unreliability
- Good concept but depends on quality of upstream chunks

**Improvement suggestions**:
1. Should work on original documents, not chunks
2. System message: "أنت محقق قضائي متخصص في استخلاص الوقائع والتسلسل الزمني من المستندات القانونية"
3. Add explicit instruction to preserve exact dates as written in documents

### Agent 5: Law Manager (مدير القانون)

**Current approach**: Maps issues to statutes with strength assessment + adversary analysis.

**Issues found**:
- RAG search generates queries from intake text — these may not be precise enough
- Produces `05_matching_guidelines.json` for Agent 6 — a multi-hop dependency
- The three-step adversary analysis (Fact → Flaw → Effect) is a good concept but often produced generically

**Improvement suggestions**:
1. System message: "أنت خبير في النظام القانوني السعودي، متخصص في تحليل القضايا وربط الوقائع بالمواد النظامية"
2. RAG queries should be more specific — use case category + extracted legal concepts
3. The adversary analysis should reference specific articles and counter-arguments
4. Add chain-of-thought: "حلّل كل واقعة على حدة وحدد المواد النظامية المنطبقة مع شرح السبب"

### Agent 6: Statute Matcher (مطابق الأنظمة)

**Current approach**: Matches evidence chunks to statute articles with confidence scoring.

**Issues found**:
- Has the most complex validation (4-point check)
- `quoted_text` must be literal from statute index — but LLMs paraphrase
- Logical Fallback to Islamic maxims is a good concept but rarely triggered correctly
- RAG search extracts queries from Agent 5 output — two-hop dependency

**Improvement suggestions**:
1. The `quoted_text` copying should be **programmatic** — look up the statute in the index and copy the text, don't ask the LLM to copy it
2. System message: "أنت باحث قانوني متخصص في مطابقة الأدلة مع المواد النظامية السعودية"
3. Consider hybrid approach: LLM identifies which statute applies, PHP code copies the exact text
4. Validation should be stricter — reject and self-correct on any quote mismatch

### Agent 7: Defense Strategist (الاستراتيجي)

**Current approach**: Builds risk matrix + three-tier defense strategy.

**Issues found**:
- No documents and no law library — works entirely from prior outputs
- The three-tier defense structure is well-designed
- Risk matrix is often generic rather than case-specific

**Improvement suggestions**:
1. System message: "أنت محامي دفاع خبير في المحاكم السعودية، متخصص في بناء استراتيجيات الدفاع القوية"
2. Add explicit instruction: "يجب أن تكون كل طبقة دفاع مبنية على مواد نظامية محددة من فهرس المواد"
3. Include thinking step: "حلّل نقاط القوة والضعف في القضية أولاً، ثم ابنِ استراتيجية الدفاع"
4. Temperature could be slightly higher (0.4) for more creative defense strategies

### Agent 8: Legal Drafter (الصائغ القانوني)

**Current approach**: Drafts complete legal brief with 8 mandatory sections.

**Issues found**:
- Uses 10 specific prior outputs (no docs, no law library)
- max_tokens = 16384 (highest alongside Agents 9, 12) — good
- The mandatory 8-section structure is well-defined in SKILL.md
- Uses `CASE:{ref}` and `LAW:{ref}` markers that must be cleaned later
- Often produces shallow syllogisms
- Brief quality depends entirely on quality of all upstream outputs

**Improvement suggestions**:
1. **This is the most critical agent** — it should have the strongest system message
2. System message: "أنت صائغ قانوني محترف في مكتب محاماة سعودي. تكتب مذكرات قانونية تُقدَّم للمحاكم السعودية. أسلوبك رسمي، دقيق، ومبني على الأنظمة والشريعة الإسلامية. لا تستخدم أي مصطلحات إنجليزية أو تقنية."
3. **Eliminate `CASE:{ref}` and `LAW:{ref}` markers entirely** — instead, instruct the agent to write prose Arabic citations directly (e.g., "وفقاً للمادة الثمانين من نظام العمل" instead of `LAW:LABOR_LAW_ART_80`)
4. This eliminates the need for AI Erasure altogether
5. Add a thinking section: "قبل كتابة المذكرة، لخّص الحجج الرئيسية والمواد النظامية الداعمة"
6. Increase temperature to 0.4 for more natural Arabic prose
7. Consider splitting into two passes: (a) outline with key arguments, (b) full prose draft

### Agent 9: Quality Assurance (ضبط الجودة)

**Current approach**: 10-point QA checklist + AI Erasure → `brief_v2`.

**Issues found**:
- AI Erasure is the weakest part — asking an LLM to do regex-like string replacement
- QA checklist is good conceptually but the LLM often skips checks or gives false passes
- Temperature 0.2 is correct for this task

**Improvement suggestions**:
1. **QA checks should be mostly programmatic** — PHP code can check:
   - Brief structure (section headers present)
   - Citation format compliance
   - No markdown tables
   - Preamble present
   - Three-part requests present
2. LLM QA should focus on what code can't check:
   - Syllogism soundness
   - Argument coherence
   - Legal accuracy
3. **AI Erasure should be a PHP post-processing step**, not an LLM task:
   - Replace `LAW:{statute_id}` with pre-built Arabic citation lookup
   - Replace `CASE:{chunk_id}` with Arabic document reference
   - Strip confidence scores via regex
   - Remove metadata headers
4. OR better: eliminate the need for erasure by having Agent 8 write pure Arabic from the start

### Agents 10-12: Phase 3 (Judicial Review)

**Current approach**: Judge critique → Opponent attack → Fortification → final brief v3.

**Issues found**:
- Good concept — adversarial review strengthens the brief
- Agent 10 (Judge) and Agent 11 (Devil's Advocate) produce critique in Arabic — good
- Agent 12 (Fortification) does another AI Erasure pass — compounds errors
- Agent failures in Phase 3 don't halt pipeline — good resilience

**Improvement suggestions**:
1. System messages for each role:
   - Agent 10: "أنت قاضٍ في المحكمة العمالية/التجارية/الجزائية السعودية، تراجع المذكرات المقدمة بعين نقدية"
   - Agent 11: "أنت المحامي المنافس الذي يمثل الطرف المقابل، مهمتك إيجاد الثغرات في المذكرة"
   - Agent 12: "أنت خبير تحصين قانوني، مهمتك تقوية المذكرة بناءً على الملاحظات القضائية والهجمات المتوقعة"
2. Agent 12 should produce the final brief directly in court-ready Arabic (no erasure needed)
3. Consider making Agent 12 output the only displayed output to the user

---

## 7. RAG System Issues & Improvements

### Current State

| Component | Status | Issue |
|-----------|--------|-------|
| Law Parser | Working | Only handles article-level chunks, no subsections |
| Embedding Model | `text-embedding-3-small` | English-optimized, poor Arabic embeddings |
| Vector Storage | Binary blob in MySQL/SQLite | No vector index, full table scan |
| Search | Cosine similarity in PHP | O(n) linear, no re-ranking |
| Law Coverage | 4 laws seeded | Missing major Saudi law domains |
| Search Cache | Table exists, never used | Wasted optimization opportunity |

### Improvement Plan

**Priority 1: Expand Law Library**
- Add all major Saudi laws: نظام العمل, نظام المحكمة التجارية, نظام المعاملات المدنية, نظام الأحوال الشخصية, نظام الشركات, النظام الجزائي, نظام مكافحة الغسل, etc.
- At minimum, add the 20 most commonly used Saudi laws
- Allow admin to upload new law files and trigger re-embedding

**Priority 2: Better Embedding Model**
- Switch from `text-embedding-3-small` to a multilingual model:
  - `text-embedding-3-large` (better but still English-biased)
  - `BAAI/bge-m3` (excellent multilingual including Arabic)
  - `intfloat/multilingual-e5-large` (strong Arabic support)
  - `Cohere/embed-multilingual-v3.0` (available on OpenRouter)
- Re-embed all existing articles after model switch

**Priority 3: Implement Search Cache**
- The `law_search_cache` table already exists — implement it
- Cache query → results mapping with SHA256 hash
- Invalidate on law library changes
- Reduces redundant embedding API calls

**Priority 4: Add Re-Ranking**
- After initial vector search, use a cross-encoder or LLM to re-rank results
- Filter by case category (civil, criminal, labor, commercial)
- Boost articles from laws identified in Phase 1

**Priority 5: Programmatic Statute Index**
- Agent 3's statute index should be built programmatically from RAG results
- LLM's role: generate search queries, filter results, identify conflicts
- Article content: copied verbatim from `law_articles` table

---

## 8. Prompt Architecture Issues & Improvements

### Current Architecture (Problematic)

```
Single User Message = [
  General Rules (static, ~1500 chars)
  + Agent Section (static, ~800 chars)
  + Anti-Hallucination (static, ~1200 chars)
  + Output Template (static, ~600-1200 chars)
  + Context Boundary (static, ~200 chars)
  + Case Context (dynamic, up to 240K chars)
]
```

**Problems:**
1. No system message → no role anchoring
2. Static instructions bloat every prompt
3. Instructions far from data → "lost in the middle"
4. One-shot → no conversational reasoning

### Proposed Architecture

```
System Message = [
  Agent Identity (who you are, your expertise, your writing style)
  + Core Rules (Arabic language, citation format, confidence threshold)
  + Agent-Specific Behavior (what this agent does, what it produces)
  + Anti-Hallucination Rules (for relevant agents)
  + Output Format Specification
]

User Message = [
  Case Context (intake, documents, law library, prior outputs)
  + Specific Task Instruction ("analyze the following case and produce...")
]
```

**Why this is better:**
1. **System message anchors the persona** — "I am a Saudi legal expert" persists throughout
2. **User message is the case data** — what the LLM actually needs to analyze
3. **Cleaner separation** — instructions in system, data in user
4. **Better attention** — LLM focuses on the case data, not on remembering rules
5. **Smaller user message** — instructions don't consume context budget

### Implementation Change

In `Phase2BaseAgent::executeWithStreaming()`:

```php
// BEFORE (current):
$messages = [['role' => 'user', 'content' => $prompt]];

// AFTER (proposed):
$messages = [
    ['role' => 'system', 'content' => $systemPrompt],
    ['role' => 'user', 'content' => $caseContext],
];
```

This requires refactoring `PromptBuilder::buildPromptForAgent()` to return two strings:
1. `buildSystemPrompt(int $agentNumber): string` — static instructions + persona
2. `buildUserPrompt(int $agentNumber, string $context): string` — case data + task

### Arabic-First Prompt Design

Current prompts mix Arabic and English. The output template uses Arabic headers but English formatting instructions. Proposed approach:

```
System: أنت صائغ قانوني سعودي محترف. مهمتك كتابة مذكرات قانونية رسمية...
[All instructions in Arabic for Arabic-output agents]
[English only for JSON schema definitions]
```

---

## 9. Output Quality Issues & Improvements

### Issue: Mixed Languages in Final Output

**Root cause**: Agents produce intermediate formats with English keys (chunk_id, statute_id, confidence) that leak into the final brief.

**Solution**:
1. Eliminate `CASE:{ref}` and `LAW:{ref}` markers entirely
2. Instruct Agent 8 to write pure Arabic prose from the start
3. Have Agent 8 produce natural Arabic citations: "وفقاً للمادة الثمانين من نظام العمل"
4. Move any string replacement to PHP post-processing, not LLM

### Issue: Shallow Legal Analysis

**Root cause**: No structured thinking step. Agents jump from instructions to output.

**Solution**:
1. Add explicit thinking instructions to key agents (5, 7, 8):
   ```
   ## مرحلة التفكير (لا تُدرج في المخرج النهائي)
   قبل كتابة المخرج، فكّر في:
   1. ما هي النقاط القانونية الرئيسية في هذه القضية؟
   2. ما هي المواد النظامية الأكثر صلة؟
   3. ما هي نقاط القوة والضعف في موقف الموكل؟
   4. ما هي الحجة الأقوى للخصم وكيف نرد عليها؟
   ```
2. Use `<thinking>` tags for models that support extended thinking
3. For Claude models: leverage the thinking budget feature

### Issue: Formulaic/Generic Output

**Root cause**: Prompts are template-heavy with the same rules for every case. The LLM follows the template mechanically.

**Solution**:
1. Dynamic prompt sections based on case type:
   - Labor cases: emphasize نظام العمل articles, worker protections, employer obligations
   - Commercial cases: emphasize نظام المحكمة التجارية, contract law, partnership disputes
   - Criminal cases: emphasize criminal procedure, evidence standards, burden of proof
2. Case-type-specific system messages
3. Remove generic fallbacks that make output formulaic

### Issue: JSON in Displayed Output

**Root cause**: The UI displays markdown outputs from the database. Some agents produce JSON/JSONL that gets shown raw.

**Solution**:
1. Only display markdown outputs to users (already partially implemented — `agent-timeline-live.blade.php` filters by content_type)
2. For the final brief, display only `13_final_brief_v3.md` (or `09_final_brief_v2.md` if Phase 3 skipped)
3. Hide intermediate agent outputs behind an "advanced view" toggle
4. The primary user-facing output should be the final court-ready Arabic brief

---

## 10. Proposed Improvement Plan

### Phase A: Quick Wins (High Impact, Low Effort)

| # | Change | Files to Modify | Impact |
|---|--------|----------------|--------|
| A1 | **Add system messages** — Split prompt into system (instructions) and user (case data) | `Phase2BaseAgent.php`, `PromptBuilder.php`, `LLMServiceInterface.php` | CRITICAL — immediate improvement in output quality and persona consistency |
| A2 | **Arabic-first system prompts** — Each agent gets a strong Arabic legal persona | `PromptBuilder.php` | HIGH — output will read more like a Saudi lawyer, less like a generic AI |
| A3 | **Eliminate CASE/LAW markers in Agent 8** — Write pure Arabic citations directly | `PromptBuilder.php` (template for Agent 8), `SKILL.md` Agent 8 section | HIGH — eliminates AI erasure failures |
| A4 | **Programmatic QA checks** — Move structural validation from Agent 9 to PHP | `OutputValidator.php`, `QualityAssuranceAgent.php` | MEDIUM — more reliable QA, save LLM tokens |
| A5 | **Add chain-of-thought instructions** to Agents 5, 7, 8 | `PromptBuilder.php` | MEDIUM — deeper legal reasoning |

### Phase B: RAG Improvements (High Impact, Medium Effort)

| # | Change | Files to Modify | Impact |
|---|--------|----------------|--------|
| B1 | **Expand law library** — Add 15-20 major Saudi laws | `database/seeders/`, `storage/laws/` | CRITICAL — without laws, RAG is useless for most case types |
| B2 | **Switch embedding model** to multilingual (e.g., `text-embedding-3-large` or `BAAI/bge-m3`) | `EmbeddingService.php`, `config/openrouter.php` | HIGH — dramatically better Arabic retrieval |
| B3 | **Implement search cache** | `VectorSearchService.php` | MEDIUM — faster repeat queries |
| B4 | **Programmatic statute index** — Agent 3 copies article text from DB, not LLM-generated | `ChainOfCustodyAgent.php` | HIGH — eliminates hallucinated statute content |

### Phase C: Architecture Improvements (Medium Impact, Higher Effort)

| # | Change | Files to Modify | Impact |
|---|--------|----------------|--------|
| C1 | **Programmatic document chunking** — Replace Agent 2 with PHP code | New `DocumentChunker.php` service | MEDIUM — saves one LLM call, more reliable chunks |
| C2 | **Dynamic prompts by case type** — Adapt instructions based on legal domain | `PromptBuilder.php`, `LegalOrchestrator.php` | MEDIUM — more relevant analysis per case type |
| C3 | **Two-pass drafting for Agent 8** — Outline first, then full draft | `LegalDrafterAgent.php` | MEDIUM — better structured briefs |
| C4 | **Post-processing pipeline** — PHP-based cleanup of final brief (strip remaining English, format Arabic citations, etc.) | New `BriefPostProcessor.php` service | HIGH — guaranteed clean Arabic output |
| C5 | **Display only final brief to user** — Hide intermediate outputs behind advanced view | `agent-timeline-live.blade.php`, `show.blade.php` | MEDIUM — better user experience |

### Phase D: Advanced Improvements (Long-term)

| # | Change | Impact |
|---|--------|--------|
| D1 | **Vector database** — Migrate from PHP cosine similarity to pgvector or Qdrant | Scalable to 100K+ articles |
| D2 | **Cross-encoder re-ranking** — Re-rank RAG results with a fine-tuned Arabic legal model | Better retrieval precision |
| D3 | **Fine-tuned Arabic legal model** — Train on Saudi court documents | Domain-specific output quality |
| D4 | **Multi-turn agent conversations** — Allow agents to ask clarifying questions | Deeper analysis |
| D5 | **Parallel agent execution** — Run independent agents concurrently | Faster pipeline |

---

## Summary of Key Recommendations

1. **Add system messages NOW** — This is the single most impactful change. Every agent should have a strong Arabic legal persona in the system message.

2. **Write Arabic from the start** — Don't rely on AI Erasure. Instruct agents to produce pure Arabic output. Move any cleanup to PHP post-processing.

3. **Expand the law library** — 4 laws is not enough. Add at least the 15 most-used Saudi laws.

4. **Make Agent 3 (statute index) programmatic** — Copy article text from the database verbatim. Don't ask the LLM to reproduce legal text.

5. **Add structured thinking** — Complex legal analysis needs chain-of-thought reasoning before output.

6. **Split prompts into system + user messages** — Better persona anchoring, cleaner context, improved output quality.

7. **Programmatic QA** — Use PHP for structural checks, reserve LLM for substantive legal review.

8. **Better Arabic embeddings** — Switch to a multilingual embedding model for better RAG retrieval.
