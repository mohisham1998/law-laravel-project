<?php

namespace App\Http\Controllers\Api;

use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCaseRequest;
use App\Http\Resources\CaseResource;
use App\Jobs\ProcessPhase1Job;
use App\Jobs\ProcessPhase2Job;
use App\Jobs\ProcessPhase3Job;
use App\Models\LegalCase;
use App\Models\CaseDocument;
use App\Services\Orchestration\PromptBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CaseController extends Controller
{
    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $query = LegalCase::where('user_id', $request->user()->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');
        if (in_array($sort, ['created_at', 'status', 'phase', 'progress_percentage'])) {
            $query->orderBy($sort, $order === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $cases = $query->paginate($perPage);

        return CaseResource::collection($cases);
    }

    public function store(CreateCaseRequest $request): JsonResponse
    {
        $user = $request->user();
        $promptBuilder = app(PromptBuilder::class);

        $case = LegalCase::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'intake_text' => $request->intake_text,
            'status' => CaseStatus::Phase1Pending,
            'phase' => 1,
            'progress_percentage' => 0,
            'skill_version' => config('legal.skill_version', 'v2.4.0'),
            'skill_hash' => $promptBuilder->getSkillHash(),
            'model_used' => $user->selected_model ?? config('openrouter.default_model'),
        ]);

        $caseDir = "cases/{$case->id}";
        Storage::disk('local')->put("{$caseDir}/intake.txt", $request->intake_text);

        if ($request->hasFile('documents')) {
            $docDir = "{$caseDir}/documents";
            foreach ($request->file('documents') as $file) {
                $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs($docDir, $filename, 'local');
                CaseDocument::create([
                    'case_id' => $case->id,
                    'filename' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'encoding' => 'UTF-8',
                ]);
            }
        }

        ProcessPhase1Job::dispatch($case);

        return (new CaseResource($case->loadCount('documents')))
            ->additional(['meta' => ['message' => 'Case created successfully. Phase 1 analysis started.']])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $id): JsonResponse|CaseResource
    {
        $case = LegalCase::with(['documents', 'requiredLaws', 'laws', 'agentExecutions'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return new CaseResource($case);
    }

    public function startPhase2(Request $request, string $id): JsonResponse
    {
        $case = LegalCase::where('user_id', $request->user()->id)->findOrFail($id);

        if ($case->status !== CaseStatus::AwaitingLaws) {
            return response()->json(['message' => 'Case must be in awaiting_laws status'], 422);
        }

        // Law context is supplied from the RAG law library (الأنظمة والقوانين); per-case upload is no longer required
        $case->update(['status' => CaseStatus::Phase2Pending, 'phase' => 2]);
        ProcessPhase2Job::dispatch($case);

        return response()->json([
            'data' => [
                'id' => $case->id,
                'status' => $case->status->value,
                'phase' => 2,
                'estimated_duration_minutes' => 30,
            ],
            'meta' => ['message' => 'Phase 2 processing started. 9 agents will execute sequentially.'],
        ]);
    }

    public function startPhase3(Request $request, string $id): JsonResponse
    {
        $case = LegalCase::where('user_id', $request->user()->id)->findOrFail($id);

        if ($case->status !== CaseStatus::Phase2Completed) {
            return response()->json(['message' => 'Case must be in phase2_completed status to start Phase 3'], 422);
        }

        $case->update(['status' => CaseStatus::Phase3Pending, 'phase' => 3]);
        ProcessPhase3Job::dispatch($case);

        return response()->json([
            'data' => [
                'id' => $case->id,
                'status' => $case->status->value,
                'phase' => 3,
                'estimated_duration_minutes' => 10,
            ],
            'meta' => ['message' => 'Phase 3 processing started. Judge and Devil\'s Advocate agents will review the brief.'],
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $case = LegalCase::where('user_id', $request->user()->id)->findOrFail($id);
        $case->delete();
        return response()->json(['meta' => ['message' => 'Case cancelled successfully']]);
    }
}
