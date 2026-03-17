<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OutputResource;
use App\Models\LegalCase;
use App\Services\Pdf\PdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OutputController extends Controller
{
    public function index(Request $request, string $id)
    {
        $case = LegalCase::where('user_id', $request->user()->id)->findOrFail($id);
        $outputs = $case->outputs()->orderBy('agent_number')->get();
        return OutputResource::collection($outputs)->additional(['meta' => ['total_outputs' => $outputs->count()]]);
    }

    public function show(Request $request, string $id, int $outputId)
    {
        $case = LegalCase::where('user_id', $request->user()->id)->findOrFail($id);
        $output = $case->outputs()->findOrFail($outputId);

        $content = $output->content;
        if ($content === null && $output->file_path) {
            $path = Storage::disk('local')->path($output->file_path);
            $content = file_exists($path) ? file_get_contents($path) : '';
        }

        $contentType = match ($output->content_type ?? '') {
            'markdown', 'md' => 'text/markdown',
            'json' => 'application/json',
            'jsonl' => 'application/x-ndjson',
            default => 'text/plain',
        };

        return response($content ?? '', 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $output->filename . '"',
        ]);
    }

    public function finalBrief(Request $request, string $id)
    {
        $case = LegalCase::where('user_id', $request->user()->id)->findOrFail($id);
        $viewMode = $request->get('view_mode', 'internal');
        $format = $request->get('format', 'md');

        $briefOutput = $case->outputs()->where('filename', '10_judge_review.md')->first()
            ?? $case->outputs()->where('filename', '09_qa_report.md')->first()
            ?? $case->outputs()->where('filename', '08_legal_brief_v2.md')->first();

        if (!$briefOutput) {
            $briefOutput = $case->outputs()->where('agent_number', 9)->first()
                ?? $case->outputs()->where('agent_number', 8)->first();
        }

        if (!$briefOutput) {
            return response()->json(['message' => 'No final brief available. Complete Phase 2 first.'], 404);
        }

        $content = $briefOutput->content;
        if ($content === null && $briefOutput->file_path && Storage::disk('local')->exists($briefOutput->file_path)) {
            $content = Storage::disk('local')->get($briefOutput->file_path);
        }
        $content = (string) $content;

        if ($viewMode === 'clean') {
            $content = preg_replace('/CASE:\{[^}]*\}/', '', $content);
            $content = preg_replace('/LAW:\{[^}]*\}/', '', $content);
            $content = preg_replace('/\s+/', ' ', $content);
        }

        $safeTitle = preg_replace('/[^\p{Arabic}\p{L}\p{N}\s_-]/u', '', $case->title) ?: 'brief';
        $filename = $safeTitle . '_المذكرة_النهائية';

        if ($format === 'pdf') {
            $pdfGen = app(PdfGenerator::class);
            $pdf = $pdfGen->generateFromMarkdown($content, $case->title);
            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '.pdf"',
            ]);
        }

        return response($content, 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.md"',
        ]);
    }
}
