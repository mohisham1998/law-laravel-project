# Codex Deep Analysis — جودة مخرجات القضايا

تاريخ التحليل: 2026-03-27

## 0) تسمية الملفات حسب طلبك
- minimax_analysis = DEEP_CASE_ANALYSIS_INVESTIGATION.md
- claude_analysis = PIPELINE_ANALYSIS.md
- codex_analysis = هذا الملف

## 1) الملخص التنفيذي
التحقيق العميق يؤكد أن ضعف جودة المخرجات ليس من سبب واحد، بل من تجمّع 6 عوامل تصميمية وتشغيلية:

1. لا يوجد System Message فعلي لوكلاء Phase 1/2/3، وكل الطلب يُرسل كـ user message فقط.
2. Prompt طويل جداً ومكدّس (قواعد + قالب + سياق) في رسالة واحدة، ما يضعف الالتزام بالتعليمات الدقيقة.
3. RAG يعمل جزئياً بشكل جيد في Agents 3/5/6، لكنه لا يتدخل في Agent 0 (مرحلة تحديد القوانين من البداية).
4. مسارات الإخراج مصممة لإنتاج JSON/JSONL وسيط بكثافة، ثم تحويله لاحقاً، مما يسرّب الأثر التقني للمخرجات النهائية.
5. التحقق الآلي (OutputValidator) قوي في البنية والاقتباس القانوني، لكنه لا يفرض Arabic-only enforcement.
6. حدود السياق والتقطيع (truncation) قد تقصّ مواد قانونية أو سياق مهم في القضايا الكبيرة.

النتيجة: النظام قادر على إنتاج مخرجات مقبولة في حالات بسيطة، لكنه غير مستقر في “جودة قانونية احترافية عربية خالصة” في الحالات المعقدة.

---

## 2) كيف يعمل تحليل القضية حالياً (تفصيلياً)

### 2.1 مرحلة الإنشاء والتجهيز
- المستخدم ينشئ القضية + intake + مرفقات.
- النظام يحفظ intake في `cases/{id}/intake.txt`.
- يبدأ `ProcessPhase1Job`.

مرجع:
- app/Http/Controllers/CaseController.php:230
- app/Jobs/ProcessPhase1Job.php:46

### 2.2 Phase 1 (Agent 0)
- الوكيل `Phase1AnalysisAgent` يبني السياق من intake + محتوى المستندات (مقتطع حتى 50,000 حرف لكل مستند).
- يبني prompt عبر `PromptBuilder::buildPromptForAgent(0, context)`.
- يرسل للـ LLM كرسالة user فقط (بدون system).
- يستخرج required_laws من JSON داخل النص الناتج.
- يحفظ `00_required_laws.md` + سجلات RequiredLaw.

مرجع:
- app/Services/Agents/Phase1AnalysisAgent.php:45
- app/Services/Agents/Phase1AnalysisAgent.php:67
- app/Services/Agents/Phase1AnalysisAgent.php:116

### 2.3 Phase 2 (Agents 1-9)
- `LegalOrchestrator` يشغّل الوكلاء بالتسلسل.
- لكل وكيل: gate validation → execute → validation/correction → حفظ outputs.
- عند فشل وكيل بعد retries: يتوقف الـ pipeline (halt).
- عند إكمال المرحلة: status = phase2_completed أو completed_with_warnings لو ظهر below_threshold.

مرجع:
- app/Services/Orchestration/LegalOrchestrator.php:47
- app/Services/Orchestration/LegalOrchestrator.php:237
- app/Services/Orchestration/LegalOrchestrator.php:446

### 2.4 Phase 3 (Agents 10-12)
- تشغيل `Judge`, ثم `Devil's Advocate`, ثم `Fortification`.
- كل وكيل Phase 3 أيضاً يرسل user message فقط (بدون system).
- الناتج النهائي المستهدف: `13_final_brief_v3.md`.

مرجع:
- app/Jobs/ProcessPhase3Job.php:76
- app/Services/Agents/Phase3/JudgeAgent.php:49
- app/Services/Agents/Phase3/DevilsAdvocateAgent.php:43
- app/Services/Agents/Phase3/FortificationAgent.php:61

