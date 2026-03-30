<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;
use App\Models\LawArticle;
use App\Models\LawRegistry;
use App\Services\Agents\BaseAgent;
use App\Services\CaseEventService;
use App\Services\LLM\LLMServiceInterface;
use App\Services\Orchestration\GateValidator;
use App\Services\Orchestration\PromptBuilder;
use App\Services\RAG\EmbeddingService;
use App\Services\RAG\VectorSearchService;
use Illuminate\Support\Facades\Storage;
use App\Services\ErrorMemoryService;
use Illuminate\Support\Facades\Log;

abstract class Phase2BaseAgent extends BaseAgent
{
    protected ?CaseEventService $eventService = null;
    /** Max characters of law context per agent (from RAG library). Raised from 50K to 100K. */
    private const LAW_CONTEXT_MAX_CHARS = 100_000;
    protected const MAX_CORRECTION_ATTEMPTS = 3;
    protected const MIN_CONFIDENCE_THRESHOLD = 0.70;

    /**
     * Declare exactly which prior-agent output filenames this agent needs.
     * Return an empty array to receive no prior outputs (e.g. Agent 1).
     * Return null to fall back to the legacy "all previous outputs" behaviour.
     *
     * Example: ['02_chunks.jsonl', '03_statutes_index.jsonl']
     *
     * @return string[]|null
     */
    protected function requiredPriorOutputs(): ?array
    {
        return null; // null = legacy mode (all prior outputs)
    }

    /**
     * Whether this agent needs the full law library context.
     * Most Phase 2 agents need it; override to false to exclude it.
     */
    protected function needsLawLibrary(): bool
    {
        return true;
    }

    /**
     * Whether this agent needs the raw case documents.
     * Agents that work only on prior outputs can set this to false.
     */
    protected function needsDocuments(): bool
    {
        return true;
    }

    public function __construct(
        PromptBuilder $promptBuilder,
        GateValidator $gateValidator,
        protected LLMServiceInterface $openRouter,
    ) {
        parent::__construct($promptBuilder, $gateValidator);
        $this->eventService = app(CaseEventService::class);
    }

    /**
     * Create a VectorSearchService configured for the case's LLM provider.
     * When the case uses Puter, embeddings are generated via Puter's OpenAI-compatible
     * proxy instead of OpenRouter, avoiding the need for OpenRouter credits.
     */
    protected function makeVectorSearch(string $puterToken = ''): VectorSearchService
    {
        return new VectorSearchService(new EmbeddingService($puterToken ?: null));
    }
    
    /**
     * Execute LLM call with streaming support.
     * Emits agent.output events in real-time for frontend display.
     */
    protected function executeWithStreaming(LegalCase $case, string $prompt, float $temperature = 0.3, ?int $maxTokens = null): array
    {
        // Read per-agent config (overrides defaults if set)
        $agentConfig = config("legal.agents.{$this->agentNumber()}");
        if ($agentConfig) {
            $temperature = $agentConfig['temperature'] ?? $temperature;
            $maxTokens = $maxTokens ?? ($agentConfig['max_tokens'] ?? null);
        }

        $model = $case->modelForAgent($this->agentNumber());

        // Build system + user message split for persona anchoring
        $systemPrompt = $this->promptBuilder->buildSystemPrompt($this->agentNumber());
        $messages = !empty($systemPrompt)
            ? [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $prompt],
              ]
            : [['role' => 'user', 'content' => $prompt]];

        $meta = [
            'case_id' => $case->id,
            'agent_number' => $this->agentNumber(),
            'agent_name' => $this->agentName(),
        ];
        
        if ($this->eventService) {
            $onChunk = $this->eventService->createStreamCallback(
                $case->id,
                $this->agentNumber(),
                $this->agentName()
            );
            
            $result = $this->openRouter->completeStream($model, $messages, $onChunk, $temperature, $maxTokens, $meta);
            
            // Flush any remaining buffered content
            $this->eventService->flushChunkBuffer($case->id, $this->agentNumber(), $this->agentName());
            
            return $result;
        }
        
        Log::debug("Agent {$this->agentNumber()} LLM call", [
            'case_id' => $case->id,
            'model' => $model,
            'message_roles' => array_column($messages, 'role'),
        ]);

