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

        $provider = $user->llm_provider ?? 'openrouter';
        $effectiveModel = $provider === 'puter'
            ? ($user->puter_model ?? 'gpt-5-nano')
            : ($user->selected_model ?? config('openrouter.default_model'));
        $puterToken = $request->header('X-Puter-Token', '') ?: $request->input('puter_token', '');

        $case = LegalCase::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'intake_text' => $request->intake_text,
            'status' => CaseStatus::Phase1Pending,
            'phase' => 1,
            'progress_percentage' => 0,
            'skill_version' => config('legal.skill_version', 'v2.4.0'),
            'skill_hash' => $promptBuilder->getSkillHash(),
            'model_used' => $effectiveModel,
            'puter_token' => $puterToken ?: null,
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

        ProcessPhase1Job::dispatch($case, $case->getPuterToken());

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
        $freshToken = $request->header('X-Puter-Token', '') ?: $request->input('puter_token', '');
        if (!empty($freshToken)) {
            $case->update(['puter_token' => $freshToken]);
        }
        $case->update(['status' => CaseStatus::Phase2Pending, 'phase' => 2]);
        ProcessPhase2Job::dispatch($case->fresh(), $case->getPuterToken());

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

        if (!in_array($case->status, [CaseStatus::Phase2Completed, CaseStatus::CompletedWithWarnings])) {
            return response()->json(['message' => 'Case must be in phase2_completed status to start Phase 3'], 422);
        }

        $freshToken = $request->header('X-Puter-Token', '') ?: $request->input('puter_token', '');
        if (!empty($freshToken)) {
            $case->update(['puter_token' => $freshToken]);
        }
        $case->update(['status' => CaseStatus::Phase3Pending, 'phase' => 3]);
        ProcessPhase3Job::dispatch($case->fresh(), $case->getPuterToken());

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

    /**
     * Get case progress data for AI Analysis page
     * T001 - API Infrastructure
     */
    public function progress(Request $request, string $id): JsonResponse
    {
        $case = LegalCase::with([
            'documents',
            'caseLaws',
            'agentExecutions' => function ($query) {
                $query->orderBy('agent_number');
            },
            'outputs' => function ($query) {
                $query->whereIn('output_type', ['analysis', 'facts', 'timeline']);
            }
        ])->where('user_id', $request->user()->id)->findOrFail($id);

        // Calculate counts
        $documentCount = $case->documents->count();
        $lawCount = $case->caseLaws->count();
        $factCount = $case->outputs->count();

        // Get stage states from agent executions
        $stages = [];
        $phaseAgents = $case->agentExecutions->groupBy('phase');
        
        $stageNames = [
            1 => ['name' => 'تحليل المستندات', 'key' => 'document_analysis'],
            2 => ['name' => 'استخراج الوقائع', 'key' => 'facts_extraction'],
            3 => ['name' => 'مطابقة الأنظمة', 'key' => 'law_matching'],
            4 => ['name' => 'التحليل القانوني', 'key' => 'legal_analysis'],
            5 => ['name' => 'صياغة المذكرة', 'key' => 'brief_generation'],
            6 => ['name' => 'المراجعة النهائية', 'key' => 'review'],
        ];

        foreach ($stageNames as $phase => $stageInfo) {
            $phaseExecutions = $phaseAgents->get($phase, collect());
            $completedCount = $phaseExecutions->where('status', 'completed')->count();
            $totalAgents = $phase === 1 ? 1 : ($phase === 2 ? 9 : 2); // Phase 1: 1 agent, Phase 2: 9 agents, Phase 3: 2 agents
            
            if ($completedCount === $totalAgents) {
                $status = 'completed';
            } elseif ($phaseExecutions->where('status', 'running')->isNotEmpty()) {
                $status = 'running';
            } elseif ($phaseExecutions->where('status', 'failed')->isNotEmpty()) {
                $status = 'failed';
            } elseif ($completedCount > 0 || $phaseExecutions->where('status', 'pending')->isNotEmpty()) {
                $status = 'in_progress';
            } else {
                $status = 'pending';
            }

            $stages[] = [
                'phase' => $phase,
                'name' => $stageInfo['name'],
                'key' => $stageInfo['key'],
                'status' => $status,
                'progress' => $totalAgents > 0 ? round(($completedCount / $totalAgents) * 100) : 0,
            ];
        }

        return response()->json([
            'data' => [
                'id' => $case->id,
                'title' => $case->title,
                'status' => $case->status->value,
                'phase' => $case->phase,
                'progress_percentage' => $case->progress_percentage ?? 0,
                'counts' => [
                    'documents' => $documentCount,
                    'facts' => $factCount,
                    'laws' => $lawCount,
                ],
                'stages' => $stages,
            ],
            'meta' => ['message' => 'Case progress retrieved successfully'],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Pause case processing
     * T002 - API Infrastructure
     */
    public function pause(Request $request, string $id): JsonResponse
    {
        $case = LegalCase::where('user_id', $request->user()->id)->findOrFail($id);

        // Only pause if case is actively processing
        if (!in_array($case->status, [
            CaseStatus::Phase1Processing,
            CaseStatus::Phase2Processing,
            CaseStatus::Phase3Processing,
        ])) {
            return response()->json([
                'message' => 'Cannot pause case in current status: ' . $case->status->value,
            ], 422);
        }

        // Update status to paused (add paused status if needed)
        $case->update(['status' => CaseStatus::Paused]);

        return response()->json([
            'data' => [
                'id' => $case->id,
                'status' => $case->status->value,
            ],
            'meta' => ['message' => 'Case paused successfully'],
        ]);
    }

    /**
     * Resume case processing
     * T003 - API Infrastructure
     */
    public function resume(Request $request, string $id): JsonResponse
    {
        $case = LegalCase::where('user_id', $request->user()->id)->findOrFail($id);

        if ($case->status !== CaseStatus::Paused) {
            return response()->json([
                'message' => 'Cannot resume case that is not paused',
            ], 422);
        }

        // Resume to the appropriate processing status based on phase
        $newStatus = match($case->phase) {
            1 => CaseStatus::Phase1Processing,
            2 => CaseStatus::Phase2Processing,
            3 => CaseStatus::Phase3Processing,
            default => CaseStatus::Phase1Pending,
        };

        $case->update(['status' => $newStatus]);

        // Dispatch appropriate job based on phase — use stored puter token
        $puterToken = $case->getPuterToken();
        match($case->phase) {
            1 => ProcessPhase1Job::dispatch($case, $puterToken),
            2 => ProcessPhase2Job::dispatch($case, $puterToken),
            3 => ProcessPhase3Job::dispatch($case, $puterToken),
        };

        return response()->json([
            'data' => [
                'id' => $case->id,
                'status' => $case->status->value,
                'phase' => $case->phase,
            ],
            'meta' => ['message' => 'Case resumed successfully'],
        ]);
    }
}
