# تقرير التحقيق الشامل في نظام تحليل القضايا القانونية

## الملخص التنفيذي

هذا التقرير يمثل نتيجة تحقيق معمق في البنية التقنية لنظام تحليل القضايا القانونية (Legal Case Analysis System) المبني على إطار عمل Laravel 11. يهدف التقرير إلى فهم آلية عمل النظام الحالية، وتحديد نقاط الضعف في جودة المخرجات، واقتراح تحسينات شاملة.

---

## القسم الأول: فهم البنية العامة للنظام

### 1.1 نظرة عامة على مراحل التحليل

يتكون النظام من ثلاث مراحل رئيسية (Phases) تضم 13 وكيلاً (Agents):

| المرحلة | الوكلاء | الوصف |
|---------|---------|-------|
| **المرحلة الأولى** | الوكيل 0 | تحليل القضية واستخراج القوانين المطلوبة من قاعدة بيانات RAG |
| **المرحلة الثانية** | الوكلاء 1-9 | خط أنابيب التحليل القانوني الشامل |
| **المرحلة الثالثة** | الوكلاء 10-12 | التحكيم القضائي والمراجعة |

### 1.2 تدفق البيانات الأساسي

```
حالة المحكمة (Intake) ← المستندات ← RAG Database
                    ↓
         ┌─────────────────────────────────────┐
         │         Phase 1 (الوكيل 0)          │
         │   تحليل القضية وتحديد الأنظمة        │
         └─────────────────────────────────────┘
                    ↓
         ┌─────────────────────────────────────┐
         │   Phase 2 (الوكلاء 1-9)            │
         │ 1. القائد القانوني                   │
         │ 2. مدير الأدلة                       │
         │ 3. سلسلة الحفظ                       │
         │ 4. الجدول الزمني                     │
         │ 5. مدير القانون                      │
         │ 6. مطابق الأنظمة                     │
         │ 7. الاستراتيجي الدفاعي               │
         │ 8. الصائغ القانوني                   │
         │ 9. ضبط الجودة                       │
         └─────────────────────────────────────┘
                    ↓
         ┌─────────────────────────────────────┐
         │   Phase 3 (الوكلاء 10-12)           │
         │ 10. القاضي                          │
         │ 11. محامي الخصم                     │
         │ 12. وكيل التحصين                    │
         └─────────────────────────────────────┘
```

---

## القسم الثاني: نظام RAG (Retrieval-Augmented Generation)

### 2.1 آلية عمل RAG في النظام

نظام RAG يعمل كمكتبة للأنظمة القانونية السعودية ويستخدم تقنية المتجهات الدلالية (Semantic Vectors) للبحث عن المواد القانونية ذات الصلة بالقضية.

#### 2.1.1 مكونات نظام RAG

| المكون | الملف | الوظيفة |
|--------|-------|---------|
| **EmbeddingService** | `app/Services/RAG/EmbeddingService.php` | تحويل النصوص إلى متجهات عددية (1536 بُعد) |
| **VectorSearchService** | `app/Services/RAG/VectorSearchService.php` | البحث الدلالي باستخدام التشابه الجيبي |
| **LawProcessingService** | `app/Services/RAG/LawProcessingService.php` | معالجة ملفات القوانين واستخراج المواد |

#### 2.1.2 عملية إنشاء التضمينات (Embeddings)

```php
// من EmbeddingService.php:27
public function generateEmbedding(string $text): array
{
    // يستخدم OpenRouter API مع نموذج text-embedding-3-small
    $response = Http::post("{$this->baseUrl}/embeddings", [
        'model' => 'openai/text-embedding-3-small',
        'input' => $text,
    ]);
    
    return [
        'embedding' => $embedding,  // مصفوفة من 1536 عنصرًا
        'model' => 'openai/text-embedding-3-small',
    ];
}
```

#### 2.1.3 عملية البحث الدلالي

```php
// من VectorSearchService.php:19
public function search(string $query, int $topK = 20, float $minSimilarity = 0.70): array
{
    // 1. إنشاء embedding للاستعلام
    $queryEmbeddingData = $this->embeddingService->generateEmbedding($query);
    $queryVector = $queryEmbeddingData['embedding'];
    
    // 2. جلب جميع التضمينات من قاعدة البيانات
    $embeddings = LawEmbedding::with(['lawArticle.lawRegistry'])->get();
    
    // 3. حساب التشابه الجيبي (Cosine Similarity)
    foreach ($embeddings as $embedding) {
        $similarity = LawEmbedding::cosineSimilarity($queryVector, $articleVector);
        
        if ($similarity >= $minSimilarity) {  // 0.70 عتبة افتراضية
            $results[] = [
                'article' => $embedding->lawArticle,
                'similarity' => $similarity,
            ];
        }
    }
    
    // 4. ترتيب النتائج حسب التشابه
    return array_slice($results, 0, $topK);
}
```