---

## 3) كيف يتدخل RAG فعلياً

## 3.1 مكوّنات RAG
- Embedding: `openai/text-embedding-3-small` عبر OpenRouter.
- Search: cosine similarity على كل embeddings المسترجعة من DB في PHP.
- Parsing: استخراج المواد من ملفات القوانين بأنماط regex عربية متعددة.

مرجع:
- app/Services/RAG/EmbeddingService.php:17
- app/Services/RAG/VectorSearchService.php:19
- app/Services/RAG/LawParserService.php:88

## 3.2 التدخل حسب الوكيل
- Agent 0: لا يستخدم VectorSearchService مباشرة (فجوة مهمة).
- Agent 3: يستخدم RAG `searchMultiple(..., 20, 0.60)` لبناء فهرس المواد.
- Agent 5: يستخدم RAG `searchMultiple(..., 10, 0.70)`.
- Agent 6: يستخدم RAG `searchMultiple(..., 15, 0.70)`.
- باقي الوكلاء يعتمدون على outputs المسبقة أكثر من استدعاء RAG مباشر.

مرجع:
- app/Services/Agents/Phase1AnalysisAgent.php:116
- app/Services/Agents/Phase2/ChainOfCustodyAgent.php:98
- app/Services/Agents/Phase2/LawManagerAgent.php:111
- app/Services/Agents/Phase2/StatuteMatcherAgent.php:124

## 3.3 ماذا يعني هذا عملياً
- جودة RAG downstream جيدة نسبياً بعد Agent 3.
- لكن اختيار القوانين الأساسية في البداية (Phase 1) يظل معتمدًا على استدلال LLM من النص الخام وليس استرجاعًا دلاليًا قانونيًا مباشرًا.
- أي نقص مبكر في required_laws ينعكس على كل السلسلة.

---

## 4) Prompt Architecture الحالية ولماذا تؤثر على الجودة

`PromptBuilder` يركّب prompt من:
1. General Rules
2. Agent Section
3. Anti-Hallucination (لبعض الوكلاء)
4. Output Template
5. Context boundary
6. Case context

ثم يتم إرسال النتيجة كلها كـ user content في رسالة واحدة.

مرجع:
- app/Services/Orchestration/PromptBuilder.php:185
- app/Services/Agents/Phase2/Phase2BaseAgent.php:79
- app/Services/Agents/Phase1AnalysisAgent.php:68
- app/Services/Agents/Phase3/JudgeAgent.php:49

الحكم الفني:
- هذا يسبب تراجع الالتزام في القضايا الطويلة.
- نعم، نحتاج System Message لكل Agent role (أو على الأقل base system + agent override) بشكل واضح.

---

## 5) ما الذي هو جيد حالياً (نقاط قوة فعلية)

1. يوجد تنظيم قوي لسلسلة الوكلاء ومخرجات معيارية لكل Agent.
2. يوجد Self-correction loop داخل Phase 2 بحد أقصى 3 محاولات.
3. OutputValidator ممتاز في كشف:
- JSONL malformation
- hallucinated statute_id
- fabricated quoted_text
- confidence floor
- brief structure
4. يوجد tracking جيد للأخطاء والتكلفة والتوكنات.
5. يوجد gating وhalt logic يمنع استمرار سلسلة معطوبة بشكل صريح.

مراجع:
- app/Services/Agents/Phase2/Phase2BaseAgent.php:364
- app/Services/Orchestration/OutputValidator.php:18
- app/Services/Orchestration/LegalOrchestrator.php:420
- app/Services/Orchestration/GateValidator.php:12

---

## 6) Approvement Suggestion لكل Approach مطبق

## 6.1 Agent Orchestration (1→12)
- التقييم: APPROVE WITH IMPROVEMENTS
- السبب: السلسلة واضحة ومنطقية، لكن تحتاج "مرحلة قرار" إضافية قبل الصياغة النهائية (quality gate أقوى + إعادة تغذية ذكية).
- التحسين المقترح:
- إضافة Agent وسيط قبل Agent 8: Legal Reasoning Synthesizer.
- إضافة replay targeted بدل إعادة المرحلة كاملة عند فشل محدد.

