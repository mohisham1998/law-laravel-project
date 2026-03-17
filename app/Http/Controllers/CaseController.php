<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Jobs\ProcessPhase1Job;
use App\Jobs\ProcessPhase2Job;
use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Services\PdfExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    public function index()
    {
        $cases = LegalCase::latest()->paginate(10);
        
        $stats = [
            'new' => LegalCase::where('status', 'phase1_pending')->count(),
            'analyzing' => LegalCase::where('status', 'phase2_processing')->count(),
            'drafting' => LegalCase::where('status', 'phase3_processing')->count(),
            'completed' => LegalCase::whereIn('status', ['phase2_completed', 'phase3_completed'])->count(),
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
            ->whereIn('status', ['phase1_pending', 'phase1_processing', 'phase2_processing'])
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
                'mimetypes:' . implode(',', self::ALLOWED_MIMES),
            ],
        ], [
            'attachments.*.max' => 'كل ملف يجب ألا يتجاوز 50 ميجابايت.',
            'attachments.*.mimetypes' => 'نوع الملف غير مسموح. المسموح: صور، TXT، DOC/DOCX، PDF، PPT/PPTX.',
        ]);

        $case = LegalCase::create([
            'title' => $validated['title'],
            'intake_text' => $validated['description'] ?? '',
            'user_id' => auth()->id(),
            'status' => 'phase1_pending',
            'phase' => 1,
            'skill_version' => config('legal.skill_version', 'v2.4.0'),
            'skill_hash' => md5(config('legal.skill_version', 'v2.4.0')),
            'model_used' => auth()->user()->selected_model ?? config('openrouter.default_model'),
            'client_name' => $validated['client_name'] ?? null,
            'category' => $validated['category'] ?? null,
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

        // Dispatch Phase 1 processing job
        ProcessPhase1Job::dispatch($case);

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
        return view('pages.cases.show', compact('case', 'sampleOutputText'));
    }

    public function timeline(LegalCase $case)
    {
        return view('pages.cases.timeline', compact('case'));
    }

    public function pdf(LegalCase $case): StreamedResponse
    {
        $status = $case->status->value ?? $case->status;
        if (! in_array($status, ['phase2_completed', 'phase3_completed'], true)) {
            abort(403, 'لا يمكن تصدير PDF إلا بعد اكتمال المعالجة.');
        }

        $service = app(PdfExportService::class);
        $pdfContent = $service->generate($case);
        $filename = 'brief-' . $case->id . '-' . now()->format('Y-m-d') . '.pdf';

        return response()->streamDownload(
            fn () => print($pdfContent),
            $filename,
            ['Content-Type' => 'application/pdf'],
            'inline'
        );
    }

    public function retryAgent(Request $request, LegalCase $case)
    {
        $status = $case->status->value ?? $case->status;
        if (! in_array($status, ['failed', 'paused'], true)) {
            return redirect()->route('cases.show', $case)->with('error', 'لا يمكن إعادة المحاولة إلا عند فشل أو إيقاف القضية.');
        }
        $case->clearFailure();
        $case = $case->fresh();
        $modelUsed = auth()->user()->selected_model ?? config('openrouter.default_model');
        if ($case->phase >= 2) {
            $case->update([
                'model_used' => $modelUsed,
                'status' => CaseStatus::Phase2Pending,
                'phase' => 2,
            ]);
            ProcessPhase2Job::dispatch($case);
        } else {
            $case->update([
                'model_used' => $modelUsed,
                'status' => CaseStatus::Phase1Pending,
                'phase' => 1,
            ]);
            ProcessPhase1Job::dispatch($case);
        }

        return redirect()->route('cases.show', $case)->with('success', 'تم إعادة تشغيل المعالجة.');
    }

    public function startPhase2(LegalCase $case)
    {
        $status = $case->status->value ?? $case->status;
        if ($status !== 'awaiting_laws') {
            return redirect()->route('cases.show', $case)->with('error', 'لا يمكن بدء المرحلة الثانية إلا بعد اكتمال المرحلة الأولى.');
        }

        $case->update([
            'status' => CaseStatus::Phase2Pending,
            'phase' => 2,
        ]);

        ProcessPhase2Job::dispatch($case);

        return redirect()->route('cases.show', $case)->with('success', 'تم بدء المرحلة الثانية - جارٍ معالجة الوكلاء التسعة.');
    }

    public function abort(LegalCase $case)
    {
        $status = $case->status->value ?? $case->status;
        if (! in_array($status, ['failed', 'paused', 'phase1_processing', 'phase2_processing'], true)) {
            return redirect()->route('cases.show', $case)->with('error', 'لا يمكن إلغاء القضية في حالتها الحالية.');
        }
        $case->update(['status' => CaseStatus::Cancelled]);

        return redirect()->route('cases.show', $case)->with('success', 'تم إلغاء القضية.');
    }
}