### 2.2 كيف يتدخل RAG في تحليل القضايا

#### 2.2.1 مرحلة تحديد الأنظمة المطلوبة (الوكيل 0)

```php
// من Phase1AnalysisAgent.php:49
public function execute(LegalCase $case): array
{
    // بناء السياق من النصوص والمستندات
    $context = $this->buildContext($case);
    
    // بناءPrompt مع قالب المخرجات
    $prompt = $this->promptBuilder->buildPromptForAgent(0, $context);
    
    // استدعاء LLM
    $result = $this->openRouter->complete($model, $messages, $temperature, $maxTokens);
    
    // تحليل القوانين المطلوبة من المخرجات
    $requiredLaws = $this->parseRequiredLaws($result['content']);
}
```

#### 2.2.2 مرحلة بناء سياق القوانين (الوكلاء 1-9)

```php
// من Phase2BaseAgent.php:295
protected function buildLawContextFromLibrary(LegalCase $case): string
{
    // جلب القوانين المطلوبة من المرحلة الأولى
    $requiredNames = $case->requiredLaws()->pluck('law_name')->all();
    
    // جلب المواد من قاعدة البيانات
    $laws = LawRegistry::query()
        ->where(function ($q) use ($requiredNames) {
            foreach ($requiredNames as $name) {
                $q->orWhere('name', 'like', '%' . $name . '%');
            }
        })
        ->with(['articles'])
        ->get();
    
    // بناء نص السياق
    $out = "## Law Library (مكتبة الأنظمة والقوانين)\n\n";
    foreach ($laws as $law) {
        $out .= "### {$law->name}\n\n";
        foreach ($law->articles as $article) {
            $out .= "المادة {$article->article_number}: {$article->article_text}\n\n";
        }
    }
    
    return $out;
}
```

### 2.3 مشاكل RAG الحالية وحدوده

#### 2.3.1 القيود الحرجة

| المشكلة | التأثير | الموقع |
|---------|--------|--------|
| **حد أقصى 50,000 حرف** | فقدان مواد قانونية مهمة في القضايا المعقدة | `Phase2BaseAgent.php:21` |
| **عدم وجود تحديث ديناميكي** | لا يتم تحديث البحث أثناء تشغيل الوكيل | التصميم الكامل |
| **نموذج_embedding واحد** | لا يوجد تخصيص لنوع القانون | `EmbeddingService.php:21` |
| **عتبة تشابه ثابتة 0.70** | فقدان مواد ذات صلة في النصوص المتخصصة | `VectorSearchService.php:19` |

#### 2.3.2 الفجوة الحرجة: عدم وجود بحث RAG تفاعلي

**المشكلة الرئيسية:** الوكيل 0 (تحليل القضية) لا يستدعيVectorSearchService مباشرة. وبدلاً من ذلك، يعتمد على LLM لتحديد القوانين المطلوبة دون البحث الفعلي في قاعدة بيانات RAG.

```php
//Phase1AnalysisAgent.php:116 - السياق المبني بدون بحث RAG
protected function buildContext(LegalCase $case): string
{
    $parts = ["## Intake\n\n{$case->intake_text}"];
    
    foreach ($case->documents as $doc) {
        $content = file_get_contents($path);
        $parts[] = "## Document: {$doc->filename}\n\n" . mb_substr($content, 0, 50000);
    }
    
    // لا يوجد استدعاء لـ VectorSearchService للبحث في الأنظمة!
    return implode("\n\n---\n\n", $parts);
}
```

---

## القسم الثالث: نظام الرسائل والوكلاء

### 3.1 هيكل الـ System Prompt

#### 3.1.1 مكونات الـ Prompt

