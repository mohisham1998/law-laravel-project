<?php

namespace App\Services\Agents\Phase3;

use App\Models\LegalCase;
use App\Services\Agents\BaseAgent;
use App\Services\CaseEventService;
use App\Services\ErrorMemoryService;
use App\Services\LLM\LLMServiceInterface;
use App\Services\Orchestration\GateValidator;
use App\Services\Orchestration\PromptBuilder;
use Illuminate\Support\Facades\Storage;

class FortificationAgent extends BaseAgent
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
        return 12;
    }

    public function agentName(): string
    {
        return 'Fortification Agent';
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);

        // Read error memory
        $errorMemory = app(ErrorMemoryService::class);
        $errors = $errorMemory->read($case->id);
        $errorSection = !empty($errors) ? "\n\n## سجل الأخطاء السابقة\n\n" . mb_substr($errors, 0, 3000) : '';

        $prompt = $this->promptBuilder->buildPromptForAgent(12, $context);
        if (!empty($errorSection)) {
            $prompt .= $errorSection;
        }

        // Append structural instruction for output parsing
        $prompt .= "\n\n---\n## تعليمات هيكلة المخرجات\n\nأنتج بالترتيب مفصولة بعلامات:\n- `---FORTIFICATION_PLAN---` ثم خطة التحصين بالعربية\n- `---RESPONSES_TO_JUDGE---` ثم ردود مُعدّة لكل سؤال متوقع من القاضي\n- `---COUNTER_ARGUMENTS---` ثم ردود على كل هجوم من محامي الخصم\n- `---FINAL_BRIEF_V3---` ثم المذكرة النهائية المحصّنة (جاهزة للمحكمة)";

        // Read per-agent config
        $agentConfig = config('legal.agents.12', []);
        $temperature = $agentConfig['temperature'] ?? 0.3;
        $maxTokens = $agentConfig['max_tokens'] ?? 16384;

        $model = $case->modelForAgent($this->agentNumber());
        $systemPrompt = $this->promptBuilder->buildSystemPrompt($this->agentNumber());
        $messages = !empty($systemPrompt)
            ? [['role' => 'system', 'content' => $systemPrompt], ['role' => 'user', 'content' => $prompt]]
            : [['role' => 'user', 'content' => $prompt]];

        if ($this->eventService) {
            $onChunk = $this->eventService->createStreamCallback($case->id, $this->agentNumber(), $this->agentName());
            $result = $this->openRouter->completeStream($model, $messages, $onChunk, $temperature, $maxTokens);
            $this->eventService->flushChunkBuffer($case->id, $this->agentNumber(), $this->agentName());
        } else {
            $result = $this->openRouter->complete($model, $messages, $temperature, $maxTokens);
        }

        $content = $result['content'] ?? '';

        // Parse sections
        $fortPlan = $this->extractSection($content, 'FORTIFICATION_PLAN', 'RESPONSES_TO_JUDGE');
        $responsesToJudge = $this->extractSection($content, 'RESPONSES_TO_JUDGE', 'COUNTER_ARGUMENTS');
        $counterArguments = $this->extractSection($content, 'COUNTER_ARGUMENTS', 'FINAL_BRIEF_V3');
        $finalBriefV3 = $this->extractSection($content, 'FINAL_BRIEF_V3', null);

        // Fallbacks
        if (empty(trim($fortPlan))) $fortPlan = $content;
        if (empty(trim($responsesToJudge))) $responsesToJudge = "# ردود للقاضي\n\nمضمّنة في خطة التحصين.";
        if (empty(trim($counterArguments))) $counterArguments = "# ردود على الخصم\n\nمضمّنة في خطة التحصين.";
        if (empty(trim($finalBriefV3))) $finalBriefV3 = $content;

        // Save all outputs
        $this->saveOutput($case, '12_fortification_plan.md', $fortPlan);
        $this->saveOutput($case, '12_responses_to_judge.md', $responsesToJudge);
        $this->saveOutput($case, '12_counter_arguments.md', $counterArguments);
        $this->saveOutput($case, '13_final_brief_v3.md', $finalBriefV3);

        // Log newly discovered errors
        if (preg_match_all('/(?:خطأ|مشكلة|تناقض).*?(?:\.|$)/u', $fortPlan, $errorMatches)) {
            foreach (array_slice($errorMatches[0], 0, 3) as $errorDetail) {
                $errorMemory->append($case->id, [
                    'discovering_agent_number' => 12,
                    'discovering_agent_name' => 'وكيل التحصين',
                    'error_type' => 'fortification_finding',
                    'details' => $errorDetail,
                    'impact' => 'تم اكتشافه أثناء التحصين',
                    'fix_applied' => 'تم التصحيح في المذكرة v3',
                    'lesson_learned' => 'يجب مراجعة هذا النوع من القضايا مبكراً',
                ]);
            }
        }

        return [
            'content' => $result['content'],
            'filename' => '13_final_brief_v3.md',
            'output_files' => ['12_fortification_plan.md', '12_responses_to_judge.md', '12_counter_arguments.md', '13_final_brief_v3.md'],
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
        ];
    }

    protected function saveOutput(LegalCase $case, string $filename, string $content): void
    {
        $path = "cases/{$case->id}/outputs/{$filename}";
        Storage::disk('local')->put($path, $content);
        try { $fp = Storage::disk('local')->path($path); chmod($fp, 0644); chmod(dirname($fp), 0755); } catch (\Throwable) {}

        \App\Models\CaseOutput::create([
            'case_id' => $case->id,
            'agent_number' => $this->agentNumber(),
            'filename' => $filename,
            'file_path' => $path,
            'content_type' => 'markdown',
            'content' => $content,
            'file_size' => strlen($content),
        ]);
    }

    /**
     * Fortification needs: the brief to strengthen, the adversarial feedback
     * from Judge and Devil's Advocate, and the reference outputs it will rewrite.
     * The QA output is included so corrections from Agent 9 are preserved in v3.
     */
    protected function buildContext(LegalCase $case): string
    {
        $required = [
            '09_final_brief_v2.md',     // The brief to fortify (primary input)
            '10_judge_notes.md',         // Judicial critique to address
            '11_devils_advocate_notes.md', // Adversarial attack to counter
            '09_QA_summary.md',          // QA issues already identified
            '06_statutes_map.jsonl',     // Statute map for accurate citation rewrite
            '07_defense_layers.md',      // Defense structure context
        ];

        return $this->buildSelectiveContext($case, $required);
    }

    /**
     * Build context from a specific list of filenames with proportional budget.
     */
    private function buildSelectiveContext(LegalCase $case, array $filenames): string
    {
        $totalBudget = 400_000;
        $perFileBudget = max(20_000, (int) floor($totalBudget / max(1, count($filenames))));

        $outputsByFilename = $case->outputs()
            ->whereIn('filename', $filenames)
            ->get()
            ->keyBy('filename');

        $parts = ["## بيانات القضية\n\n" . mb_substr($case->intake_text, 0, 5_000)];

        foreach ($filenames as $filename) {
            $output = $outputsByFilename->get($filename);
            if (!$output) {
                continue;
            }
            $content = $output->content;
            if ($content === null && $output->file_path) {
                $full = Storage::disk('local')->path($output->file_path);
                $content = file_exists($full) ? file_get_contents($full) : '';
            }
            $content = (string) $content;
            if (trim($content) === '') {
                continue;
            }
            if (mb_strlen($content) > $perFileBudget) {
                $content = mb_substr($content, 0, $perFileBudget) . "\n\n… [مقتطع] …";
            }
            $parts[] = "## {$filename}\n\n{$content}";
        }

        return implode("\n\n---\n\n", array_filter($parts)) ?: $case->intake_text;
    }

    protected function extractSection(string $content, string $startMarker, ?string $endMarker): string
    {
        $pattern = '/---' . preg_quote($startMarker, '/') . '---\s*(.*?)' .
                   ($endMarker ? '(?=---' . preg_quote($endMarker, '/') . '---)' : '$') . '/s';
        if (preg_match($pattern, $content, $m)) {
            return trim($m[1]);
        }
        return '';
    }
}
