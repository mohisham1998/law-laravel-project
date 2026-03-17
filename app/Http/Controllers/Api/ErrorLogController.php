<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ErrorLogController extends Controller
{
    public function index(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $case = LegalCase::where('user_id', $request->user()->id)->findOrFail($id);

        $query = $case->errorLogs()->orderBy('created_at', 'desc');

        if ($request->filled('agent_number')) {
            $query->where('agent_number', $request->agent_number);
        }
        if ($request->filled('error_type')) {
            $query->where('error_type', $request->error_type);
        }

        $errors = $query->get();
        $agentNames = [
            1 => 'Lead Counsel', 2 => 'Evidence Manager', 3 => 'Chain of Custody',
            4 => 'Timeline Extractor', 5 => 'Law Manager', 6 => 'Statute Matcher',
            7 => 'Defense Strategist', 8 => 'Legal Drafter', 9 => 'Quality Assurance',
            10 => 'Judge', 11 => "Devil's Advocate",
        ];

        $data = $errors->map(fn ($e) => [
            'id' => $e->id,
            'agent_number' => $e->agent_number,
            'agent_name' => $agentNames[$e->agent_number] ?? "Agent {$e->agent_number}",
            'error_type' => $e->error_type->value ?? $e->error_type,
            'error_details' => $e->error_details,
            'fix_applied' => $e->fix_applied,
            'lesson_learned' => $e->lesson_learned,
            'confidence_score' => $e->confidence_score ? (float) $e->confidence_score : null,
            'created_at' => $e->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => $data,
            'meta' => ['total_errors' => $errors->count()],
        ]);
    }

    public function export(Request $request, string $id): StreamedResponse|\Illuminate\Http\Response
    {
        $case = LegalCase::where('user_id', $request->user()->id)->findOrFail($id);

        $query = $case->errorLogs()->orderBy('created_at', 'asc');
        if ($request->filled('agent_number')) {
            $query->where('agent_number', $request->agent_number);
        }
        if ($request->filled('error_type')) {
            $query->where('error_type', $request->error_type);
        }
        $errors = $query->get();

        $agentNames = [
            1 => 'Lead Counsel', 2 => 'Evidence Manager', 3 => 'Chain of Custody',
            4 => 'Timeline Extractor', 5 => 'Law Manager', 6 => 'Statute Matcher',
            7 => 'Defense Strategist', 8 => 'Legal Drafter', 9 => 'Quality Assurance',
            10 => 'Judge', 11 => "Devil's Advocate",
        ];

        $md = "# Error Log – Case " . Str::limit($case->id, 8) . "\n\n";
        $md .= "Generated: " . now()->toIso8601String() . "\n\n---\n\n";

        foreach ($errors as $e) {
            $agentName = $agentNames[$e->agent_number] ?? "Agent {$e->agent_number}";
            $md .= "## [{$e->created_at?->format('Y-m-d H:i')}] — {$agentName}\n\n";
            $md .= "- **Error Type:** " . ($e->error_type->value ?? $e->error_type) . "\n";
            $md .= "- **Details:** " . ($e->error_details ?? '') . "\n";
            if ($e->fix_applied) {
                $md .= "- **Fix Applied:** {$e->fix_applied}\n";
            }
            if ($e->lesson_learned) {
                $md .= "- **Lesson Learned:** {$e->lesson_learned}\n";
            }
            if ($e->confidence_score !== null) {
                $md .= "- **Confidence Score:** {$e->confidence_score}\n";
            }
            $md .= "\n";
        }

        return response($md, 200, [
            'Content-Type' => 'text/markdown',
            'Content-Disposition' => 'attachment; filename="errors_log.md"',
        ]);
    }
}