```php
// من PromptBuilder.php:205
public function buildPromptForAgent(int $agentNumber, string $context): string
{
    $parts = [];
    
    // 1. القواعد العامة (من SKILL.md)
    $parts[] = $this->extractGeneralRules();
    
    // 2. قسم الوكيل المحدد
    $parts[] = $this->extractAgentSection($agentNumber);
    
    // 3. قواعد مكافحة الهلوسة (للوكلاء 3,5,6,7,8,9)
    if (in_array($agentNumber, [3, 5, 6, 7, 8, 9])) {
        $parts[] = $this->extractAntiHallucinationRules();
    }
    
    // 4. قالب المخرجات
    $parts[] = $this->buildOutputTemplate($agentNumber);
    
    // 5. قيد السياق (للوكلاء 5,6,7,8,9)
    if (in_array($agentNumber, [5, 6, 7, 8, 9])) {
        $parts[] = "---\n## ⚠️ قيد إلزامي\n\nلا يجوز الاستشهاد بأي نظام أو مادة غير مذكورة في السياق أدناه...";
    }
    
    // 6. سياق القضية
    $parts[] = "---\n## سياق القضية\n\n{$context}";
    
    return implode("\n\n---\n\n", $parts);
}
```

### 3.2 القواعد المحددة في SKILL.md

```markdown
## القواعد العامة (من SKILL.md)

### اللغة والتنسيق
- جميع المخرجات يجب أن تكون بالعربية القانونية الرسمية (العربية الفصحى القانونية)
- المخرجات المهيكلة (JSON/JSONL) تستخدم مفاتيح إنجليزية مع قيم عربية

### معيار الاستشهاد المزدوج (إلزامي)
- `CASE:{chunk_ref}` - الإشارة إلى جزء من الأدلة
- `LAW:{statute_ref}` - الإشارة إلى مادة قانونية
- أي فقرة تفتقر للاستشهاد المزدوج تحمل علامة: `⚠️ غير مُسنَّدة`

### حد الثقة الأدنى
- الحد الأدنى للتطابق: **0.70 (70%)**
- التطابقات أقل من 0.70 يجب وضع علامة لإعادة المطابقة
```

### 3.3 هل هناك System Message لكل وكيل؟

**الإجابة: لا يوجد System Message منفصل لكل وكيل.**

النظام الحالي يستخدم نهج "Prompt Engineering" حيث يتم بناء Prompt جديد لكل وكيل يتضمن:
1. القواعد العامة من SKILL.md
2. تعليمات الوكيل المحددة
3. قالب المخرجات المتوقع
4. سياق القضية

**المشكلة:** هذا النهج يفتقر إلى "الذاكرة المستمرة" للوكيل عبر الطلبات المتعددة. كل طلب يُعامل كأنه طلب جديد تمامًا.

---

## القسم الرابع: تحليل مشاكل جودة المخرجات

### 4.1 مشاكل المخرجات المحددة

#### 4.1.1 عدم وجود تفاصيل كافية في المخرجات

**السبب الجذري:** حدود الأحرف المفروضة على السياق

```php
// Phase2BaseAgent.php:113
private const TOTAL_CONTEXT_BUDGET_CHARS = 240_000;  // ~60K token

// Phase2BaseAgent.php:21
private const LAW_CONTEXT_MAX_CHARS = 50_000;       // فقط 50KB للقوانين!
```

**التأثير:** 
- في القضايا المعقدة، يتم اقتطاع материал القانونية المهمة
- الوكيل 8 (الصائغ القانوني) يحتاج سياقًا كاملاً لكنه يحصل على نسخة مختصرة

#### 4.1.2 ظهور JSON أو English في المخرجات العربية

**السبب:** قالب المخرجات يتضمن English keys

```php
// PromptBuilder.php:315
private function templateAgent1(): string
{
    return <<<'TEMPLATE'
## قالب المخرجات المطلوب — Agent 1

### `01_acceptance_criteria.json`
```json
{
  "min_confidence": 0.70,
  "dual_citations_required": true,
  "max_unsupported_paragraphs": 0,
  "defense_tiers": ["primary", "alternative", "consequential"],
  "preamble_required": true,
  "no_abrogated_articles": true,
  "ai_erasure_required": true
}
```
```

**الحل المطلوب:** تغيير المفاتيح إلى العربية أو فصل JSON عن النص العربي

### 4.2 آليات التحقق من الجودة الحالية

#### 4.2.1 التحقق من المخرجات (OutputValidator)

