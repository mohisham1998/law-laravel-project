<?php

namespace App\Services;

use App\Models\LegalCase;
use App\Services\OpenRouter\OpenRouterService;
use Illuminate\Support\Facades\Log;

class InputAuditService
{
    public function __construct(
        protected OpenRouterService $openRouter,
    ) {}

    /**
     * Audit a case's input completeness.
     *
     * @param LegalCase $case The case to audit
     * @param array{
     *     text?: array<string, string>,
     *     files?: array<string>,
     *     selections?: array<string, string>
     * }|null $inlineInputs User-provided inline inputs
     * @return array{
     *     score: int,
     *     projected_score: int,
     *     summary: string|null,
     *     feedback: array{
     *         required: array<int, array{label: string, reason: string, input_type: string, options: array<int, array{value: string, label: string}>|null}>,
     *         recommended: array<int, array{label: string, reason: string, input_type: string, options: array<int, array{value: string, label: string}>|null}>,
     *         optional: array<int, array{label: string, reason: string, input_type: string, options: array<int, array{value: string, label: string}>|null}>
     *     }
     * }
     */
    public function audit(LegalCase $case, ?array $inlineInputs = null): array
    {
        try {
            $prompt = $this->buildAuditPrompt($case, $inlineInputs);
            
            $model = config('openrouter.default_model', 'anthropic/claude-3.5-sonnet');
            
            Log::info('InputAuditService: Starting audit', [
                'case_id' => $case->id,
                'model' => $model,
                'prompt_length' => strlen($prompt),
            ]);
            
            $response = $this->openRouter->complete($model, [
                ['role' => 'system', 'content' => 'You are a legal case input quality analyst. Respond in Arabic with structured JSON in a markdown code block.'],
                ['role' => 'user', 'content' => $prompt],
            ]);

            $content = $response['content'];
            
            Log::info('InputAuditService: Received response', [
                'case_id' => $case->id,
                'content_length' => strlen($content),
            ]);
            
            // Extract JSON from markdown code block via regex
            $json = $this->extractJsonFromContent($content);
            
            if (!$json) {
                Log::warning('InputAuditService: Failed to parse JSON from LLM response', [
                    'case_id' => $case->id,
                    'content' => $content,
                ]);
                throw new \RuntimeException('Failed to parse audit response');
            }

            $data = json_decode($json, true);
            
            if (!is_array($data)) {
                Log::warning('InputAuditService: Invalid JSON structure from LLM', [
                    'case_id' => $case->id,
                    'json' => $json,
                ]);
                throw new \RuntimeException('Invalid audit response structure');
            }

            // Validate and apply scoring caps
            return $this->validateAndCapScore($data, $case, $inlineInputs);
        } catch (\Exception $e) {
            Log::error('InputAuditService: Exception during audit', [
                'case_id' => $case->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Build the audit prompt with all case inputs.
     */
    protected function buildAuditPrompt(LegalCase $case, ?array $inlineInputs): string
    {
        $taskType = $this->determineTaskType($case);
        
        $prompt = "قم بتحليل اكتمال مدخلات القضية التالية وتحديد ما يحتاجه المحامي لإعداد دفاع قوي.\n\n";
        $prompt .= "## معلومات القضية\n";
        $prompt .= "- عنوان القضية: {$case->title}\n";
        $prompt .= "- نوع القضية: {$taskType}\n\n";
        
        $prompt .= "## نص الاستشارة (intake_text)\n";
        $prompt .= $case->intake_text ?: "(غير موجود)\n";
        $prompt .= "\n\n";

        // Include inline text inputs if provided
        if (!empty($inlineInputs['text'])) {
            $prompt .= "## معلومات إضافية مقدمة من المستخدم\n";
            foreach ($inlineInputs['text'] as $label => $value) {
                $prompt .= "- {$label}: {$value}\n";
            }
            $prompt .= "\n";
        }

        // Document metadata
        $documents = $case->documents;
        if ($documents->isNotEmpty()) {
            $prompt .= "## المستندات المرفقة\n";
            foreach ($documents as $doc) {
                $prompt .= "- {$doc->filename} ({$doc->mime_type}, {$doc->file_size} bytes)\n";
            }
            $prompt .= "\n";

            // Include inline file inputs if provided
            if (!empty($inlineInputs['files'])) {
                $inlineDocIds = $inlineInputs['files'];
                $inlineDocs = $documents->filter(fn($doc) => in_array($doc->id, $inlineDocIds));
                if ($inlineDocs->isNotEmpty()) {
                    $prompt .= "## مستندات إضافية مرفوعة من المستخدم\n";
                    foreach ($inlineDocs as $doc) {
                        $prompt .= "- {$doc->filename} ({$doc->mime_type}, {$doc->file_size} bytes)\n";
                    }
                    $prompt .= "\n";
                }
            }
        }

        // Required laws
        $requiredLaws = $case->requiredLaws;
        if ($requiredLaws->isNotEmpty()) {
            $prompt .= "## القوانين المطلوبة\n";
            foreach ($requiredLaws as $law) {
                $prompt .= "- {$law->law_title}\n";
            }
            $prompt .= "\n";
        }

        // Phase 1 output (existing analysis)
        $phase1Output = $case->outputs()->where('agent_number', 0)->first();
        if ($phase1Output && $phase1Output->content) {
            $prompt .= "## ناتج المرحلة الأولى (التحليل الأولي)\n";
            $prompt .= substr($phase1Output->content, 0, 2000);
            if (strlen($phase1Output->content) > 2000) {
                $prompt .= "\n...(مختصر)";
            }
            $prompt .= "\n\n";
        }

        // Include selections if provided
        if (!empty($inlineInputs['selections'])) {
            $prompt .= "## خيارات محددة من المستخدم\n";
            foreach ($inlineInputs['selections'] as $label => $value) {
                $prompt .= "- {$label}: {$value}\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "## المطلوب\n";
        $prompt .= "قدم تقييمًا شاملاً يتضمن:\n";
        $prompt .= "1. درجة اكتمال من 0-100 (100 = جاهز للمرحلة الثانية)\n";
        $prompt .= "2. ملخص موجز (2-3 جمل بالعربية) لتقييم الحالة العامة\n";
        $prompt .= "3. قائمة反馈 مفصلة مصنفة حسب الأولوية:\n";
        $prompt .= "   - مطلوب: عناصر ضرورية يجب توفرها قبل المتابعة (إذا غابت، الدرجة القصوى 60)\n";
        $prompt .= "   - موصى به: عناصر يُنصح بإضافتها لتحسين جودة المخرجات (إذا غابت، الدرجة القصوى 85)\n";
        $prompt .= "   - اختياري: عناصر اختيارية ترفع الجودة进一步 (النطاق 86-100)\n";
        $prompt .= "4. لكل عنصر حدد: label (بالعربية)، reason (سبب الأهمية)، input_type (text/file/selection)، والخيارات المتاحة إن وجدت\n";
        $prompt .= "\nأخرج النتيجة كـ JSON في كود بلوك markdown مثل:\n```json\n{...}\n```";

        return $prompt;
    }

    /**
     * Determine the task type based on case content.
     */
    protected function determineTaskType(LegalCase $case): string
    {
        $intake = strtolower($case->intake_text ?? '');
        
        $criminalKeywords = ['جنائي', 'جريمة', 'سرقة', 'ضرب', 'قتل', 'مخدرات', 'تهريب', 'جرى'];
        $civilKeywords = ['مدني', 'نزاع', 'عقد', 'إيجار', 'ديني', 'ميراث', 'زواج', 'طلاق'];
        $commercialKeywords = ['تجاري', 'شركة', 'تجارة', 'سوق', 'استيراد', 'تصدير'];
        
        foreach ($criminalKeywords as $keyword) {
            if (str_contains($intake, $keyword)) {
                return 'قضية جنائية';
            }
        }
        
        foreach ($civilKeywords as $keyword) {
            if (str_contains($intake, $keyword)) {
                return 'قضية مدنية';
            }
        }
        
        foreach ($commercialKeywords as $keyword) {
            if (str_contains($intake, $keyword)) {
                return 'قضية تجارية';
            }
        }
        
        return 'قضية عامة';
    }

    /**
     * Extract JSON from markdown code block.
     */
    protected function extractJsonFromContent(string $content): ?string
    {
        // Try to extract JSON from markdown code block
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            return $matches[1];
        }
        
        // Try to extract any JSON object from the content
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            return $matches[0];
        }
        
        return null;
    }

    /**
     * Validate response structure and apply scoring caps.
     */
    protected function validateAndCapScore(array $data, LegalCase $case, ?array $inlineInputs): array
    {
        $requiredFeedback = $data['feedback']['required'] ?? [];
        $recommendedFeedback = $data['feedback']['recommended'] ?? [];
        
        // Apply scoring caps
        $score = $data['score'] ?? 50;
        $projectedScore = $data['projected_score'] ?? 100;
        
        // Cap at 60 if required items are missing (not addressed via inline inputs)
        $providedLabels = [];
        if (!empty($inlineInputs['text'])) {
            $providedLabels = array_merge($providedLabels, array_keys($inlineInputs['text']));
        }
        if (!empty($inlineInputs['selections'])) {
            $providedLabels = array_merge($providedLabels, array_keys($inlineInputs['selections']));
        }
        
        $hasUnaddressedRequired = false;
        foreach ($requiredFeedback as $item) {
            if (!in_array($item['label'], $providedLabels)) {
                $hasUnaddressedRequired = true;
                break;
            }
        }
        
        if ($hasUnaddressedRequired && $score > 60) {
            $score = 60;
        }
        
        // Cap at 85 if recommended items are missing
        $hasUnaddressedRecommended = false;
        foreach ($recommendedFeedback as $item) {
            if (!in_array($item['label'], $providedLabels)) {
                $hasUnaddressedRecommended = true;
                break;
            }
        }
        
        if ($hasUnaddressedRecommended && $score > 85) {
            $score = 85;
        }

        return [
            'score' => min(100, max(0, (int) $score)),
            'projected_score' => min(100, max(0, (int) $projectedScore)),
            'summary' => $data['summary'] ?? null,
            'feedback' => [
                'required' => array_values($requiredFeedback),
                'recommended' => array_values($recommendedFeedback),
                'optional' => array_values($data['feedback']['optional'] ?? []),
            ],
        ];
    }
}