## 6.2 PromptBuilder الحالي
- التقييم: NEEDS IMPROVEMENT (HIGH)
- السبب: البناء جيد شكلياً لكن بدون system role، ومع تكديس التعليمات في user واحدة.
- التحسين المقترح:
- split prompts إلى:
- system: persona + ثابتات منهجية
- developer/user: task + context
- اكتب policy صريحة: Arabic legal prose only في system.

## 6.3 RAG في Agent 3/5/6
- التقييم: APPROVE
- السبب: دمج فعلي ومفيد مع عتبات واضحة.
- التحسين المقترح:
- إضافة reranker (cross-encoder) للـ top-N.
- إضافة metadata filtering (حسب القانون/الباب/الزمن).

## 6.4 RAG في Agent 0
- التقييم: REJECT (as-is)
- السبب: لا يوجد RAG search مباشر رغم أنه نقطة البداية.
- التحسين المقترح:
- Agent 0 يجب أن ينفّذ retrieval قبل تحديد required_laws.

## 6.5 OutputValidator
- التقييم: APPROVE WITH CRITICAL GAP
- السبب: قوي بنيوياً، لكن لا يفرض Arabic-only policy.
- التحسين المقترح:
- إضافة validator لغوي:
- منع الجمل الإنجليزية في final briefs
- منع تسريب مفاتيح JSON في النسخة النهائية
- فحص نسبة الحروف العربية الدنيا

## 6.6 Self-Correction (max 3)
- التقييم: NEEDS IMPROVEMENT
- السبب: مفيد، لكنه ينتهي إلى best-effort حتى مع مخالفات.
- التحسين المقترح:
- عند استنفاد المحاولات في وكلاء حرجة (6/8/9): توقف واعادة توليد سياق مخصص بدل الاستمرار.

## 6.7 Context Budget & Truncation
- التقييم: NEEDS IMPROVEMENT
- السبب: توجد حدود ثابتة قد تقطع مواد أساسية.
- التحسين المقترح:
- ميزانية ديناميكية حسب تعقيد القضية.
- hierarchical context packing: خلاصات أولاً + expandable references.

## 6.8 UI Rendering Policy
- التقييم: APPROVE (جزئياً)
- السبب: الواجهة تستثني JSON/JSONL من العرض العام (جيد).
- الفجوة: لا توجد طبقة تحويل Arabic publishing للنسخة النهائية.

مرجع:
- resources/views/components/agent-timeline-live.blade.php:5

---

## 7) لماذا المخرجات ليست “عربية نهائية احترافية” الآن

الأسباب الجذرية المباشرة:
1. النظام ينتج artifacts وسيطة كثيرة (JSON/JSONL) ويعتمد على LLM لتحويلها لاحقاً.
2. لا توجد Post-processor حتمي deterministic للنشر العربي النهائي.
3. لا توجد لغة إلزامية في validator النهائي.
4. غياب system role لكل وكيل يضعف الانضباط الأسلوبي.
5. Agent 9 قد لا يُخرج `09_final_brief_v2.md` إذا لم تظهر marker بشكل صحيح.

مرجع:
- app/Services/Agents/Phase2/QualityAssuranceAgent.php:95

---

## 8) هل نحتاج System Message لكل Agent؟
نعم، وبقوة.

القرار المقترح:
- Base System Message موحّد لكل الوكلاء:
- "خبير قانون سعودي"
- "إخراج عربي فصيح قانوني فقط"
- "ممنوع أي JSON أو English في final brief"
- Agent-specific System Addendum:
- يحدد دور الوكيل بدقة (Evidence, Matching, Drafting, QA...).

السبب:
- رفع ثبات السلوك عبر الاستدعاءات.
- تقليل انجراف الأسلوب.
- دعم أعلى لتعليمات اللغة والالتزام القضائي.

---

## 9) خطة تحسين عملية عالية الجودة

