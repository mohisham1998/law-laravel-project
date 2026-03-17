<?php

namespace App\Services\Agents\Phase2;

use App\Models\LegalCase;
use App\Models\LawArticle;
use App\Models\LawRegistry;
use App\Services\Agents\BaseAgent;
use App\Services\OpenRouter\OpenRouterService;
use App\Services\Orchestration\GateValidator;
use App\Services\Orchestration\PromptBuilder;
use Illuminate\Support\Facades\Storage;

abstract class Phase2BaseAgent extends BaseAgent
{
    /** Max characters of law context per agent (from RAG library). */
    private const LAW_CONTEXT_MAX_CHARS = 80000;

    public function __construct(
        PromptBuilder $promptBuilder,
        GateValidator $gateValidator,
        protected OpenRouterService $openRouter,
    ) {
        parent::__construct($promptBuilder, $gateValidator);
    }

    protected function buildContext(LegalCase $case): string
    {
        $parts = ["## Intake\n\n{$case->intake_text}"];
        foreach ($case->documents as $d) {
            $p = Storage::disk('local')->path($d->file_path);
            if (file_exists($p)) {
                $parts[] = "## Doc: {$d->filename}\n\n" . mb_substr(file_get_contents($p), 0, 35000);
            }
        }
        // Law context from RAG law library (الأنظمة والقوانين), not per-case uploads
        $lawContext = $this->buildLawContextFromLibrary($case);
        if ($lawContext !== '') {
            $parts[] = $lawContext;
        }
        $outputs = $case->outputs()->where('agent_number', '>=', 1)->where('agent_number', '<', $this->agentNumber())->orderBy('agent_number')->get();
        foreach ($outputs as $o) {
            $c = $o->content;
            if ($c === null && $o->file_path && Storage::disk('local')->exists($o->file_path)) {
                $c = Storage::disk('local')->get($o->file_path);
            }
            $parts[] = "## {$o->filename}\n\n" . mb_substr((string) $c, 0, 25000);
        }
        return implode("\n\n---\n\n", $parts);
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
}
