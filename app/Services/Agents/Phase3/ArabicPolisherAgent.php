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

        // If the response is empty or suspiciously short, keep the best available brief
        if (mb_strlen($content) < 3000) {
            \Illuminate\Support\Facades\Log::warning(
                'ArabicPolisherAgent: response too short, using best available brief',
                ['case_id' => $case->id, 'length' => mb_strlen($content)]
            );
            $content = $this->getBestBriefContent($case);
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
     * Build the polishing + completion prompt from the v3 brief context.
     */
    protected function buildPolishPrompt(string $context): string
    {
        return <<<PROMPT
## المهمة

أنت مدقق لغوي قانوني ومكمّل للمذكرات القضائية. المذكرة القانونية أدناه أعدّها فريق وكلاء الذكاء الاصطناعي. مهمتك تصحيح اللغة، وإتمام الأقسام الناقصة، وإضافة الملاحق المطلوبة بناءً على بيانات القضية المقدمة في السياق.

## تعليمات الإتمام الإلزامية:

١. **البسملة**: تأكد أن المذكرة تبدأ بـ "بسم الله الرحمن الرحيم" كسطر أول.
٢. **مخاطبة المحكمة**: تأكد من وجود "إلى أصحاب الفضيلة قضاة [اسم المحكمة]" في المطلع.
٣. **بيانات الأطراف**: تأكد من وجود اسم المدعي والمدعى عليه ووكيل الدفاع ورقم الترخيص كاملاً.
٤. **التواريخ المنقوصة**: أكمل أي تاريخ ناقص المكونات بالكلمات الهجرية الكاملة.
٥. **الكلمات الإنجليزية**: أزل أي كلمات أو رموز إنجليزية تسربت للنص واستبدلها بالعربية الصحيحة.
٦. **الأخطاء النحوية**: صحح أخطاء المطابقة (الفعل مع الفاعل، التذكير والتأنيث، التنوين).
٧. **الطلبات الثلاث**: تأكد من وجود الطلبات الأصلية والاحتياطية والتبعية مفصّلةً. إذا كانت ناقصة فأكملها بما يتناسب مع الحجج القانونية الواردة.
٨. **التوقيع والختام**: تأكد من وجود اسم المحامي ورقم الترخيص في نهاية المذكرة.
٩. **ملحق ١ — مسرد الوقائع الزمني (إلزامي)**: إذا لم يكن الملحق موجوداً في المذكرة، أضفه بناءً على الوقائع الزمنية الواردة في السياق. رتّب الوقائع تصاعدياً مع التواريخ الهجرية بالكلمات الكاملة.
١٠. **ملحق ٢ — المواد النظامية المستشهد بها (إلزامي)**: إذا لم يكن الملحق موجوداً في المذكرة، أضفه مع نص كل مادة نظامية مستشهدٍ بها كاملاً. استخدم نصوص المواد المقدمة في السياق.

## قيود مطلقة:

- لا تحذف أي حجة قانونية قائمة ولا تغيّر أرقام المواد أو نصوصها.
- لا تكتب أي تعليق أو ملاحظة خارج نص المذكرة — أنتج نص المذكرة المكتملة فحسب.
- لا تستخدم جداول Markdown — القوائم المرقمة أو النثر فقط.
- جميع التواريخ بالكلمات العربية الهجرية الكاملة.

---

{$context}
PROMPT;
    }

    /**
     * Build context: the best available brief + timeline + statutes for appendix generation.
     */
    protected function buildContext(LegalCase $case): string
    {
        // Get best available brief (pick longest among v3, v2)
        $briefContent = $this->getBestBriefContent($case);

        $parts = [];
        $parts[] = "## المذكرة القانونية (للتصحيح والإتمام)\n\n" . $briefContent;

        // Include timeline for appendix 1
        $this->appendFileContent($case, '04_timeline.md', '## الجدول الزمني للوقائع (للملحق ١)', 8000, $parts);

        // Include statutes index for appendix 2 (JSONL — take first 6000 chars)
        $this->appendFileContent($case, '03_statutes_index.jsonl', '## فهرس المواد النظامية (للملحق ٢)', 6000, $parts);

        // Include entities index for party names
        $this->appendFileContent($case, '04_entities_index.md', '## فهرس الكيانات (أسماء الأطراف والأشخاص)', 3000, $parts);

        return implode("\n\n---\n\n", $parts);
    }

    /**
     * Get the best (longest) brief content from v3 or v2.
     */
    private function getBestBriefContent(LegalCase $case): string
    {
        $v3Content = $this->getV3Content($case) ?? '';
        $v2Content = '';

        $v2output = $case->outputs()->where('filename', '09_final_brief_v2.md')->first();
        if ($v2output) {
            $v2Content = (string) ($v2output->content ?? '');
            if (empty(trim($v2Content)) && $v2output->file_path) {
                $full = Storage::disk('local')->path($v2output->file_path);
                $v2Content = file_exists($full) ? file_get_contents($full) : '';
            }
        }

        // Also check v1 (Agent 8 original)
        $v1Content = '';
        $v1output = $case->outputs()->where('filename', '08_final_brief.md')->first();
        if ($v1output) {
            $v1Content = (string) ($v1output->content ?? '');
            if (empty(trim($v1Content)) && $v1output->file_path) {
                $full = Storage::disk('local')->path($v1output->file_path);
                $v1Content = file_exists($full) ? file_get_contents($full) : '';
            }
        }

        // Pick the longest
        $best = $v3Content;
        if (mb_strlen(trim($v2Content)) > mb_strlen(trim($best)) * 1.10) {
            $best = $v2Content;
        }
        if (mb_strlen(trim($v1Content)) > mb_strlen(trim($best)) * 1.10) {
            $best = $v1Content;
        }

        return $best ?: $case->intake_text ?? '';
    }

    /**
     * Append file content from a case output to the context parts array.
     */
    private function appendFileContent(LegalCase $case, string $filename, string $label, int $maxChars, array &$parts): void
    {
        $output = $case->outputs()->where('filename', $filename)->first();
        if (!$output) {
            return;
        }
        $content = (string) ($output->content ?? '');
        if (empty(trim($content)) && $output->file_path) {
            $full = Storage::disk('local')->path($output->file_path);
            $content = file_exists($full) ? file_get_contents($full) : '';
        }
        if (empty(trim($content))) {
            return;
        }
        if (mb_strlen($content) > $maxChars) {
            $content = mb_substr($content, 0, $maxChars) . "\n\n… [مقتطع] …";
        }
        $parts[] = "{$label}\n\n{$content}";
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