```php
// OutputValidator.php - التحققات المتاحة
- validateJsonl()           // التحقق من صحة JSONL
- validateStatuteIds()      // التحقق من وجود statute_id في الفهرس
- validateQuotedText()      // التحقق من أن النص المقتبس مطابق للمصدر
- validateNoAbrogated()     // التحقق من عدم الاستشهاد بمواد ملغاة
- validateConfidenceFloor() // التحقق من حد الثقة الأدنى
- validateBriefCitations()  // التحقق من استشهادات المذكرة
- validateBriefStructure()  // التحقق من وجود الأقسام الإلزامية
```

#### 4.2.2 التصحيح الذاتي (Self-Correction)

```php
// Phase2BaseAgent.php:357
protected function executeWithSelfCorrection(LegalCase $case, string $prompt...): array
{
    for ($attempt = 1; $attempt <= static::MAX_CORRECTION_ATTEMPTS; $attempt++) {
        $result = $this->executeWithStreaming($case, $currentPrompt, ...);
        
        // استخراج درجة الثقة
        $confidenceScore = $this->extractConfidenceScore($output);
        
        // التحقق من الانتهاكات
        $violations = $this->validateOutput($output, $case);
        
        if (empty($violations)) {
            return $result;  // قبلول المخرجات
        }
        
        // إضافة سياق التصحيح لل محاولة التالية
        $errorContext .= "Violations found: " . implode('; ', $violations);
    }
}
```

### 4.3 لماذا لا تعمل الآليات الحالية بشكل كافٍ؟

| الآلية | المشكلة |
|--------|---------|
| **Self-Correction** | يعتمد على regex لاستخراج الثقة - غير موثوق |
| **OutputValidator** | يعمل بعد الإنتاج - لا يمنع الأخطاء مسبقًا |
| **Confidence Threshold** | 0.70 منخفض جدًا للقضايا القانونية |
| **Anti-Hallucination** | قواعد جيدة لكن لا يوجد تحقق فعلي |

---

## القسم الخامس: خطة التحسين الشاملة

### 5.1 تحسينات البنية الأساسية

#### 5.1.1 إضافة بحث RAG تفاعلي في المرحلة الأولى

**الحالة الحالية:** الوكيل 0 لا يستدعي VectorSearchService

**الحالة المطلوبة:**

```php
// Phase1AnalysisAgent.php - إضافة البحث
public function execute(LegalCase $case): array
{
    // 1. بناء السياق الأساسي
    $context = $this->buildContext($case);
    
    // 2. البحث في RAG للحصول على القوانين ذات الصلة
    $vectorSearch = app(VectorSearchService::class);
    
    // استخراج الكلمات المفتاحية من القضية
    $keywords = $this->extractKeywords($case->intake_text);
    
    // بحث متعدد الاستعلامات
    $relevantLaws = $vectorSearch->searchMultiple($keywords, 10, 0.65);
    
    // 3. إضافة القوانين المستردة للسياق
    $lawContext = $this->formatLawContext($relevantLaws);
    $context .= "\n\n---\n\n## الأنظمة والقوانين ذات الصلة\n\n" . $lawContext;
    
    // 4. بناء Prompt مع السياق الموسع
    $prompt = $this->promptBuilder->buildPromptForAgent(0, $context);
    
    // ... rest of the code
}
```

### 5.2 تحسينات جودة المخرجات

#### 5.2.1 فصل JSON عن النص العربي

**المشكلة:** المخرجات تحتوي على مفاتيح إنجليزية وJSON مmixed مع العربية

**الحل:** إنشاء قالبين منفصلين

```php
// PromptBuilder.php - قالب محسّن
private function templateAgent1(): string
{
    return <<<'TEMPLATE'
## قالب المخرجات المطلوب — Agent 1

أنتج ملفين:

### `01_lead_plan.md`
الخطة الرئيسية باللغة العربية تتضمن:
1. ملخص القضية (الأطراف، الدعاوى، المطالبات)
2. نطاق التحليل (المجالات القانونية المعنية)
3. التعليمات الاستراتيجية للوكلاء المتابعين

### `01_acceptance_criteria.md`
معايير القبول كملف Markdown:

**معايير القبول:**
- درجة الثقة الدنيا: 0.70 (70%)
- الاستشهاد المزدوج مطلوب: نعم
- الحد الأقصى للفقرات غير المستشهد بها: 0
- مستويات الدفاع المطلوبة: الأصلي - البديل - الاحتياطي
- البسملة مطلوبة: نعم
- عدم الاستشهاد بمواد ملغاة: نعم
- مسح الذكاء الاصطناعي مطلوب: نعم
TEMPLATE;
}
```