        // Fallback to non-streaming if no event service
        return $this->openRouter->complete($model, $messages, $temperature, $maxTokens, $meta);
    }

    /**
     * Total character budget for the entire context passed to any agent.
     *
     * ~240K chars ≈ ~60K tokens — safe for 75K-token models (e.g. many OpenRouter
     * models) while still comfortable for 128K/200K-context models.
     * Leaves ~15K tokens for system prompt + output generation.
     */
    private const TOTAL_CONTEXT_BUDGET_CHARS = 240_000;

    /** Hard cap per individual document to prevent one file dominating context. */
    private const PER_DOCUMENT_CHARS = 20_000;

    /** Hard cap per individual prior agent output. */
    private const PER_OUTPUT_CHARS = 30_000;

    /**
     * Per-file overrides for files that are inherently large (e.g. chunks JSONL).
     * These override PER_OUTPUT_CHARS for the named file.
     * T042: Increased 03_statutes_index.jsonl from 40K to 80K for agents that need the full index.
     */
    private const PER_FILE_CAPS = [
        '02_chunks.jsonl'         => 50_000,  // chunk files can be large but are essential
        '03_statutes_index.jsonl' => 120_000, // increased from 80K — full index for Agents 3-5
        '06_statutes_map.jsonl'   => 30_000,
    ];

    /**
     * Build context for this agent.
     *
     * When the agent declares requiredPriorOutputs(), only those specific
     * filenames are included — this is the recommended approach for
     * production quality and token efficiency.
     *
     * When requiredPriorOutputs() returns null, all prior outputs are
     * included with proportional budget distribution (legacy fallback).
     */
    protected function buildContext(LegalCase $case): string
    {
        $required = $this->requiredPriorOutputs();

        // ── 1. Intake (always included) ───────────────────────────────────────
        $intake = "## بيانات القضية (Intake)\n\n{$case->intake_text}";
        $parts  = [$intake];

        // ── 2. Case documents ─────────────────────────────────────────────────
        if ($this->needsDocuments()) {
            foreach ($case->documents as $d) {
                $p = Storage::disk('local')->path($d->file_path);
                if (file_exists($p)) {
                    $raw = file_get_contents($p);
                    $parts[] = "## مستند: {$d->filename}\n\n" . $this->truncate($raw, self::PER_DOCUMENT_CHARS);
                }
            }
        }

        // ── 3. Law library ────────────────────────────────────────────────────
        if ($this->needsLawLibrary()) {
            $lawContext = $this->buildLawContextFromLibrary($case);
            if ($lawContext !== '') {
                $parts[] = $lawContext;
            }
        }

        // ── 4. Prior agent outputs ─────────────────────────────────────────────
        if ($required === null) {
            // Legacy mode: include all prior outputs with proportional budget
            $parts = array_merge($parts, $this->buildAllPriorOutputs($case, $parts));
        } else {
            // Selective mode: only include declared filenames
            $parts = array_merge($parts, $this->buildSelectivePriorOutputs($case, $required));
        }

        return implode("\n\n---\n\n", array_filter($parts, fn ($p) => $p !== ''));
    }

    /**
     * Build prior output sections for exactly the listed filenames.
     * Files are included in the order they appear in $filenames.
     * Each file is capped at PER_OUTPUT_CHARS.
     *
     * @param  string[]  $filenames  e.g. ['02_chunks.jsonl', '04_timeline.json']
     * @return string[]
     */
    protected function buildSelectivePriorOutputs(LegalCase $case, array $filenames): array
    {
        if (empty($filenames)) {
            return [];
        }

        // Load all outputs for this case and index by filename
        $outputsByFilename = $case->outputs()
            ->whereIn('filename', $filenames)
            ->get()
            ->keyBy('filename');

        $parts = [];
        foreach ($filenames as $filename) {
            $output = $outputsByFilename->get($filename);
            if (!$output) {
                continue;
            }

            $content = $output->content;
            if ($content === null && $output->file_path && Storage::disk('local')->exists($output->file_path)) {
                $content = Storage::disk('local')->get($output->file_path);
            }

            $content = (string) $content;
            if (trim($content) === '') {
                continue;
            }

            $cap = self::PER_FILE_CAPS[$filename] ?? self::PER_OUTPUT_CHARS;
            $parts[] = "## {$filename}\n\n" . $this->truncate($content, $cap, $filename);
        }

        return $parts;
    }

    /**
     * Legacy: include all prior outputs with proportional budget distribution.
     *
     * @param  string[]  $existingParts  Already-built parts (for budget calculation)
     * @return string[]
     */
    private function buildAllPriorOutputs(LegalCase $case, array $existingParts): array
    {
        $outputs = $case->outputs()
            ->where('agent_number', '>=', 1)
            ->where('agent_number', '<', $this->agentNumber())
            ->orderBy('agent_number')
            ->get();

        $rawOutputParts = [];
        foreach ($outputs as $o) {
            $c = $o->content;
            if ($c === null && $o->file_path && Storage::disk('local')->exists($o->file_path)) {
                $c = Storage::disk('local')->get($o->file_path);
            }
            $rawOutputParts[] = ['filename' => $o->filename, 'content' => (string) $c];
        }

        if (empty($rawOutputParts)) {
            return [];
        }

        // Budget remaining after intake + docs + law library
        $usedChars = array_sum(array_map('mb_strlen', $existingParts)) + 200;
        $outputBudget = max(0, self::TOTAL_CONTEXT_BUDGET_CHARS - $usedChars);

        $perOutput = max(5_000, min(
            self::PER_OUTPUT_CHARS,
            (int) floor($outputBudget / count($rawOutputParts))
        ));

        $parts = [];
        foreach ($rawOutputParts as $raw) {
            $cap = self::PER_FILE_CAPS[$raw['filename']] ?? $perOutput;
            $parts[] = "## {$raw['filename']}\n\n" . $this->truncate($raw['content'], $cap, $raw['filename']);
        }

        return $parts;
    }

    /**
     * Truncate a string to $maxChars, appending a note when truncated.
     * T043: For statute files, includes explicit article count and boundary instruction.
     */
    private function truncate(string $text, int $maxChars, ?string $filename = null): string
    {
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $maxChars);

        // For statute index files, count visible articles and add boundary instruction
        if ($filename && str_contains($filename, 'statutes_index')) {
            $articleCount = substr_count($truncated, '"statute_id"');
            return $truncated . "\n\n⚠️ تتضمن القائمة أعلاه {$articleCount} مادة نظامية. لا يجوز الاستشهاد بأي مادة غير مدرجة في هذه القائمة.";
        }

        return $truncated . "\n\n… [مقتطع: تم اقتصار المحتوى على الحد المسموح به] …";
    }

    /**
     * Build law context from the RAG law library (الأنظمة والقوانين).
     * Uses required law names from Phase 1 to select laws; includes their articles.
     */
    protected function buildLawContextFromLibrary(LegalCase $case): string
    {
        $requiredNames = $case->requiredLaws()->pluck('law_name')->map('trim')->filter()->unique()->values()->all();
        if (empty($requiredNames)) {
            $laws = LawRegistry::with(['articles' => fn ($q) => $q->orderBy('id')])->get();
        } else {
            $laws = LawRegistry::query()
                ->where(function ($q) use ($requiredNames) {
                    foreach ($requiredNames as $name) {
                        $q->orWhere('name', 'like', '%' . $name . '%');
                    }
                })
                ->with(['articles' => fn ($q) => $q->orderBy('id')])
                ->get();
        }
        if ($laws->isEmpty()) {
            return "## Law Library (مكتبة الأنظمة والقوانين)\n\nلا توجد أنظمة في المكتبة حالياً. يُنصح بإضافة الأنظمة من صفحة الأنظمة والقوانين.";
        }
        $out = "## Law Library (مكتبة الأنظمة والقوانين)\n\nالنصوص أدناه من مكتبة الأنظمة المعرّفة في النظام.\n\n";
        $remaining = self::LAW_CONTEXT_MAX_CHARS - mb_strlen($out);
        foreach ($laws as $law) {
            if ($remaining <= 0) {
                break;
            }
            $block = "### {$law->name}\n\n";
            foreach ($law->articles as $article) {
                $line = "المادة {$article->article_number}: {$article->article_text}\n\n";
                if (mb_strlen($block) + mb_strlen($line) > 15000) {
                    $block .= "\n(… مزيد من المواد …)\n\n";
                    break;
                }
                $block .= $line;
            }
            $take = min(mb_strlen($block), $remaining);
            $out .= mb_substr($block, 0, $take);
            $remaining -= $take;
        }
        return $out;
    }

    protected function saveOutput(LegalCase $case, string $filename, string $content, string $contentType): void
    {
        $path = "cases/{$case->id}/outputs/{$filename}";
        Storage::disk('local')->put($path, $content);
        $this->fixStoragePermissions(Storage::disk('local')->path($path));
        \App\Models\CaseOutput::create([
            'case_id' => $case->id,
            'agent_number' => $this->agentNumber(),
            'filename' => $filename,
            'file_path' => $path,
            'content_type' => $contentType,
            'content' => in_array($contentType, ['markdown', 'md']) ? $content : null,
            'content_json' => null,
            'file_size' => strlen($content),
        ]);
    }

    /**
     * Execute with self-correction loop (max 3 retries).
     * After each LLM call, validate output for violations.
     * On failure, re-run with error context appended to prompt.
     */
    protected function executeWithSelfCorrection(LegalCase $case, string $prompt, float $temperature = 0.3, ?int $maxTokens = null): array
    {
        $errorMemory = app(ErrorMemoryService::class);
        $existingErrors = $errorMemory->read($case->id);

        // Append error memory to prompt if it exists
        if (!empty($existingErrors)) {
            $prompt .= "\n\n---\n## Previous Error Memory (سجل الأخطاء السابقة)\n\n" . mb_substr($existingErrors, 0, 5000);
        }

        $errorContext = '';
        $confidenceScore = null;
        $belowThreshold = false;

        for ($attempt = 1; $attempt <= static::MAX_CORRECTION_ATTEMPTS; $attempt++) {
            $currentPrompt = $prompt;
            if (!empty($errorContext)) {
                $currentPrompt .= "\n\n---\n## Correction Context (سياق التصحيح) — Attempt {$attempt}\n\n" . $errorContext;
                $currentPrompt .= "\n\nPlease fix the above violations and regenerate your output.";
            }

            $result = $this->executeWithStreaming($case, $currentPrompt, $temperature, $maxTokens);
            $output = $result['content'] ?? '';

            // Extract confidence score from output
            $confidenceScore = $this->extractConfidenceScore($output);
            $belowThreshold = $confidenceScore !== null && $confidenceScore < static::MIN_CONFIDENCE_THRESHOLD;
            $result['confidence_score'] = $confidenceScore;
            $result['below_threshold'] = $belowThreshold;

            $violations = $this->validateOutput($output, $case);

            if (empty($violations)) {
                // No violations — output is clean
                if ($attempt > 1 && $this->eventService) {
                    // Log the successful correction
                    Log::info("Agent {$this->agentNumber()} self-corrected on attempt {$attempt}", ['case_id' => $case->id]);
                }
                $result['corrections_count'] = $attempt - 1;
                $result['correction_details'] = [];
                return $result;
            }

            // Violations found — emit correction event and prepare retry
            $violationSummary = implode('; ', array_map(fn($v) => "[{$v['type']}] {$v['detail']}", $violations));

            if ($this->eventService) {
                $this->eventService->emitCorrection(
                    $case->id,
                    $this->agentNumber(),
                    $this->agentName(),
                    $attempt,
                    $violations[0]['type'],
                    $violationSummary
                );
            }

            // Log to error memory
            foreach ($violations as $v) {
                $errorMemory->append($case->id, [
                    'discovering_agent_number' => $this->agentNumber(),
                    'discovering_agent_name' => $this->agentName(),
                    'error_type' => $v['type'],
                    'details' => $v['detail'],
                    'impact' => 'قد يؤثر على دقة المخرجات',
                    'fix_applied' => $attempt < static::MAX_CORRECTION_ATTEMPTS ? 'إعادة المحاولة مع سياق الخطأ' : 'استنفاد المحاولات',
                    'lesson_learned' => $v['lesson'] ?? 'يجب التحقق من هذا النوع من الأخطاء',
                ]);
            }

            $errorContext = "Violations found on attempt {$attempt}:\n" . $violationSummary;

            if ($attempt === static::MAX_CORRECTION_ATTEMPTS) {
                // All attempts exhausted — log warning but continue pipeline with best-effort output
                Log::warning("Agent {$this->agentNumber()} exhausted {$attempt} correction attempts, proceeding with best-effort output", [
                    'case_id' => $case->id,
                    'violations' => $violations,
                ]);

                $result['corrections_count'] = $attempt;
                $result['correction_details'] = array_map(fn($v) => $v['detail'], $violations);
                $result['self_correction_exhausted'] = true;
                return $result;
            }
        }

        return $result ?? ['content' => '', 'corrections_count' => static::MAX_CORRECTION_ATTEMPTS];
    }

    /**
     * Extract confidence score from agent output.
     */
    protected function extractConfidenceScore(string $output): ?float
    {
        // Try to find JSON with confidence field
        if (preg_match('/"confidence"\s*:\s*([\d.]+)/', $output, $matches)) {
            return (float) $matches[1];
        }
        // Try to find confidence in text format
        if (preg_match('/confidence[:\s]+([\d.]+)/i', $output, $matches)) {
            return (float) $matches[1];
        }
        return null;
    }

    /**
     * Validate agent output for common violations.
     * Override in subclasses for agent-specific validation.
     * Returns array of violations (empty = valid).
     */
    protected function validateOutput(string $output, LegalCase $case): array
    {
        $violations = [];

        // Check for confidence values below threshold
        if (preg_match_all('/"confidence"\s*:\s*([\d.]+)/', $output, $matches)) {
            foreach ($matches[1] as $score) {
                if ((float) $score < static::MIN_CONFIDENCE_THRESHOLD && (float) $score > 0) {
                    $violations[] = [
                        'type' => 'low_confidence',
                        'detail' => "Confidence score {$score} is below threshold " . static::MIN_CONFIDENCE_THRESHOLD,
                        'lesson' => 'يجب أن تكون درجة الثقة 0.70 أو أعلى',
                    ];
                    break; // Report once
                }
            }
        }

        // Check for abrogated article references only in JSONL lines.
        // A violation is when the agent marks "abrogated": false on a line that
        // also carries an abrogated/superseded flag — i.e. it is citing an abrogated
        // statute as if it were still valid. Merely mentioning the word "ملغي" in
        // narrative text (e.g. "this article was abrogated") is NOT a violation.
        $jsonlLines = array_filter(explode("\n", $output), fn ($l) => str_starts_with(trim($l), '{'));
        foreach ($jsonlLines as $line) {
            $decoded = json_decode(trim($line), true);
            if (
                is_array($decoded) &&
                isset($decoded['abrogated']) &&
                $decoded['abrogated'] === false &&
                isset($decoded['statute_id']) &&
                preg_match('/ملغ[يا]ة?|abrogated|superseded/ui', $decoded['statute_id'] . ' ' . ($decoded['quoted_text'] ?? ''))
            ) {
                $violations[] = [
                    'type' => 'abrogated_statute',
                    'detail' => 'Output references an abrogated or superseded statute; statute_id: ' . ($decoded['statute_id'] ?? ''),
                    'lesson' => 'يجب التحقق من حالة المادة قبل الاستشهاد بها — لا تضع abrogated: false على مادة ملغاة',
                ];
                break;
            }
        }

        return $violations;
    }

    /**
     * Save output with output_type classification.
     */
    protected function saveOutputTyped(LegalCase $case, string $filename, string $content, string $contentType, string $outputType = 'primary'): void
    {
        $path = "cases/{$case->id}/outputs/{$filename}";
        Storage::disk('local')->put($path, $content);
        $this->fixStoragePermissions(Storage::disk('local')->path($path));
        \App\Models\CaseOutput::create([
            'case_id' => $case->id,
            'agent_number' => $this->agentNumber(),
            'filename' => $filename,
            'file_path' => $path,
            'content_type' => $contentType,
            'content' => in_array($contentType, ['markdown', 'md']) ? $content : null,
            'content_json' => in_array($contentType, ['json', 'jsonl']) ? $content : null,
            'file_size' => strlen($content),
            'output_type' => $outputType,
        ]);
    }

    /**
     * Ensure files written by root (worker) are readable by www-data (app/FPM).
     */
    protected function fixStoragePermissions(string $filePath): void
    {
        try {
            chmod($filePath, 0644);
            // Walk up to ensure all parent dirs are traversable
            $dir = dirname($filePath);
            while ($dir && $dir !== '/' && str_contains($dir, 'storage')) {
                if (is_dir($dir)) {
                    chmod($dir, 0755);
                }
                $dir = dirname($dir);
            }
        } catch (\Throwable) {
            // Non-fatal: best-effort
        }
    }
}
