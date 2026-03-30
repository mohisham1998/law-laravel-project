<?php

namespace App\Services\Agents\Phase3;

use App\Models\LegalCase;
use App\Services\Agents\BaseAgent;
use App\Services\CaseEventService;
use App\Services\LLM\LLMServiceInterface;
use App\Services\Orchestration\GateValidator;
use App\Services\Orchestration\PromptBuilder;
use Illuminate\Support\Facades\Storage;

class JudgeAgent extends BaseAgent
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
        return 10;
    }

    public function agentName(): string
    {
        return 'Judge';
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);
        $prompt = $this->promptBuilder->buildPromptForAgent(10, $context);

        // Read per-agent config
        $agentConfig = config('legal.agents.10', []);
        $temperature = $agentConfig['temperature'] ?? 0.3;
        $maxTokens = $agentConfig['max_tokens'] ?? 8192;

        // Build system + user messages for persona anchoring
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

        $filename = '10_judge_notes.md';
        $path = "cases/{$case->id}/outputs/{$filename}";
        Storage::disk('local')->put($path, $result['content']);
        try { $fp = Storage::disk('local')->path($path); chmod($fp, 0644); chmod(dirname($fp), 0755); } catch (\Throwable) {}

        \App\Models\CaseOutput::create([
            'case_id' => $case->id,
            'agent_number' => $this->agentNumber(),
            'filename' => $filename,
            'file_path' => $path,
            'content_type' => 'markdown',
            'content' => $result['content'],
            'file_size' => strlen($result['content']),
        ]);

        return [
            'content' => $result['content'],
            'filename' => $filename,
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
        ];
    }

    /**
     * Judge only needs the final brief (v2) and the statutes index for verification.
     * Including all 20+ prior outputs would exceed context limits and add noise.
     * Budget: 400K chars total across all included files.
     */
    protected function buildContext(LegalCase $case): string
    {
        $required = [
            '09_final_brief_v2.md',     // Primary document under review (large — gets most budget)
            '01_lead_plan.md',           // Acceptance criteria & strategic intent
            '03_statutes_index.jsonl',   // Statute registry to verify citations
            '06_statutes_map.jsonl',     // Chunk-to-statute matches for citation verification
            '07_risk_matrix.md',         // Risk assessment context
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
}
