<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;
use App\Services\Orchestration\OutputValidator;

class EvidenceManagerAgent extends Phase2BaseAgent
{
    public function agentNumber(): int
    {
        return 2;
    }

    public function agentName(): string
    {
        return 'Evidence Manager';
    }

    /**
     * Agent 2 needs only the strategic plan from Agent 1 and the raw documents.
     * It does not need the law library — it chunks documents, not statutes.
     */
    protected function requiredPriorOutputs(): ?array
    {
        return ['01_lead_plan.md'];
    }

    protected function needsLawLibrary(): bool
    {
        return false; // Chunking documents — law library is not needed
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);
        $prompt = $this->promptBuilder->buildPromptForAgent(2, $context);

        // Append structural instruction for output parsing (agent needs to know delimiters)
        $prompt .= "\n\n---\n## تعليمات هيكلة المخرجات\n\nأنتج JSONL أولاً ثم التقرير. افصل بينهما بـ `---REPORT---`.";

        $result = $this->executeWithSelfCorrection($case, $prompt);

        $content = $result['content'] ?? '';

        // Split content into JSONL and report
        $parts = preg_split('/---REPORT---/i', $content, 2);
        $jsonlContent = trim($parts[0] ?? $content);
        $reportContent = trim($parts[1] ?? $this->generateDefaultReport($jsonlContent));

        // Clean JSONL: remove markdown code fences if present
        $jsonlContent = preg_replace('/^```(?:jsonl?)?\s*/m', '', $jsonlContent);
        $jsonlContent = preg_replace('/\s*```\s*$/m', '', $jsonlContent);
        $jsonlContent = trim($jsonlContent);

        // Save both outputs
        $this->saveOutputTyped($case, '02_chunks.jsonl', $jsonlContent, 'jsonl', 'primary');
        $this->saveOutputTyped($case, '02_ingestion_report.md', $reportContent, 'markdown', 'secondary');

        return [
            'content' => $result['content'],
            'filename' => '02_chunks.jsonl',
            'output_files' => ['02_chunks.jsonl', '02_ingestion_report.md'],
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'corrections_count' => $result['corrections_count'] ?? 0,
        ];
    }

    protected function generateDefaultReport(string $jsonlContent): string
    {
        $lines = array_filter(explode("\n", $jsonlContent), fn($l) => trim($l) !== '');
        $chunkCount = count($lines);
        $sources = [];
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data && isset($data['source_path'])) {
                $sources[$data['source_path']] = true;
            }
        }
        return "# تقرير الإدخال\n\n- عدد الملفات المعالجة: " . count($sources) . "\n- عدد الأجزاء المُنشأة: {$chunkCount}\n";
    }

    protected function validateOutput(string $output, LegalCase $case): array
    {
        $violations = parent::validateOutput($output, $case);

        // Extract JSONL part (before REPORT marker)
        $parts = preg_split('/---REPORT---/i', $output, 2);
        $jsonlPart = trim($parts[0] ?? '');
        $jsonlPart = preg_replace('/^```(?:jsonl?)?\s*/m', '', $jsonlPart);
        $jsonlPart = preg_replace('/\s*```\s*$/m', '', $jsonlPart);

        // Use deterministic JSONL validator
        $violations = array_merge($violations, OutputValidator::validateJsonl($jsonlPart));

        return $violations;
    }
}
