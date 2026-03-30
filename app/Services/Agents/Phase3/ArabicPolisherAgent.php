<?php

namespace App\Services\Agents\Phase3;

use App\Models\LegalCase;
use App\Services\Agents\BaseAgent;
use App\Services\CaseEventService;
use App\Services\LLM\LLMServiceInterface;
use App\Services\Orchestration\GateValidator;
use App\Services\Orchestration\PromptBuilder;
use Illuminate\Support\Facades\Storage;

class ArabicPolisherAgent extends BaseAgent
{
    protected ?CaseEventService $eventService = null;

    public function __construct(
        PromptBuilder $promptBuilder,
        GateValidator $gateValidator,
        protected LLMServiceInterface $openRouter,
    ) {
        parent::__construct($promptBuilder, $gateValidator);
        $this->eventService = app(CaseEventService::class);
    }

    public function agentNumber(): int
    {
        return 13;
    }

    public function agentName(): string
    {
        return 'Arabic Language Polisher';
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);

        $prompt = $this->buildPolishPrompt($context);

        // Read per-agent config
        $agentConfig = config('legal.agents.13', []);
        $temperature = $agentConfig['temperature'] ?? 0.1;
        $maxTokens   = $agentConfig['max_tokens'] ?? 8000;

        $model        = $case->modelForAgent($this->agentNumber());
        $systemPrompt = $this->promptBuilder->buildSystemPrompt($this->agentNumber());
        $messages     = !empty($systemPrompt)
            ? [['role' => 'system', 'content' => $systemPrompt], ['role' => 'user', 'content' => $prompt]]
            : [['role' => 'user', 'content' => $prompt]];

        if ($this->eventService) {
            $onChunk = $this->eventService->createStreamCallback($case->id, $this->agentNumber(), $this->agentName());
            $result  = $this->openRouter->completeStream($model, $messages, $onChunk, $temperature, $maxTokens);
            $this->eventService->flushChunkBuffer($case->id, $this->agentNumber(), $this->agentName());
        } else {
            $result = $this->openRouter->complete($model, $messages, $temperature, $maxTokens);
        }

        $content = trim($result['content'] ?? '');

        // If the response is empty or suspiciously short, keep the original v3 brief
        if (mb_strlen($content) < 3000) {
            \Illuminate\Support\Facades\Log::warning(
                'ArabicPolisherAgent: response too short, using original v3 brief',
                ['case_id' => $case->id, 'length' => mb_strlen($content)]
            );
            $content = $this->getV3Content($case) ?? $content;
        }

        $this->saveOutput($case, '14_final_brief_polished.md', $content);

        return [
            'content'           => $result['content'],
            'filename'          => '14_final_brief_polished.md',
            'output_files'      => ['14_final_brief_polished.md'],
            'prompt_tokens'     => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
        ];
    }

    /**
     * Build the polishing prompt from the v3 brief context.
     */
    protected function buildPolishPrompt(string $context): string
    {
        return <<<PROMPT
## المهمة

أنت مدقق لغوي قانوني. المذكرة القانونية أدناه أعدّها فريق وكلاء الذكاء الاصطناعي. مهمتك تصحيح اللغة وإتمام المعلومات المنقوصة دون تغيير الحجج القانونية.

## تعليمات التصحيح الإلزامية:

١. **التواريخ المنقوصة**: أكمل أي تاريخ ناقص المكونات (مثال: "١٤٤٤/٣" تصبح "١٤٤٤/٣/١٥ هـ" أو ما يناسب السياق).
٢. **الكلمات الإنجليزية**: أزل أي كلمات أو رموز إنجليزية تسربت للنص واستبدلها بالعربية الصحيحة. مثال: "Agent" → "الوكيل"، "output" → "المخرج".
٣. **الأخطاء النحوية**: صحح أخطاء المطابقة (الفعل مع الفاعل، التذكير والتأنيث، التنوين).
٤. **مخاطبة المحكمة**: تأكد من وجود العبارة الصحيحة "إلى فضيلة رئيس المحكمة..." أو ما يناسب في مطلع المذكرة.
٥. **التوقيع والختام**: تأكد من وجود اسم المحامي وتاريخ التوقيع والمحكمة في نهاية المذكرة بشكل مكتمل.
٦. **الطلبات الثلاث**: تأكد من وجود الطلبات الأصلية والاحتياطية والتبعية. إذا كانت ناقصة فأكملها بما يتناسب مع الحجج القانونية الواردة.
٧. **البسملة**: تأكد أن المذكرة تبدأ بـ "بسم الله الرحمن الرحيم" كسطر أول.

## قيود مطلقة:

- لا تضف حججاً قانونية جديدة ولا تحذف حججاً قائمة.
- لا تغيّر أرقام المواد النظامية أو نصوصها.
- لا تكتب أي تعليق أو ملاحظة — أنتج نص المذكرة المصحح فحسب.

---

{$context}
PROMPT;
    }

    /**
     * Build context: only needs the final brief v3 from Agent 12.
     */
    protected function buildContext(LegalCase $case): string
    {
        $v3Content = $this->getV3Content($case);

        if ($v3Content === null) {
            // Fallback to v2 if v3 not available
            $output = $case->outputs()->where('filename', '09_final_brief_v2.md')->first();
            if ($output) {
                $v3Content = (string) ($output->content ?? '');
                if (empty(trim($v3Content)) && $output->file_path) {
                    $full = Storage::disk('local')->path($output->file_path);
                    $v3Content = file_exists($full) ? file_get_contents($full) : '';
                }
            }
        }

        return $v3Content ?? $case->intake_text ?? '';
    }

    /**
     * Retrieve the content of 13_final_brief_v3.md.
     */
    protected function getV3Content(LegalCase $case): ?string
    {
        $output = $case->outputs()->where('filename', '13_final_brief_v3.md')->latest('id')->first();
        if (!$output) {
            return null;
        }

        $content = (string) ($output->content ?? '');
        if (empty(trim($content)) && $output->file_path) {
            $full = Storage::disk('local')->path($output->file_path);
            if (file_exists($full)) {
                $content = file_get_contents($full);
            }
        }

        return mb_strlen(trim($content)) >= 1000 ? $content : null;
    }

    protected function saveOutput(LegalCase $case, string $filename, string $content): void
    {
        $path = "cases/{$case->id}/outputs/{$filename}";
        Storage::disk('local')->put($path, $content);
        try {
            $fp = Storage::disk('local')->path($path);
            chmod($fp, 0644);
            chmod(dirname($fp), 0755);
        } catch (\Throwable) {}

        \App\Models\CaseOutput::create([
            'case_id'      => $case->id,
            'agent_number' => $this->agentNumber(),
            'filename'     => $filename,
            'file_path'    => $path,
            'content_type' => 'markdown',
            'content'      => $content,
            'file_size'    => strlen($content),
        ]);
    }
}