## المرحلة العاجلة (48 ساعة)
1. تطبيق system message في:
- app/Services/Agents/Phase1AnalysisAgent.php
- app/Services/Agents/Phase2/Phase2BaseAgent.php
- app/Services/Agents/Phase3/JudgeAgent.php
- app/Services/Agents/Phase3/DevilsAdvocateAgent.php
- app/Services/Agents/Phase3/FortificationAgent.php

2. إضافة Arabic Output Guard داخل `OutputValidator`:
- validateArabicFinalBrief(string $brief)
- validateNoEnglishLeak(string $brief)

3. إضافة RAG retrieval إلى Agent 0 قبل parse required laws.

4. إضافة fallback deterministic في Agent 9:
- لو لم يوجد marker لـ FINAL_BRIEF_V2، يُبنى brief_v2 من brief_v1 + fixes applied بدل فقد الملف.

## المرحلة القصيرة (أسبوع)
1. فصل “output working format” عن “output publishing format”:
- working: JSON/JSONL داخلي
- publishing: Markdown عربي قانوني خالص

2. إنشاء خدمة جديدة:
- FinalArabicBriefComposer
وظيفتها:
- دمج نتائج 8/9/12
- إزالة أي مفاتيح/artefacts غير عربية
- توحيد الأسلوب القضائي النهائي

3. إضافة retrieval quality metrics:
- recall@k
- statute coverage
- citation correctness

## المرحلة المتوسطة (2-4 أسابيع)
1. إضافة Reranker فوق Vector search.
2. توسيع corpus القوانين وتوسيمها metadata أفضل (اختصاص، باب، نسخ/إلغاء، تاريخ نفاذ).
3. إضافة "Decision Tools" داخل السلسلة:
- conflict checker
- procedural admissibility checker
- burden-of-proof checker
- remedy calculator
4. تبني سياسة "no-final-output-unless-pass":
- لا نشر نهائي إلا بعد نجاح quality gates الحتمية.

---

## 10) Blueprint لأدوات تجعل النظام "يفكر بشكل صحيح دائماً"

الأدوات المقترحة داخل التطبيق:
1. Legal Issue Tree Builder
- يبني شجرة نزاع: الوقائع → المسائل → المواد → الدفوع.

2. Citation Integrity Engine
- يتحقق أن كل LAW/CASE مذكور موجود ومطابق حرفياً.

3. Procedural Gate Engine
- يتحقق من الاختصاص، الميعاد، الصفة، المصلحة قبل أي صياغة موضوعية.

4. Contradiction Detector
- يرصد التعارض بين timeline، evidences، والدفوع.

5. Arabic Publication Formatter
- يمنع أي JSON/English في النسخة النهائية.
- يوحّد الأسلوب إلى فصحى قضائية صلبة.

6. Confidence Arbitration Layer
- إذا confidence منخفض في أي نقطة حرجة: يعيد التوليد عبر مسار بديل وليس best-effort.

---

## 11) الفرق بين minimax_analysis وclaude_analysis ونتيجة هذا التحقيق

- minimax_analysis: يغطي صورة عميقة جيدة جداً، لكن بعض النقاط كانت "اتجاه مرغوب" أكثر من "واقع كود حرفي".
- claude_analysis: قريب من الواقع بشكل أكبر في كثير من المسارات.
- codex_analysis (هذا الملف): مبني على قراءة مباشرة للكود الحالي مع تثبيت الفجوات/التحسينات بسياق تنفيذي.

---

## 12) قرارات تنفيذية جاهزة (مختصرة)

1. اعتمد System Message لكل وكيل فوراً.
2. أدخل RAG إلى Agent 0 فوراً.
3. أنشئ Arabic-only deterministic final composer.
4. شدّد quality gates: لا Final Brief بدون pass كامل.
5. وسّع أدوات التفكير القانوني الحتمي (procedural + contradiction + citation engines).

بهذه الخطة، يمكن رفع جودة المخرجات بشكل ملموس وتحويلها إلى صياغة عربية قانونية نهائية بدون JSON/English في المخرجات القضائية النهائية.
