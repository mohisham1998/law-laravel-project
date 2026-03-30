<?php

namespace App\Services\Agents;

use App\Models\CaseOutput;
use App\Models\LegalCase;
use App\Models\RequiredLaw;
use App\Services\CaseEventService;
use App\Services\LLM\LLMServiceInterface;
use App\Services\Orchestration\GateValidator;
use App\Services\Orchestration\PromptBuilder;
use App\Services\RAG\EmbeddingService;
use App\Services\RAG\VectorSearchService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\LawRegistry;

class Phase1AnalysisAgent extends BaseAgent
{
    public function __construct(
        PromptBuilder $promptBuilder,
        GateValidator $gateValidator,
        protected LLMServiceInterface $openRouter,
        protected ?CaseEventService $eventService = null,
        protected ?VectorSearchService $vectorSearch = null,
    ) {
        parent::__construct($promptBuilder, $gateValidator);
    }

    public function agentNumber(): int
    {
        return 0;
    }

    public function agentName(): string
    {
        return 'Phase 1 Analysis';
    }
    
    public function agentNameAr(): string
    {
        return 'تحليل القضية';
    }

    public function validateGate(LegalCase $case): bool
    {
        return $this->gateValidator->validatePhase1Gate($case);
    }

    /**
     * @return array{content: string, required_laws: array<int, array{law_name: string, reason: string}>}
     */
    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);

        // RAG search: enrich context with relevant statute candidates before law identification
        $ragSection = $this->buildRagContext($case);
        if (!empty($ragSection)) {
            $context .= "\n\n---\n\n" . $ragSection;
        }

        $prompt = $this->promptBuilder->buildPromptForAgent(0, $context);

        // Build system + user messages for persona anchoring
        $systemPrompt = $this->promptBuilder->buildSystemPrompt(0);
        $messages = !empty($systemPrompt)
            ? [['role' => 'system', 'content' => $systemPrompt], ['role' => 'user', 'content' => $prompt]]
            : [['role' => 'user', 'content' => $prompt]];

        $model = $case->modelForAgent($this->agentNumber());
        $agentConfig = config('legal.agents.0', []);
        $temperature = $agentConfig['temperature'] ?? 0.3;
        $maxTokens = $agentConfig['max_tokens'] ?? 4096;

        Log::debug('Agent 0 LLM call', [
            'case_id' => $case->id,
            'model' => $model,
            'message_roles' => array_column($messages, 'role'),
            'has_rag' => !empty($ragSection),
        ]);

        // Use streaming if event service is available
        if ($this->eventService) {
            $onChunk = $this->eventService->createStreamCallback(
                $case->id,
                $this->agentNumber(),
                $this->agentNameAr()
            );

            $result = $this->openRouter->completeStream($model, $messages, $onChunk, $temperature, $maxTokens);

            // Flush any remaining chunks
            $this->eventService->flushChunkBuffer($case->id, $this->agentNumber(), $this->agentNameAr());
        } else {
            $result = $this->openRouter->complete($model, $messages, $temperature, $maxTokens);
        }

        $requiredLaws = $this->parseRequiredLaws($result['content']);
        foreach ($requiredLaws as $law) {
            // Look up in RAG law registry by name
            $registry = LawRegistry::where('name', 'like', '%' . $law['law_name'] . '%')->first();

            RequiredLaw::create([
                'case_id' => $case->id,
                'law_name' => $law['law_name'],
                'reason' => $law['reason'],
                'is_uploaded' => false,
                'law_registry_id' => $registry?->id,
                'subject_area' => $law['subject_area'] ?? $registry?->category ?? null,
            ]);
        }

        $outputPath = "cases/{$case->id}/outputs/00_required_laws.md";
        Storage::disk('local')->put($outputPath, $result['content']);
        try { $fp = \Illuminate\Support\Facades\Storage::disk('local')->path($outputPath); chmod($fp, 0644); chmod(dirname($fp), 0755); } catch (\Throwable) {}

        CaseOutput::create([
            'case_id' => $case->id,
            'agent_number' => 0,
            'filename' => '00_required_laws.md',
            'file_path' => $outputPath,
            'content_type' => 'markdown',
            'content' => $result['content'],
            'file_size' => strlen($result['content']),
        ]);

        return [
            'content' => $result['content'],
            'required_laws' => $requiredLaws,
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
        ];
    }

    protected function buildContext(LegalCase $case): string
    {
        $parts = ["## Intake\n\n{$case->intake_text}"];

        foreach ($case->documents as $doc) {
            $path = Storage::disk('local')->path($doc->file_path);
            if (file_exists($path)) {
                $content = file_get_contents($path);
                $parts[] = "## Document: {$doc->filename}\n\n" . mb_substr($content, 0, 50000);
            }
        }

        return implode("\n\n---\n\n", $parts);
    }

    /**
     * Search RAG database for relevant statute candidates from intake text.
     * Returns a formatted context section with matching law articles.
     */
    protected function buildRagContext(LegalCase $case): string
    {
        if ($this->vectorSearch === null) {
            // Build with the case's provider so Puter users don't hit OpenRouter for embeddings
            try {
                $this->vectorSearch = new VectorSearchService(new EmbeddingService($case->getPuterToken() ?: null));
            } catch (\Throwable) {
                return '';
            }
        }

        $keywords = $this->extractLegalKeywords($case->intake_text);
        if (empty($keywords)) {
            return '';
        }

        try {
            // Search with lower threshold to cast a wider net for law identification
            $results = $this->vectorSearch->search(implode(' ', array_slice($keywords, 0, 10)), topK: 15, minSimilarity: 0.55);

            if (empty($results)) {
                return '';
            }

            $section = "## المواد القانونية المرشحة من قاعدة المعرفة\n\n";
            $section .= "الأنظمة التالية مرشحة من قاعدة البيانات القانونية استناداً إلى الكلمات المفتاحية في النص:\n\n";

            $seen = [];
            foreach ($results as $r) {
                $article = $r['article'] ?? null;
                if (!$article) {
                    continue;
                }
                $lawName = $article->lawRegistry?->name ?? $article->lawFile?->title ?? 'نظام غير محدد';
                $articleNum = $article->article_number ?? '';
                $key = $lawName . '_' . $articleNum;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $section .= "### {$lawName}";
                if ($articleNum) {
                    $section .= " — المادة {$articleNum}";
                }
                $section .= "\n";
                $section .= mb_substr($article->article_text ?? '', 0, 500) . "\n\n";
            }

            return $section;
        } catch (\Throwable $e) {
            Log::warning('Agent 0 RAG search failed', ['case_id' => $case->id, 'error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Extract legal keywords from Arabic intake text for RAG search.
     * Filters words > 3 chars that are likely to match legal statutes.
     */
    protected function extractLegalKeywords(string $text): array
    {
        // Common legal terms to prioritize
        $legalKeywords = [
            'عمل', 'عقد', 'فصل', 'تعسفي', 'أجر', 'مكافأة', 'إشعار', 'تعويض',
            'تجاري', 'شركة', 'مدني', 'مدنية', 'إيجار', 'ملكية', 'ميراث', 'وصية',
            'جنائي', 'جناية', 'جريمة', 'غرامة', 'حبس', 'سجن', 'عقوبة',
            'ضريبة', 'زكاة', 'مالية', 'بنك', 'قرض', 'دين', 'صك',
            'نزاع', 'خلاف', 'دعوى', 'محكمة', 'حكم', 'قضاء', 'أطراف',
            'مدعي', 'مدعى', 'مدعى عليه', 'عقوبات', 'مرافعات', 'تنفيذ',
        ];

        // Split text and extract words
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $extracted = [];

        // First add words that match known legal keywords
        foreach ($words as $word) {
            $clean = preg_replace('/[^\p{Arabic}]/u', '', $word);
            if (mb_strlen($clean) > 2 && in_array($clean, $legalKeywords)) {
                $extracted[] = $clean;
            }
        }

        // Then add other long words
        foreach ($words as $word) {
            $clean = preg_replace('/[^\p{Arabic}]/u', '', $word);
            if (mb_strlen($clean) > 3 && !in_array($clean, $extracted)) {
                $extracted[] = $clean;
            }
        }

        return array_unique(array_filter($extracted));
    }

    /**
     * @return array<int, array{law_name: string, reason: string}>
     */
    protected function parseRequiredLaws(string $content): array
    {
        $laws = [];
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $content, $m)) {
            $json = json_decode(trim($m[1]), true);
            if (isset($json['required_laws']) && is_array($json['required_laws'])) {
                foreach ($json['required_laws'] as $item) {
                    if (!empty($item['law_name'] ?? '')) {
                        $laws[] = [
                            'law_name' => (string) $item['law_name'],
                            'reason' => (string) ($item['reason'] ?? 'Required for case analysis'),
                            'subject_area' => (string) ($item['subject_area'] ?? ''),
                        ];
                    }
                }
            }
        }
        if (empty($laws)) {
            $laws[] = ['law_name' => 'نظام الإثبات', 'reason' => 'Fallback: نظام الإثبات السعودي مطلوب عادةً في القضايا'];
        }
        return $laws;
    }
}