#### 5.2.2 إضافة Arabic Output Formatter

```php
// جديد: App/Services/Output/ArabicOutputFormatter.php

namespace App\Services\Output;

class ArabicOutputFormatter
{
    /**
     * تحويل أي مخرجات إلى Arabic-first format
     */
    public static function format(string $content): string
    {
        // 1. إزالة أي JSON متبقي
        $content = self::extractJsonFromMarkdown($content);
        
        // 2. تحويل مفاتيح JSON الإنجليزية إلى عربية
        $content = self::arabicizeJsonKeys($content);
        
        // 3. تنسيق التاريخ والهجري
        $content = self::formatArabicDates($content);
        
        // 4. تحسين ترقيم المواد
        $content = self::formatArticleNumbers($content);
        
        return $content;
    }
    
    /**
     * استخراج وتحويل JSON إلى جداول عربية
     */
    private static function extractJsonFromMarkdown(string $content): string
    {
        //Find all JSON blocks
        preg_match_all('/```json\s*([\s\S]*?)\s*```/', $content, $matches);
        
        foreach ($matches[1] as $json) {
            $data = json_decode($json, true);
            $table = self::jsonToArabicTable($data);
            $content = str_replace("```json\n{$json}\n```", $table, $content);
        }
        
        return $content;
    }
}
```

### 5.3 تحسينات نظام الوكلاء

#### 5.3.1 إضافة System Message لكل وكيل

**الحالة المطلوبة:** بدلاً من بناء Prompt جديد كل مرة، نستخدم System Message ثابت

```php
// App/Services/Agents/BaseAgent.php - إضافة system message

abstract class BaseAgent
{
    abstract public function systemMessage(): string;
    
    public function execute(LegalCase $case): array
    {
        $systemPrompt = $this->systemMessage();
        $userPrompt = $this->buildPrompt($case);
        
        // استخدام النظام والـ User messages
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];
        
        return $this->openRouter->complete($this->model, $messages, ...);
    }
}
```

#### 5.3.2 إنشاء وكلاء متخصصين للتحسين

```php
// App/Services/Agents/ArabicRefinementAgent.php

class ArabicRefinementAgent extends BaseAgent
{
    public function agentNumber(): int { return 13; }
    public function agentName(): string { return 'Arabic Refinement'; }
    
    public function systemMessage(): string
    {
        return <<<'SYSTEM'
أنت خبير في اللغة العربية القانونية الرسمية (العربية الفصحى).
مهمتك هي تحسين وتحرير النصوص القانونية العربية.

القواعد:
1. استخدم فقط المصطلحات القانونية العربية المعتمدة
2. تجنب أي كلمات إنجليزية أو فرنسية
3. حول جميع الأرقام إلى أرقام هربية عربية
4. استخدم أسلوب القضاء السعودي الرسمي
5. أضف البسملة "بسم الله الرحمن الرحيم" إذا لم تكن موجودة
6. لا تضيف أي معلومات جديدة - فقط حسّن الصياغة

مثال للتحسين:
قبل: "The defendant violated Article 80"
بعد: "إن المدعى عليه قد أخل بالالتزاماته الواردة في المادة الثمانون"
SYSTEM;
    }
}
```

### 5.4 تحسينات RAG

#### 5.4.1 بحث RAG متعدد المستويات

```php
// تحسين VectorSearchService.php

class VectorSearchService
{
    /**
     * بحث هرمي: عام ← متوسط ← محدد
     */
    public function hierarchicalSearch(string $query, array $caseContext): array
    {
        // المستوى 1: بحث عام في جميع الأنظمة
        $broadResults = $this->search($query, 20, 0.65);
        
        // المستوى 2: بحث متوسط في الأنظمة ذات الصلة
        $relevantLaws = array_map(fn($r) => $r['article']->lawRegistry, $broadResults);
        $mediumResults = $this->searchInLaws($relevantLaws, $query, 15, 0.70);
        
        // المستوى 3: بحث محدد في المواد
        $specificResults = $this->searchSpecificArticles($mediumResults, $query, 10, 0.75);
        
        return array_merge($broadResults, $mediumResults, $specificResults);
    }
}
```

#### 5.4.2 تحديث ديناميكي للسياق

