<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;
use App\Services\Orchestration\OutputValidator;
use App\Services\RAG\EmbeddingService;
use App\Services\RAG\VectorSearchService;

class ChainOfCustodyAgent extends Phase2BaseAgent
{
    public function agentNumber(): int
    {
        return 3;
    }

    public function agentName(): string
    {
        return 'Chain of Custody';
    }

    /**
     * Agent 3 needs the chunks from Agent 2 to build document fingerprints,
     * plus the law library for statute indexing via RAG.
     * Raw documents are excluded — 02_chunks.jsonl already contains all their text.
     * Including both would double the token count with near-duplicate content.
     */
    protected function requiredPriorOutputs(): ?array
    {
        return ['02_chunks.jsonl'];
    }

    protected function needsDocuments(): bool
    {
        return false; // 02_chunks.jsonl already contains full document text
    }

    public function execute(LegalCase $case): array
    {
        $context = $this->buildContext($case);

        // Query RAG database for relevant statutes
        $ragStatutes = $this->queryRAGForStatutes($case);
        $ragSection = "\n\n## المواد القانونية من قاعدة المعرفة (RAG)\n\n" . $ragStatutes;

        $prompt = $this->promptBuilder->buildPromptForAgent(3, $context . $ragSection);

        // Append structural instruction for output parsing
        $prompt .= "\n\n---\n## تعليمات هيكلة المخرجات\n\nأنتج المخرجات بالترتيب التالي مفصولة بعلامات:\n- `---STATUTES_INDEX---` ثم JSONL\n- `---CONFLICT_WARNINGS---` ثم تحذيرات التعارض بالعربية\n- `---CUSTODY_SUMMARY---` ثم ملخص سلسلة الحفظ بالعربية\n\nهذا الفهرس هو المصدر الوحيد للحقيقة لجميع الاستشهادات القانونية في الوكلاء اللاحقين.";

        $result = $this->executeWithSelfCorrection($case, $prompt);

        $content = $result['content'] ?? '';

        // Parse sections
        $statutesIndex = $this->extractSection($content, 'STATUTES_INDEX', 'CONFLICT_WARNINGS');
        $conflictWarnings = $this->extractSection($content, 'CONFLICT_WARNINGS', 'CUSTODY_SUMMARY');
        $custodySummary = $this->extractSection($content, 'CUSTODY_SUMMARY', null);

        // Fallback: if no markers, use full content as statutes index
        if (empty(trim($statutesIndex))) {
            $statutesIndex = $content;
        }
        if (empty(trim($conflictWarnings))) {
            $conflictWarnings = "# تحذيرات التعارض\n\nلم يتم اكتشاف تعارضات.";
        }
        if (empty(trim($custodySummary))) {
            $custodySummary = "# ملخص سلسلة الحفظ\n\nتم فحص جميع المستندات.";
        }

        // Clean JSONL
        $statutesIndex = preg_replace('/^```(?:jsonl?)?\s*/m', '', $statutesIndex);
        $statutesIndex = preg_replace('/\s*```\s*$/m', '', $statutesIndex);
        $statutesIndex = trim($statutesIndex);

        $this->saveOutputTyped($case, '03_statutes_index.jsonl', $statutesIndex, 'jsonl', 'primary');
        $this->saveOutputTyped($case, '03_conflict_warnings.md', $conflictWarnings, 'markdown', 'secondary');
        $this->saveOutputTyped($case, '03_chain_of_custody_summary.md', $custodySummary, 'markdown', 'secondary');

        return [
            'content' => $result['content'],
            'filename' => '03_statutes_index.jsonl',
            'output_files' => ['03_statutes_index.jsonl', '03_conflict_warnings.md', '03_chain_of_custody_summary.md'],
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'corrections_count' => $result['corrections_count'] ?? 0,
        ];
    }

