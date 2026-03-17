<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessLawFileJob;
use App\Models\LawFile;
use App\Models\LawRegistry;
use App\Models\RequiredLaw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LawController extends Controller
{
    public function index()
    {
        // Show law library (not required laws)
        $laws = LawRegistry::withCount(['files', 'articles'])
            ->with(['files' => fn($q) => $q->latest()])
            ->latest()
            ->paginate(20);
            
        return view('pages.laws.index', compact('laws'));
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
            'name.required' => 'اسم القانون مطلوب',
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
                'filename' => $filename,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'encoding' => 'UTF-8',
            ]);

            // Process the file in background (parse + embed)
            ProcessLawFileJob::dispatch($lawFile);
        }

        return back()->with('success', 'تم إضافة القانون بنجاح وجاري معالجة الملفات');
    }

    public function show(LawRegistry $law)
    {
        $law->load(['files' => fn($q) => $q->latest(), 'articles']);
        
        return view('pages.laws.show', compact('law'));
    }

    public function update(Request $request, LawRegistry $law)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
        ], [
            'name.required' => 'اسم القانون مطلوب',
        ]);

        $law->update($validated);

        return back()->with('success', 'تم تحديث القانون بنجاح');
    }

    public function destroy(LawRegistry $law)
    {
        // Delete all files from storage
        foreach ($law->files as $file) {
            Storage::disk('local')->delete($file->file_path);
        }

        $law->delete();

        return back()->with('success', 'تم حذف القانون بنجاح');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'law_ids' => 'required|json',
        ]);

        $lawIds = json_decode($validated['law_ids'], true);

        if (empty($lawIds) || !is_array($lawIds)) {
            return back()->with('error', 'لم يتم تحديد أي قوانين للحذف');
        }

        $laws = LawRegistry::whereIn('id', $lawIds)->get();

        $count = 0;
        foreach ($laws as $law) {
            // Delete all files from storage
            foreach ($law->files as $file) {
                Storage::disk('local')->delete($file->file_path);
            }

            $law->delete();
            $count++;
        }

        return back()->with('success', "تم حذف {$count} قانون بنجاح");
    }

    public function uploadFiles(Request $request, LawRegistry $law)
    {
        $validated = $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:51200|mimes:txt,pdf,doc,docx',
        ], [
            'files.required' => 'يجب رفع ملف واحد على الأقل',
            'files.*.max' => 'حجم الملف يجب ألا يتجاوز 50 ميجابايت',
            'files.*.mimes' => 'نوع الملف غير مسموح. المسموح: TXT, PDF, DOC, DOCX',
        ]);

        $count = 0;
        foreach ($request->file('files') as $file) {
            $filename = $file->getClientOriginalName();
            $path = $file->store("law_library/{$law->id}", 'local');

            $lawFile = $law->files()->create([
                'filename' => $filename,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'encoding' => 'UTF-8',
            ]);

            // Process the file in background (parse + embed)
            ProcessLawFileJob::dispatch($lawFile);
            $count++;
        }

        return back()->with('success', "تم رفع {$count} ملف بنجاح وجاري معالجتها");
    }

    public function replaceFile(Request $request, LawRegistry $law, LawFile $file)
    {
        $validated = $request->validate([
            'file' => 'required|file|max:51200|mimes:txt,pdf,doc,docx',
        ], [
            'file.required' => 'يجب اختيار ملف للاستبدال',
            'file.max' => 'حجم الملف يجب ألا يتجاوز 50 ميجابايت',
            'file.mimes' => 'نوع الملف غير مسموح. المسموح: TXT, PDF, DOC, DOCX',
        ]);

        // Delete old file from storage
        Storage::disk('local')->delete($file->file_path);

        // Delete old articles and embeddings
        $file->articles()->each(function ($article) {
            $article->embedding()->delete();
            $article->delete();
        });

        // Upload new file
        $uploadedFile = $request->file('file');
        $filename = $uploadedFile->getClientOriginalName();
        $path = $uploadedFile->store("law_library/{$law->id}", 'local');

        $file->update([
            'filename' => $filename,
            'file_path' => $path,
            'file_size' => $uploadedFile->getSize(),
            'mime_type' => $uploadedFile->getMimeType(),
            'is_processed' => false,
            'processed_at' => null,
            'processing_error' => null,
        ]);

        // Process the new file in background
        ProcessLawFileJob::dispatch($file);

        return back()->with('success', 'تم استبدال الملف بنجاح وجاري معالجته');
    }

    public function deleteFile(LawRegistry $law, LawFile $file)
    {
        // Delete file from storage
        Storage::disk('local')->delete($file->file_path);

        // Delete articles and embeddings
        $file->articles()->each(function ($article) {
            $article->embedding()->delete();
            $article->delete();
        });

        $file->delete();

        return back()->with('success', 'تم حذف الملف بنجاح');
    }

    public function downloadFile(LawRegistry $law, LawFile $file)
    {
        if (!Storage::disk('local')->exists($file->file_path)) {
            abort(404, 'الملف غير موجود');
        }

        return Storage::disk('local')->download($file->file_path, $file->filename);
    }
}