```php
// Phase2BaseAgent.php - إضافة سياق ديناميكي

protected function buildContext(LegalCase $case): string
{
    // ... الكود الحالي ...
    
    // إضافة بحث ديناميكي للمصطلحات الجديدة
    if ($this->needsLawLibrary()) {
        // استخراج المصطلحات القانونية من سياق الحالة
        $legalTerms = $this->extractLegalTerms($case->intake_text);
        
        // البحث عن مواد إضافية
        $vectorSearch = app(VectorSearchService::class);
        $additionalArticles = $vectorSearch->searchMultiple($legalTerms, 5, 0.60);
        
        if (!empty($additionalArticles)) {
            $parts[] = $this->formatAdditionalLaws($additionalArticles);
        }
        
        $lawContext = $this->buildLawContextFromLibrary($case);
        if ($lawContext !== '') {
            $parts[] = $lawContext;
        }
    }
    
    return implode("\n\n---\n\n", array_filter($parts, fn ($p) => $p !== ''));
}
```

### 5.5 جدول التنفيذ المقترح

| الأولوية | التحسين | الجهد | التأثير |
|----------|---------|-------|--------|
| **عالية** | إضافة بحث RAG تفاعلي للوكيل 0 | متوسط | عالي |
| **عالية** | فصل JSON عن النص العربي | منخفض | عالي |
| **عالية** | إضافة Arabic Refinement Agent | متوسط | عالي |
| **متوسطة** | رفع حد السياق للقوانين | منخفض | متوسط |
| **متوسطة** | إضافة System Messages للوكلاء | عالي | عالي |
| **متوسطة** | بحث RAG هرمي | متوسط | متوسط |
| **منخفضة** | تحسين عتبة الثقة | منخفض | منخفض |

---

## القسم السادس: الخلاصة والتوصيات

### 6.1 ملخص المشاكل الجوهرية

1. **غياب البحث الفعلي في RAG:** الوكيل 0 لا يستعلم قاعدة بيانات RAG، مما يؤدي إلى فقدان المواد القانونية ذات الصلة

2. **حدود السياق الصارمة:** 50KB للقوانين غير كافية للقضايا المعقدة

3. **مخرجات مختلطة اللغات:** JSON إنجليزي داخل نصوص عربية

4. **absence of system messages:** كل طلب يُبنى من الصفر بدون "ذاكرة" مستمرة

5. **آليات التحقق لاحقة:** التصحيح يتم بعد الإنتاج بدلاً من.prevent الأخطاء

### 6.2 التوصيات الفورية (للنفيذ السريع)

1. **تفعيل بحث RAG في المرحلة الأولى** - سيحدث تحسنًا فوريًا في تحديد القوانين

2. **إنشاء Arabic Output Formatter** - سيضمن مخرجات عربية خالصة

3. **رفع LAW_CONTEXT_MAX_CHARS** - من 50,000 إلى 100,000 حرف

### 6.3 التوصيات طويلة المدى (لبناء نظام متكامل)

1. **بناء نظام Multi-Agent Specialized** - وكلاء متخصصون لكل مجال قانوني

2. **تطوير RAG Domain-Specific** - نماذج embedding متخصصة لكل نوع قوانين

3. **إنشاء Arabic Legal Knowledge Graph** - قاعدة معرفية قانونية عربية

---

## الملاحق

### الملفات المحققة

| الملف | المسار |
|-------|--------|
| VectorSearchService | `app/Services/RAG/VectorSearchService.php` |
| EmbeddingService | `app/Services/RAG/EmbeddingService.php` |
| LawProcessingService | `app/Services/RAG/LawProcessingService.php` |
| LegalOrchestrator | `app/Services/Orchestration/LegalOrchestrator.php` |
| PromptBuilder | `app/Services/Orchestration/PromptBuilder.php` |
| OutputValidator | `app/Services/Orchestration/OutputValidator.php` |
| Phase2BaseAgent | `app/Services/Agents/Phase2/Phase2BaseAgent.php` |
| Phase1AnalysisAgent | `app/Services/Agents/Phase1AnalysisAgent.php` |
| AgentDefinitions | `app/Services/AgentDefinitions.php` |
| SKILL.md | `.agent/skills/legal-counsel/SKILL.md` |
| config/legal.php | `config/legal.php` |
| LegalCase Model | `app/Models/LegalCase.php` |

---

*تم إنشاء هذا التقرير بناءً على تحليل شامل للكود المصدري في مشروع Laravel بتاريخ 2026-03-26*
