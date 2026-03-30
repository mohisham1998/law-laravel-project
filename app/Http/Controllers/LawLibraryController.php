<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessLawFileJob;
use App\Models\LawFile;
use App\Models\LawRegistry;
use App\Services\RAG\LawProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LawLibraryController extends Controller
{
    public function __construct(
        protected LawProcessingService $processingService
    ) {}

    public function index()
    {
        $laws = LawRegistry::withCount(['files', 'articles'])
            ->with(['files' => fn($q) => $q->latest()])
            ->latest()
            ->paginate(20);

        return view('pages.law-library.index', compact('laws'));
    }

    public function create()
    {
        return view('pages.law-library.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'effective_year' => 'nullable|string|max:10',
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:51200|mimes:txt,pdf,doc,docx',
        ], [
            'name.required' => 'اسم النظام مطلوب',
            'files.required' => 'يجب رفع ملف واحد على الأقل',
            'files.*.max' => 'حجم الملف يجب ألا يتجاوز 50 ميجابايت',
            'files.*.mimes' => 'نوع الملف غير مسموح. المسموح: TXT, PDF, DOC, DOCX',
        ]);

        $law = LawRegistry::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'effective_year' => $validated['effective_year'] ?? null,
            'status' => 'active',
        ]);

        foreach ($request->file('files') as $file) {
            $filename = $file->getClientOriginalName();
            $path = $file->store("law_library/{$law->id}", 'local');

            $lawFile = $law->files()->create([
                'uploaded_by_user_id' => auth()->id(),
                'filename' => $filename,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'encoding' => 'UTF-8',
            ]);

            // Process the file in background (parse + embed)
            ProcessLawFileJob::dispatch($lawFile);
        }

        return redirect()->route('law-library.show', $law)
            ->with('success', 'تم إضافة النظام بنجاح وجاري معالجة الملفات');
    }

    public function show(LawRegistry $lawRegistry)
    {
        $lawRegistry->load(['files', 'articles.lawFile']);
        
        $stats = [
            'total_files' => $lawRegistry->files()->count(),
            'processed_files' => $lawRegistry->files()->where('is_processed', true)->count(),
            'total_articles' => $lawRegistry->articles()->count(),
            'embedded_articles' => $lawRegistry->articles()->whereHas('embedding')->count(),
        ];

        return view('pages.law-library.show', compact('lawRegistry', 'stats'));
    }

    public function edit(LawRegistry $lawRegistry)
    {
        return view('pages.law-library.edit', compact('lawRegistry'));
    }

    public function update(Request $request, LawRegistry $lawRegistry)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'effective_year' => 'nullable|string|max:10',
            'status' => 'required|in:active,superseded,draft',
        ]);

        $lawRegistry->update($validated);

        return redirect()->route('law-library.show', $lawRegistry)
            ->with('success', 'تم تحديث النظام بنجاح');
    }

    public function destroy(LawRegistry $lawRegistry)
    {
        // Delete all files from storage
        foreach ($lawRegistry->files as $file) {
            Storage::disk('local')->delete($file->file_path);
        }

        $lawRegistry->delete();

        return redirect()->route('law-library.index')
            ->with('success', 'تم حذف النظام بنجاح');
    }

    public function uploadFiles(Request $request, LawRegistry $lawRegistry)
    {
        $validated = $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:51200|mimes:txt,pdf,doc,docx',
        ]);

        $uploadedCount = 0;

        foreach ($request->file('files') as $file) {
            $filename = $file->getClientOriginalName();
            $path = $file->store("law_library/{$lawRegistry->id}", 'local');

            $lawFile = $lawRegistry->files()->create([
                'uploaded_by_user_id' => auth()->id(),
                'filename' => $filename,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'encoding' => 'UTF-8',
            ]);

            ProcessLawFileJob::dispatch($lawFile);
            $uploadedCount++;
        }

        return back()->with('success', "تم رفع {$uploadedCount} ملفات بنجاح");
    }

    public function reprocess(LawRegistry $lawRegistry)
    {
        $result = $this->processingService->processLawRegistry($lawRegistry);

        return back()->with('success', $result['message']);
    }

    /**
     * Re-queue a single law file for processing (e.g. when stuck "قيد المعالجة" or after a failure).
     */
    public function reprocessFile(LawRegistry $lawRegistry, LawFile $lawFile)
    {
        if ($lawFile->law_registry_id !== $lawRegistry->id) {
            abort(404);
        }

        $lawFile->update(['processing_error' => null]);
        ProcessLawFileJob::dispatch($lawFile);

        return back()->with('success', 'تم إعادة إدراج الملف في طابور المعالجة: ' . $lawFile->filename);
    }
}