    protected function queryRAGForStatutes(LegalCase $case): string
    {
        try {
            $vectorSearch = $this->makeVectorSearch($case->getPuterToken());
            $queries = [$case->intake_text];
            if ($case->category) {
                $queries[] = "أحكام {$case->category}";
            }

            // Add domain-specific queries derived from intake text keywords
            // so that relevant law domains are always represented in the index.
            $intakeLower = mb_strtolower($case->intake_text ?? '');

            // Witness-impeachment case: ensure نظام الإثبات witness articles are found
            if (str_contains($intakeLower, 'شاهد') || str_contains($intakeLower, 'شهود')
                || str_contains($intakeLower, 'تجريح') || str_contains($intakeLower, 'بينة')
                || str_contains($intakeLower, 'شهادة')) {
                $queries[] = 'أهلية الشاهد ورد الشهادة وتجريح الشاهد في نظام الإثبات';
                $queries[] = 'شروط قبول الشهادة والطعن في الشاهد لمصلحة أو عداوة';
                $queries[] = 'الشهادة بالاستفاضة والسماع والنقل عن الغير';
            }

            // Criminal/defamation case
            if (str_contains($intakeLower, 'تشهير') || str_contains($intakeLower, 'تخبيب')
                || str_contains($intakeLower, 'إيذاء') || str_contains($intakeLower, 'جنائي')) {
                $queries[] = 'الإثبات في الدعاوى الجنائية والجزائية';
            }

            $results = $vectorSearch->searchMultiple($queries, 30, 0.55);

            // Build context from vector search results
            $context = '';
            foreach ($results as $r) {
                $article = $r['article'];
                $context .= sprintf(
                    "- **%s** المادة %s: %s\n",
                    $article->lawRegistry->name ?? '',
                    $article->article_number,
                    mb_substr($article->article_text, 0, 500)
                );
            }

            // Keyword-based direct DB lookup: vector search may fail when the stored
            // embeddings were generated with a different model than the query embeddings.
            // This ensures domain-critical articles are always available in the context.
            $directArticles = $this->queryDirectArticlesByKeywords($intakeLower);
            foreach ($directArticles as $article) {
                $context .= sprintf(
                    "- **%s** المادة %s: %s\n",
                    $article->lawRegistry->name ?? '',
                    $article->article_number,
                    mb_substr($article->article_text, 0, 500)
                );
            }

            if (empty(trim($context))) {
                return "لم يتم العثور على مواد ذات صلة في قاعدة المعرفة.";
            }

            return $context;
        } catch (\Exception $e) {
            \Log::error('RAG query failed in ChainOfCustodyAgent', ['error' => $e->getMessage()]);
            return "فشل الاستعلام من قاعدة المعرفة.";
        }
    }

    /**
     * Keyword-based fallback: directly query the DB for articles matching domain keywords.
     * Used when vector search fails (e.g. model mismatch between stored and query embeddings).
     * Searches article_text content because article_number fields may store partial ordinals.
     */
    protected function queryDirectArticlesByKeywords(string $intakeLower): \Illuminate\Database\Eloquent\Collection
    {
        $conditions = [];

        // Witness/testimony cases → نظام الإثبات witness chapter
        if (str_contains($intakeLower, 'شاهد') || str_contains($intakeLower, 'شهود')
            || str_contains($intakeLower, 'تجريح') || str_contains($intakeLower, 'بينة')
            || str_contains($intakeLower, 'شهادة')) {
            $conditions[] = ['registry_id' => 3, 'text_patterns' => [
                '%لا تقبل شهادة%',
                '%أهلاً للشهادة%',
                '%ما يخل بشهادة الشاهد%',
                '%مصلحة له فيها%',
                '%بالاستفاضة%',
                '%شهادة بالسماع%',
                '%يجوز الإثبات بشهادة%',
                '%تكون الشهادة عن مشاهدة%',
                '%الشهادة بالكتابة%',
                '%تدون الشهادة%',
            ]];
        }

        if (empty($conditions)) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        $allArticles = new \Illuminate\Database\Eloquent\Collection();
        foreach ($conditions as $cond) {
            $query = \App\Models\LawArticle::with('lawRegistry')
                ->where('law_registry_id', $cond['registry_id'])
                ->where(function ($q) use ($cond) {
                    foreach ($cond['text_patterns'] as $pattern) {
                        $q->orWhere('article_text', 'ILIKE', $pattern);
                    }
                })
                ->limit(15);
            $allArticles = $allArticles->merge($query->get());
        }

        return $allArticles->unique('id');
    }

    protected function validateOutput(string $output, LegalCase $case): array
    {
        $violations = parent::validateOutput($output, $case);

        // Extract STATUTES_INDEX section for JSONL validation
        $indexSection = $this->extractSection($output, 'STATUTES_INDEX', 'CONFLICT_WARNINGS');
        if (empty(trim($indexSection))) {
            $indexSection = $output; // Fallback: validate entire output
        }
        $indexSection = preg_replace('/^```(?:jsonl?)?\s*/m', '', $indexSection);
        $indexSection = preg_replace('/\s*```\s*$/m', '', $indexSection);

        // Validate JSONL format
        $violations = array_merge($violations, OutputValidator::validateJsonl($indexSection));

        return $violations;
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
