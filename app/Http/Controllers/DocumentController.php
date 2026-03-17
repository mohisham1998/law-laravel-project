<?php

namespace App\Http\Controllers;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /** Allowed MIME types (images, txt, doc, pdf, ppt). Max 50MB. */
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

    private const MAX_FILE_KB = 51200; // 50MB

    public function index(Request $request)
    {
        $selectedCaseId = $request->get('case_id');
        $sort = $request->get('sort', 'date_desc');

        $casesQuery = LegalCase::where('user_id', auth()->id())->withCount('documents');
        match ($sort) {
            'date_asc' => $casesQuery->orderBy('created_at', 'asc'),
            'name_asc' => $casesQuery->orderBy('title', 'asc'),
            'name_desc' => $casesQuery->orderBy('title', 'desc'),
            default => $casesQuery->orderBy('created_at', 'desc'),
        };
        $cases = $casesQuery->get();

        $highlightDocumentId = $request->get('highlight_document');
        if ($highlightDocumentId && !$selectedCaseId) {
            $doc = CaseDocument::find($highlightDocumentId);
            if ($doc && $doc->case && $doc->case->user_id === auth()->id()) {
                $selectedCaseId = $doc->case_id;
            }
        }

        $documents = CaseDocument::with('case')
            ->when($selectedCaseId, fn ($q) => $q->where('case_id', $selectedCaseId))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        if ($highlightDocumentId && $selectedCaseId) {
            $doc = CaseDocument::where('case_id', $selectedCaseId)->where('id', $highlightDocumentId)->first();
            if ($doc) {
                $pos = CaseDocument::where('case_id', $selectedCaseId)->where('created_at', '>=', $doc->created_at)->count();
                $page = (int) ceil($pos / 20);
                if ($page > 1 && (int) $request->get('page') !== $page) {
                    return redirect()->route('documents.index', [
                        'case_id' => $selectedCaseId,
                        'highlight_document' => $highlightDocumentId,
                        'sort' => $sort,
                        'page' => $page,
                    ]);
                }
            }
        }

        return view('pages.documents.index', compact('documents', 'cases', 'selectedCaseId', 'sort', 'highlightDocumentId'));
    }

    /**
     * Search cases and documents by title/filename (for search dropdown).
     */
    public function search(Request $request)
    {
        $q = $request->get('q', '');
        $q = trim($q);
        if (strlen($q) < 1) {
            return response()->json(['cases' => [], 'documents' => []]);
        }

        $term = '%' . $q . '%';
        $cases = LegalCase::where('user_id', auth()->id())
            ->where('title', 'like', $term)
            ->orderBy('title')
            ->limit(10)
            ->get(['id', 'title']);

        $documents = CaseDocument::whereHas('case', fn ($c) => $c->where('user_id', auth()->id()))
            ->where('filename', 'like', $term)
            ->with('case:id,title')
            ->latest()
            ->limit(15)
            ->get(['id', 'filename', 'case_id']);

        return response()->json([
            'cases' => $cases,
            'documents' => $documents->map(fn ($d) => [
                'id' => $d->id,
                'filename' => $d->filename,
                'case_id' => $d->case_id,
                'case_title' => $d->case?->title,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|max:' . self::MAX_FILE_KB . '|mimetypes:' . implode(',', self::ALLOWED_MIMES),
            'case_id' => 'required|exists:cases,id',
        ], [
            'files.required' => 'يرجى اختيار ملف واحد على الأقل.',
            'files.*.max' => 'كل ملف يجب ألا يتجاوز 50 ميجابايت.',
            'files.*.mimetypes' => 'نوع الملف غير مسموح. المسموح: صور، TXT، DOC/DOCX، PDF، PPT/PPTX.',
        ]);

        $caseId = $request->case_id;
        $folder = 'cases/' . $caseId;
        $files = $request->file('files');
        $count = 0;

        foreach ($files as $file) {
            if (!$file->isValid()) {
                continue;
            }
            $path = $file->store($folder, 'public');
            CaseDocument::create([
                'case_id' => $caseId,
                'filename' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
            $count++;
        }

        $message = $count === 1 ? 'تم رفع الملف بنجاح' : "تم رفع {$count} ملفات بنجاح";
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $message]);
        }
        return back()->with('success', $message);
    }

    public function download(CaseDocument $document)
    {
        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404);
        }

        return Storage::disk('public')->download($document->file_path, $document->filename);
    }

    /**
     * Serve file for inline preview (browser displays PDF, images, text).
     */
    public function preview(CaseDocument $document)
    {
        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404);
        }

        $path = Storage::disk('public')->path($document->file_path);
        $mime = $document->mime_type ?: 'application/octet-stream';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $document->filename . '"',
        ]);
    }
}
