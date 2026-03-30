<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Jobs\ProcessPhase1Job;
use App\Jobs\ProcessPhase2Job;
use App\Models\CaseDocument;
use App\Models\ErrorLog;
use App\Models\LegalCase;
use App\Services\CaseEventService;
use App\Services\PdfExportService;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CaseController extends Controller
{
    /** Allowed MIME types for case attachments (images, txt, doc, pdf, ppt). */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
        'text/plain',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    /** Max file size in KB (50MB). */
    private const MAX_FILE_KB = 51200;
    
    /**
     * Bulk resume cases that are paused/failed/halted/timed_out
     */
    public function bulkResume(Request $request)
    {
        $caseIds = $request->input('case_ids', []);
        if (empty($caseIds)) {
            return redirect()->route('cases.index')->with('error', 'لم يتم تحديد أي قضايا.');
        }
        
        $ids = is_array($caseIds) ? $caseIds : explode(',', $caseIds);
        $cases = LegalCase::whereIn('id', $ids)
            ->whereIn('status', [
                CaseStatus::Paused->value,
                CaseStatus::Failed->value,
                CaseStatus::Halted->value,
                CaseStatus::TimedOut->value,
            ])
            ->where('user_id', auth()->id())
            ->get();
        
        $count = $cases->count();
        foreach ($cases as $case) {
            $oldStatus = $case->status->value ?? $case->status;
            $haltedAt = $case->halted_at_agent ?? $case->current_agent ?? 1;
            $resumeFrom = max(1, (int) $haltedAt);

            $case->update([
                'resume_from_agent' => $resumeFrom,
                'status' => CaseStatus::Phase2Processing,
            ]);

            $this->emitCaseStatusChange($case, (string) $oldStatus, CaseStatus::Phase2Processing->value);

            ProcessPhase2Job::dispatch($case, $case->getPuterToken())->onQueue('phase2');
        }

        app(UserNotificationService::class)->emitBulkActionSummary(
            auth()->id(),
            'resume',
            $count,
            route('cases.index')
        );

        return redirect()->route('cases.index')->with('success', "تم استئناف معالجة {$count} قضية بنجاح.");
    }
    
    /**
     * Bulk pause cases that are processing
     */
    public function bulkPause(Request $request)
    {
        $caseIds = $request->input('case_ids', []);
        if (empty($caseIds)) {
            return redirect()->route('cases.index')->with('error', 'لم يتم تحديد أي قضايا.');
        }
        
        $ids = is_array($caseIds) ? $caseIds : explode(',', $caseIds);
        $cases = LegalCase::whereIn('id', $ids)
            ->whereIn('status', [
                CaseStatus::Phase1Processing->value,
                CaseStatus::Phase2Processing->value,
                CaseStatus::Phase3Processing->value,
            ])
            ->where('user_id', auth()->id())
            ->get();
        
        $count = $cases->count();
        foreach ($cases as $case) {
            $oldStatus = $case->status->value ?? $case->status;
            $case->update([
                'status' => CaseStatus::Paused,
            ]);

            $this->emitCaseStatusChange($case, (string) $oldStatus, CaseStatus::Paused->value);
        }

        app(UserNotificationService::class)->emitBulkActionSummary(
            auth()->id(),
            'pause',
            $count,
            route('cases.index')
        );
        
        return redirect()->route('cases.index')->with('success', "تم إيقاف {$count} قضية مؤقتاً.");
    }
    
    /**
     * Bulk retry cases that are failed/halted/timed_out
     */
    public function bulkRetry(Request $request)
    {
        $caseIds = $request->input('case_ids', []);
        if (empty($caseIds)) {
            return redirect()->route('cases.index')->with('error', 'لم يتم تحديد أي قضايا.');
        }
        
        $ids = is_array($caseIds) ? $caseIds : explode(',', $caseIds);
        $cases = LegalCase::whereIn('id', $ids)
            ->whereIn('status', [
                CaseStatus::Failed->value,
                CaseStatus::Halted->value,
                CaseStatus::TimedOut->value,
            ])
            ->where('user_id', auth()->id())
            ->get();
        
        $count = $cases->count();
        foreach ($cases as $case) {
            $oldStatus = $case->status->value ?? $case->status;
            $case->update([
                'resume_from_agent' => 1,
                'status' => CaseStatus::Phase2Processing,
                'halted_at_agent' => null,
                'last_error_message' => null,
            ]);

            $this->emitCaseStatusChange($case, (string) $oldStatus, CaseStatus::Phase2Processing->value);

            ProcessPhase2Job::dispatch($case, $case->getPuterToken())->onQueue('phase2');
        }

        app(UserNotificationService::class)->emitBulkActionSummary(
            auth()->id(),
            'retry',
            $count,
            route('cases.index')
        );

        return redirect()->route('cases.index')->with('success', "تم إعادة معالجة {$count} قضية بنجاح.");
    }

    public function bulkDelete(Request $request)
    {
        $caseIds = $request->input('case_ids', []);
        if (empty($caseIds)) {
            return redirect()->route('cases.index')->with('error', 'لم يتم تحديد أي قضايا.');
        }
        
        $ids = is_array($caseIds) ? $caseIds : explode(',', $caseIds);
        $cases = LegalCase::whereIn('id', $ids)
            ->where('user_id', auth()->id())
            ->get();
        
        $count = $cases->count();
        foreach ($cases as $case) {
            $case->documents()->delete();
            $case->laws()->delete();
            $case->delete();
        }
        
        return redirect()->route('cases.index')->with('success', "تم حذف {$count} قضية بنجاح.");
    }

    public function index()
    {
        $cases = LegalCase::where('user_id', auth()->id())->withCount('documents', 'laws')->latest()->paginate(10);
        
        $stats = [
            'new' => LegalCase::where('user_id', auth()->id())->where('status', \App\Enums\CaseStatus::Phase1Pending)->count(),
            'analyzing' => LegalCase::where('user_id', auth()->id())->whereIn('status', [
                \App\Enums\CaseStatus::Phase1Processing->value,
                \App\Enums\CaseStatus::Phase2Pending->value,
                \App\Enums\CaseStatus::Phase2Processing->value,
            ])->count(),
            'drafting' => LegalCase::where('user_id', auth()->id())->where('status', \App\Enums\CaseStatus::Phase3Processing)->count(),
            'completed' => LegalCase::where('user_id', auth()->id())->whereIn('status', [
                \App\Enums\CaseStatus::Phase2Completed->value,
                \App\Enums\CaseStatus::Phase3Completed->value,
                \App\Enums\CaseStatus::CompletedWithWarnings->value,
            ])->count(),
            'failed' => LegalCase::where('user_id', auth()->id())->whereIn('status', [
                \App\Enums\CaseStatus::Failed->value,
                \App\Enums\CaseStatus::Paused->value,
                \App\Enums\CaseStatus::Halted->value,
                \App\Enums\CaseStatus::TimedOut->value,
            ])->count(),
        ];

        return view('pages.cases.index', compact('cases', 'stats'));
    }

    public function create()
    {
        return view('pages.cases.create');
    }

    public function store(Request $request)
    {
        // FR-017: max 3 concurrent cases in processing per user
        $processingCount = LegalCase::where('user_id', auth()->id())
            ->whereIn('status', [
                \App\Enums\CaseStatus::Phase1Pending->value,
                \App\Enums\CaseStatus::Phase1Processing->value,
                \App\Enums\CaseStatus::Phase2Processing->value,
            ])
            ->count();
        if ($processingCount >= 3) {
            return redirect()->route('cases.create')
                ->with('error', 'الحد الأقصى ٣ قضايا قيد المعالجة. يرجى الانتظار أو إلغاء إحداها.')
                ->withInput();
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_name' => 'nullable|string|max:255',
            'category' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*' => [
                'file',
                'max:' . self::MAX_FILE_KB,
                'mimes:jpg,jpeg,png,gif,webp,bmp,txt,doc,docx,pdf,ppt,pptx',
            ],
        ], [
            'title.required' => 'عنوان القضية مطلوب.',
            'attachments.*.max' => 'كل ملف يجب ألا يتجاوز 50 ميجابايت.',
            'attachments.*.mimes' => 'نوع الملف غير مسموح. المسموح: صور، TXT، DOC/DOCX، PDF، PPT/PPTX.',
        ]);

        $user = auth()->user();
        $provider = $user->llm_provider ?? 'openrouter';
        $effectiveModel = $provider === 'puter'
            ? ($user->puter_model ?? 'gpt-5-nano')
            : ($user->selected_model ?? config('openrouter.default_model'));

        // Validate Puter token present when Puter is active
        // Token comes from X-Puter-Token header (AJAX) or puter_token POST field (form submit)
        $puterToken = '';
        if ($provider === 'puter') {
            $puterToken = $request->header('X-Puter-Token', '')
                       ?: $request->input('puter_token', '');
            if (empty($puterToken)) {
                return redirect()->route('cases.create')
                    ->with('error', 'يجب الاتصال بحساب Puter أولاً من صفحة الإعدادات.')
                    ->withInput();
            }
        }

        $case = LegalCase::create([
            'title' => $validated['title'],
            'intake_text' => $validated['description'] ?? '',
            'user_id' => auth()->id(),
            'status' => 'phase1_pending',
            'phase' => 1,
            'skill_version' => config('legal.skill_version', 'v2.4.0'),
            'skill_hash' => md5(config('legal.skill_version', 'v2.4.0')),
            'model_used' => $effectiveModel,
            'client_name' => $validated['client_name'] ?? null,
            'category' => $validated['category'] ?? null,
            // Store encrypted so all subsequent phase jobs can use it without re-injection
            'puter_token' => $puterToken ?: null,
        ]);

        $folder = 'cases/' . $case->id;
        // Write intake so Phase 2 gate (intake.txt) passes
        Storage::disk('local')->put("{$folder}/intake.txt", $validated['description'] ?? '');

        $files = $request->file('attachments');
        if (! empty($files)) {
            foreach ($files as $file) {
                if (! $file->isValid()) {
                    continue;
                }
                $path = $file->store($folder, 'local');
                CaseDocument::create([
                    'case_id' => $case->id,
                    'filename' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        // Dispatch Phase 1 processing job — token read from case record
        ProcessPhase1Job::dispatch($case, $case->getPuterToken());

        return redirect()->route('cases.show', $case)->with('success', 'تم إنشاء القضية بنجاح وبدأت المعالجة');
    }

    public function show(LegalCase $case)
    {
        // Eager load relationships
        $case->load([
            'documents',
            'outputs' => fn ($q) => $q->orderBy('agent_number'),
            'requiredLaws',
            'agentExecutions' => fn ($q) => $q->orderBy('agent_number'),
            'metrics',
        ]);

        // Content for agent output panel: from live stream or latest execution; no fake sample
        $sampleOutputText = null;
        $statusVal = $case->status->value ?? $case->status;
        return view('pages.cases.show', compact('case', 'sampleOutputText', 'statusVal'));
    }

    public function timeline(LegalCase $case)
    {
        return view('pages.cases.timeline', compact('case'));
    }

    public function pdf(Request $request, LegalCase $case): \Illuminate\Http\Response
    {
        $status = $case->status->value ?? $case->status;
        if (! in_array($status, ['phase2_completed', 'phase3_completed', 'completed_with_warnings'], true)) {
            return response('لا يمكن تصدير PDF إلا بعد اكتمال المعالجة.', 403, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        $service = app(PdfExportService::class);
        try {
            $pdfContent = $service->generate($case);
        } catch (\Throwable $e) {
            return response($e->getMessage(), 422, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        $asciiFilename = 'legal-brief-' . now()->format('Y-m-d') . '.pdf';
        $encodedFilename = rawurlencode($service->getFilename($case));

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$asciiFilename}\"; filename*=UTF-8''{$encodedFilename}",
            'Content-Length'      => strlen($pdfContent),
            'Cache-Control'       => 'no-store',
        ]);
    }

    public function retryAgent(Request $request, LegalCase $case)
    {
        $status = $case->status->value ?? $case->status;
        if (! in_array($status, ['failed', 'paused', 'halted', 'timed_out'], true)) {
            return redirect()->route('cases.show', $case)->with('error', 'لا يمكن إعادة المحاولة إلا عند فشل أو إيقاف القضية.');
        }
        
        // Reset retry budget and clear halt/timeout fields
        $orchestrator = app(\App\Services\Orchestration\LegalOrchestrator::class);
        $orchestrator->resetRetryBudget($case);
        
        $case->clearFailure();
        $case = $case->fresh();
        $oldStatus = $status;
        $user = auth()->user();
        $provider = $user->llm_provider ?? 'openrouter';
        $modelUsed = $provider === 'puter'
            ? ($user->puter_model ?? 'gpt-5-nano')
            : ($user->selected_model ?? config('openrouter.default_model'));

        // Refresh token from request if provided; otherwise fall back to stored token
        $freshToken = $request->header('X-Puter-Token', '') ?: $request->input('puter_token', '');
        if (!empty($freshToken)) {
            $case->update(['puter_token' => $freshToken]);
        }
        $case = $case->fresh();

        if ($case->phase >= 2) {
            $case->update([
                'model_used' => $modelUsed,
                'status' => CaseStatus::Phase2Pending,
                'phase' => 2,
            ]);
            $this->emitCaseStatusChange($case, (string) $oldStatus, CaseStatus::Phase2Pending->value);
            ProcessPhase2Job::dispatch($case, $case->getPuterToken());
        } else {
            $case->update([
                'model_used' => $modelUsed,
                'status' => CaseStatus::Phase1Pending,
                'phase' => 1,
            ]);
            $this->emitCaseStatusChange($case, (string) $oldStatus, CaseStatus::Phase1Pending->value);
            ProcessPhase1Job::dispatch($case, $case->getPuterToken());
        }

        return redirect()->route('cases.show', $case)->with('success', 'تم إعادة تشغيل المعالجة.');
    }

    public function startPhase2(Request $request, LegalCase $case)
    {
        $status = $case->status->value ?? $case->status;
        if ($status !== 'awaiting_laws') {
            if (in_array($status, ['phase2_pending', 'phase2_processing'], true)) {
                return redirect()->route('cases.show', $case)->with('success', 'المرحلة الثانية قيد التشغيل بالفعل.');
            }
            return redirect()->route('cases.show', $case)->with('error', 'لا يمكن بدء المرحلة الثانية إلا بعد اكتمال المرحلة الأولى.');
        }

        // Gate: RAG laws must be identified (RequiredLaw records exist)
        if ($case->requiredLaws()->doesntExist()) {
            return redirect()->route('cases.show', $case)->with('error', 'لم يتم تحديد أي أنظمة مطلوبة. يرجى إعادة تشغيل المرحلة الأولى.');
        }

        // Atomic transition prevents duplicate dispatch from concurrent clicks/requests.
        $updatedRows = LegalCase::where('id', $case->id)
            ->where('status', CaseStatus::AwaitingLaws->value)
            ->update([
                'status' => CaseStatus::Phase2Pending,
                'phase' => 2,
            ]);

        if ($updatedRows === 0) {
            $case->refresh();
            $latestStatus = $case->status->value ?? $case->status;
            if (in_array($latestStatus, ['phase2_pending', 'phase2_processing'], true)) {
                return redirect()->route('cases.show', $case)->with('success', 'المرحلة الثانية قيد التشغيل بالفعل.');
            }
            return redirect()->route('cases.show', $case)->with('error', 'لا يمكن بدء المرحلة الثانية في الحالة الحالية.');
        }

        $case->refresh();
        $this->emitCaseStatusChange($case, CaseStatus::AwaitingLaws->value, CaseStatus::Phase2Pending->value);

        // Refresh stored token if browser sends a fresh one
        $freshToken = $request->header('X-Puter-Token', '') ?: $request->input('puter_token', '');
        if (!empty($freshToken)) {
            $case->update(['puter_token' => $freshToken]);
            $case->refresh();
        }

        ProcessPhase2Job::dispatch($case, $case->getPuterToken());

        return redirect()->route('cases.show', $case)->with('success', 'تم بدء المرحلة الثانية - جارٍ معالجة الوكلاء التسعة.');
    }

    public function rerunFrom(Request $request, LegalCase $case)
    {
        $request->validate([
            'agent_number' => 'required|integer|min:1|max:9',
        ]);

        $status = $case->status->value ?? $case->status;
        if (!in_array($status, ['phase2_completed', 'paused', 'failed', 'halted', 'timed_out'], true)) {
            return response()->json(['error' => 'لا يمكن إعادة التشغيل في الحالة الحالية.'], 422);
        }

        $agentNumber = $request->input('agent_number');

        $freshToken = $request->header('X-Puter-Token', '') ?: $request->input('puter_token', '');
        if (!empty($freshToken)) {
            $case->update(['puter_token' => $freshToken]);
        }

        $oldStatus = $case->status->value ?? $case->status;
        $case->update([
            'resume_from_agent' => $agentNumber,
            'status' => CaseStatus::Phase2Processing,
            'phase' => 2,
        ]);

        $this->emitCaseStatusChange($case, (string) $oldStatus, CaseStatus::Phase2Processing->value);

        ProcessPhase2Job::dispatch($case->fresh(), $case->getPuterToken());

        return response()->json([
            'status' => 'rerunning',
            'from_agent' => $agentNumber,
            'agents_to_run' => range($agentNumber, 9),
        ]);
    }

    /**
     * Save global model and per-agent model overrides for a case.
     * Can be called at any time — takes effect on next run/resume.
     */
    public function saveModelConfig(Request $request, LegalCase $case)
    {
        $request->validate([
            'global_model'    => 'nullable|string|max:200',
            'agent_overrides' => 'nullable|array',
            'agent_overrides.*' => 'nullable|string|max:200',
        ]);

        $updates = [];

        if ($request->filled('global_model')) {
            $updates['model_used'] = $request->input('global_model');
        }

        // Merge overrides: null/empty string = remove override for that agent
        $existing = $case->agent_model_overrides ?? [];
        $incoming = $request->input('agent_overrides', []);
        foreach ($incoming as $agentNum => $model) {
            if (empty($model)) {
                unset($existing[(string) $agentNum]);
            } else {
                $existing[(string) $agentNum] = $model;
            }
        }
        $updates['agent_model_overrides'] = empty($existing) ? null : $existing;

        $case->update($updates);

        return response()->json([
            'success' => true,
            'model_used' => $case->fresh()->model_used,
            'agent_model_overrides' => $case->fresh()->agent_model_overrides,
        ]);
    }

    /**
     * Rerun a single agent (even a completed/successful one) with a specific model override.
     * Saves the override then triggers rerunFrom for just that agent.
     */
    public function rerunAgentWithModel(Request $request, LegalCase $case)
    {
        $request->validate([
            'agent_number' => 'required|integer|min:0|max:12',
            'model'        => 'required|string|max:200',
        ]);

        $agentNumber = (int) $request->input('agent_number');
        $model       = $request->input('model');
        $status      = $case->status->value ?? $case->status;

        // Save the per-agent override
        $overrides = $case->agent_model_overrides ?? [];
        $overrides[(string) $agentNumber] = $model;
        $case->update(['agent_model_overrides' => $overrides]);

        // Refresh stored token if a fresh one is provided
        $freshToken = $request->header('X-Puter-Token', '') ?: $request->input('puter_token', '');
        if (!empty($freshToken)) {
            $case->update(['puter_token' => $freshToken]);
            $case->refresh();
        }

        $puterToken = $case->getPuterToken();
        $oldStatus = $case->status->value ?? $case->status;

        if ($agentNumber >= 1 && $agentNumber <= 9) {
            $case->update([
                'resume_from_agent' => $agentNumber,
                'status' => CaseStatus::Phase2Processing,
                'phase' => 2,
            ]);
            $this->emitCaseStatusChange($case, (string) $oldStatus, CaseStatus::Phase2Processing->value);
            ProcessPhase2Job::dispatch($case, $puterToken);
        } elseif ($agentNumber === 0) {
            // Phase 1 re-run
            $case->update(['status' => CaseStatus::Phase1Pending, 'phase' => 1]);
            $this->emitCaseStatusChange($case, (string) $oldStatus, CaseStatus::Phase1Pending->value);
            ProcessPhase1Job::dispatch($case, $puterToken);
        } else {
            // Phase 3 agents (10-12)
            $case->update([
                'status' => CaseStatus::Phase3Processing,
                'phase' => 3,
            ]);
            $this->emitCaseStatusChange($case, (string) $oldStatus, CaseStatus::Phase3Processing->value);
            \App\Jobs\ProcessPhase3Job::dispatch($case, $puterToken);
        }

        return response()->json([
            'success' => true,
            'agent_number' => $agentNumber,
            'model' => $model,
        ]);
    }

    /**
     * Resume a halted/failed case from the next agent after the last successful one.
     * Preserves all completed agent outputs — only re-runs failed and subsequent agents.
     */
    public function resumeCase(Request $request, LegalCase $case)
    {
        $status = $case->status->value ?? $case->status;
        if (!in_array($status, ['failed', 'paused', 'halted', 'timed_out'], true)) {
            return redirect()->route('cases.show', $case)
                ->with('error', 'يمكن الاستئناف فقط عندما تكون القضية في حالة توقف أو فشل.');
        }

        // Determine which agent to resume from:
        // Use halted_at_agent if set, otherwise fall back to current_agent, otherwise start from 1
        $haltedAt = $case->halted_at_agent ?? $case->current_agent ?? 1;
        $resumeFrom = max(1, (int) $haltedAt);

        $orchestrator = app(\App\Services\Orchestration\LegalOrchestrator::class);
        $orchestrator->resetRetryBudget($case);
        $case->clearFailure();

        $user = auth()->user();
        $provider = $user->llm_provider ?? 'openrouter';
        $effectiveModel = $provider === 'puter'
            ? ($user->puter_model ?? 'gpt-5-nano')
            : ($user->selected_model ?? $case->model_used ?? config('openrouter.default_model'));

        $oldStatus = $case->status->value ?? $case->status;
        $case->update([
            'resume_from_agent' => $resumeFrom,
            'status' => CaseStatus::Phase2Processing,
            'phase' => 2,
            'model_used' => $effectiveModel,
        ]);

        $this->emitCaseStatusChange($case, (string) $oldStatus, CaseStatus::Phase2Processing->value);

        // Refresh stored token if browser sends a fresh one
        $freshToken = $request->header('X-Puter-Token', '') ?: $request->input('puter_token', '');
        if (!empty($freshToken)) {
            $case->update(['puter_token' => $freshToken]);
            $case->refresh();
        }

        ProcessPhase2Job::dispatch($case, $case->getPuterToken());

        return redirect()->route('cases.show', $case)
            ->with('success', "جارٍ استئناف التحليل من الوكيل رقم {$resumeFrom} — المخرجات السابقة محفوظة.");
    }

    public function startPhase3(Request $request, LegalCase $case)
    {
        $status = $case->status->value ?? $case->status;
        $allowedStatuses = [
            \App\Enums\CaseStatus::Phase2Completed->value,
            \App\Enums\CaseStatus::CompletedWithWarnings->value,
        ];
        if (!in_array($status, $allowedStatuses)) {
            return redirect()->route('cases.show', $case)->with('error', 'لا يمكن بدء المرحلة الثالثة إلا بعد اكتمال المرحلة الثانية.');
        }

        // Gate: allow either legacy or v2 final brief sources before Phase 3.
        $briefPaths = [
            "cases/{$case->id}/outputs/09_final_brief_v2.md",
            "cases/{$case->id}/outputs/08_final_brief.md",
        ];
        $hasBriefFile = collect($briefPaths)->contains(fn (string $path) => Storage::disk('local')->exists($path));

        $hasBriefOutput = $case->outputs()
            ->where('agent_number', 8)
            ->where(function ($q) {
                $q->where('filename', 'like', '%final_brief%')
                    ->orWhere('content_type', 'markdown');
            })
            ->exists();

        if (! $hasBriefFile && ! $hasBriefOutput) {
            return redirect()->route('cases.show', $case)->with('error', 'لم يتم إنشاء المذكرة النهائية بعد.');
        }

        $oldStatus = $case->status->value ?? $case->status;
        $case->update([
            'status' => CaseStatus::Phase3Pending,
            'phase' => 3,
        ]);

        $this->emitCaseStatusChange($case, (string) $oldStatus, CaseStatus::Phase3Pending->value);

        // Refresh stored token if browser sends a fresh one
        $freshToken = $request->header('X-Puter-Token', '') ?: $request->input('puter_token', '');
        if (!empty($freshToken)) {
            $case->update(['puter_token' => $freshToken]);
            $case->refresh();
        }

        \App\Jobs\ProcessPhase3Job::dispatch($case, $case->getPuterToken());

        if ($request->expectsJson() || $request->header('Accept') === 'application/json') {
            return response()->json(['success' => true, 'status' => 'phase3_pending']);
        }

        return redirect()->route('cases.show', $case)->with('success', 'تم بدء المرحلة الثالثة - التحكيم القضائي.');
    }

    public function abort(Request $request, LegalCase $case)
    {
        $status = $case->status->value ?? $case->status;
        $allowedStatuses = [
            'failed', 'paused', 
            'phase1_processing', 'phase2_processing', 'phase3_processing',
            'phase1_pending', 'phase2_pending', 'phase3_pending'
        ];
        
        if (! in_array($status, $allowedStatuses, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن إيقاف القضية في حالتها الحالية.'
                ], 400);
            }
            return redirect()->route('cases.show', $case)->with('error', 'لا يمكن إيقاف القضية في حالتها الحالية.');
        }

        // Determine which phase to mark as failed
        $lastFailedPhase = 'phase1';
        if ($case->phase === 2 || str_contains($status, 'phase2')) {
            $lastFailedPhase = 'phase2';
        } elseif ($case->phase === 3 || str_contains($status, 'phase3')) {
            $lastFailedPhase = 'phase3';
        }

        // Set to paused (not cancelled) so user can retry later
        $oldStatus = $case->status->value ?? $case->status;
        $case->update([
            'status' => CaseStatus::Paused,
            'last_failed_phase' => $lastFailedPhase,
            'last_error_message' => 'تم إيقاف المعالجة من قبل المستخدم',
        ]);

        $this->emitCaseStatusChange($case, (string) $oldStatus, CaseStatus::Paused->value);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إيقاف القضية. يمكنك إعادة المحاولة لاحقاً.',
                'status' => 'paused'
            ]);
        }

        return redirect()->route('cases.show', $case)->with('success', 'تم إيقاف القضية. يمكنك إعادة المحاولة لاحقاً.');
    }

    /**
     * Pause a running case
     */
    public function pauseCase(Request $request, LegalCase $case)
    {
        $status = $case->status->value ?? $case->status;
        $pausableStatuses = [
            CaseStatus::Phase1Processing->value,
            CaseStatus::Phase2Processing->value,
            CaseStatus::Phase3Processing->value,
            CaseStatus::Phase1Pending->value,
            CaseStatus::Phase2Pending->value,
            CaseStatus::Phase3Pending->value,
            CaseStatus::AwaitingLaws->value,
        ];
        
        if (! in_array($status, $pausableStatuses, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن إيقاف القضية في حالتها الحالية.'
                ], 400);
            }
            return redirect()->back()->with('error', 'لا يمكن إيقاف القضية في حالتها الحالية.');
        }

        $oldStatus = $case->status->value ?? $case->status;
        $case->update([
            'status' => CaseStatus::Paused,
        ]);

        $this->emitCaseStatusChange($case, (string) $oldStatus, CaseStatus::Paused->value);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إيقاف القضية مؤقتاً.',
                'status' => 'paused'
            ]);
        }

        return redirect()->back()->with('success', 'تم إيقاف القضية مؤقتاً.');
    }

    /**
     * Update case with additional info from approval modal
     */
    public function updateMissingInfo(Request $request, LegalCase $case)
    {
        $request->validate([
            'additional_info' => 'required|string|max:5000',
        ]);

        // Store additional info in case metadata or append to intake_text
        $currentIntake = $case->intake_text ?? '';
        $additionalInfo = "\n\n--- معلومات إضافية مقدمة من المستخدم ---\n" . $request->input('additional_info');
        
        $case->update([
            'intake_text' => $currentIntake . $additionalInfo,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حفظ المعلومات الإضافية بنجاح'
            ]);
        }

        return back()->with('success', 'تم حفظ المعلومات الإضافية بنجاح');
    }

    /**
     * Handle user's request for changes before proceeding to Phase 2
     */
    public function requestChanges(Request $request, LegalCase $case)
    {
        $request->validate([
            'change_type' => 'required|string',
            'change_details' => 'required|string|max:2000',
        ]);

        $changeType = $request->input('change_type');
        $changeDetails = $request->input('change_details');

        // Store the change request in error_logs for tracking (if an execution record exists)
        $latestExecution = $case->agentExecutions()->latest('id')->first();

        if ($latestExecution) {
            ErrorLog::create([
                'case_id' => $case->id,
                'agent_execution_id' => $latestExecution->id,
                'agent_number' => $latestExecution->agent_number,
                'error_type' => 'user_requested_change',
                'error_details' => "Type: {$changeType}\nDetails: {$changeDetails}",
                'fix_applied' => 'pending_user_edits',
            ]);
        }

        // Mark case as requiring input (similar to awaiting_laws but for user edits)
        // This pauses the pipeline and allows user to edit the case
        // For now, just redirect back with success message

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إرسال طلب التعديل. ستتم مراجعة قضيتك وإعادة توجيهك للتعديل.'
            ]);
        }

        return redirect()->route('cases.show', $case)->with(
            'success', 
            'تم إرسال طلب التعديل. يرجى تعديل القضية وإعادة تقديمها للمتابعة.'
        );
    }

    /**
     * Audit a case's input completeness (AI-powered)
     */
    public function audit(Request $request, LegalCase $case)
    {
        $status = $case->status->value ?? $case->status;
        if ($status !== 'awaiting_laws') {
            return response()->json([
                'success' => false,
                'message' => 'Case is not in awaiting_laws status',
                'status' => $status,
            ])->header('Cache-Control', 'no-store');
        }

        try {
            $inlineInputs = $request->input('inline_inputs');
            
            $auditService = new \App\Services\InputAuditService(
                \App\Services\OpenRouter\OpenRouterService::fromConfig()
            );
            
            $result = $auditService->audit($case, $inlineInputs);
            
            return response()->json([
                'success' => true,
                'data' => array_merge($result, [
                    'passing_threshold' => config('legal.audit_passing_threshold', 70),
                ]),
            ])->header('Cache-Control', 'no-store');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Audit service unavailable'
            ], 500)->header('Cache-Control', 'no-store');
        }
    }

    /**
     * Handle inline file uploads from the audit modal
     */
    public function uploadAuditFile(Request $request, LegalCase $case)
    {
        $request->validate([
            'file' => 'required|file|max:' . self::MAX_FILE_KB . '|mimes:jpg,jpeg,png,gif,webp,bmp,txt,doc,docx,pdf,ppt,pptx',
        ], [
            'file.max' => 'الملف يجب ألا يتجاوز 50 ميجابايت.',
            'file.mimes' => 'نوع الملف غير مسموح.',
        ]);

        $file = $request->file('file');
        
        $folder = 'cases/' . $case->id;
        $filename = $file->getClientOriginalName();
        $path = $file->storeAs($folder, $filename);

        $document = CaseDocument::create([
            'case_id' => $case->id,
            'filename' => $filename,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'document' => [
                'id' => $document->id,
                'filename' => $document->filename,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
            ],
        ]);
    }

    public function progressJson(LegalCase $case): \Illuminate\Http\JsonResponse
    {
        $executions = $case->agentExecutions()->orderBy('agent_number')->get()->groupBy('phase');
        $agentDefinitions = \App\Services\AgentDefinitions::all();
        $agentStates = [];
        foreach ($agentDefinitions as $def) {
            $exec = $case->agentExecutions()->where('agent_number', $def['number'])->first();
            $status = $exec ? ($exec->status instanceof \BackedEnum ? $exec->status->value : (string)$exec->status) : 'pending';
            $agentStates[] = [
                'number' => $def['number'],
                'phase'  => $def['phase'],
                'status' => $status,
            ];
        }
        return response()->json([
            'data' => [
                'id'                  => $case->id,
                'title'               => $case->title,
                'status'              => $case->status->value ?? $case->status,
                'phase'               => $case->phase,
                'progress_percentage' => $case->progress_percentage ?? 0,
                'current_agent'       => $case->current_agent,
                'counts' => [
                    'documents' => $case->documents()->count(),
                    'facts'     => $case->outputs()->where('output_type', 'facts')->count(),
                    'laws'      => $case->laws()->count(),
                ],
                'agent_states' => $agentStates,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function showAnalysis(?LegalCase $case = null)
    {
        $caseData = null;
        $executions = collect();

        // All cases for the selector dropdown (most recent first)
        $allCases = LegalCase::where('user_id', auth()->id())
            ->select('id', 'title', 'status', 'phase', 'progress_percentage', 'created_at')
            ->latest()
            ->get();

        if ($case) {
            // Eager load agent executions for the view
            $executions = $case->agentExecutions()
                ->orderBy('agent_number', 'asc')
                ->get()
                ->groupBy('agent_number');

            $caseData = [
                'id' => $case->id,
                'title' => $case->title,
                'status' => $case->status->value ?? $case->status,
                'phase' => $case->phase,
                'progress_percentage' => $case->progress_percentage,
                'current_agent' => $case->current_agent,
                'documents_count' => $case->documents()->count(),
                'laws_count' => $case->laws()->count(),
                'facts_count' => $case->outputs()->where('output_type', 'facts')->count(),
            ];
        }

        return view('pages.ai-analysis', [
            'case' => $caseData,
            'allCases' => $allCases,
            'executions' => $executions,
            'agentDefinitions' => \App\Services\AgentDefinitions::all(),
        ]);
    }

    /**
     * Permanently delete a case and its storage files.
     */
    public function destroy(Request $request, LegalCase $case)
    {
        // Ensure ownership
        if ($case->user_id !== auth()->id()) {
            abort(403);
        }

        // Remove case files from disk
        Storage::disk('local')->deleteDirectory("cases/{$case->id}");

        $case->delete();

        return redirect()->route('cases.index')->with('success', 'تم حذف القضية بنجاح.');
    }

    private function emitCaseStatusChange(LegalCase $case, string $oldStatus, string $newStatus): void
    {
        app(CaseEventService::class)->emitStatusChanged($case->id, $oldStatus, $newStatus);
    }
}
