<?php

namespace App\Http\Controllers;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Global real-time search across cases, documents, and laws.
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->get('q', ''));

        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $userId = auth()->id();
        $results = [];

        // Search cases
        $cases = LegalCase::where('user_id', $userId)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('intake_text', 'like', "%{$query}%");
            })
            ->select('id', 'title', 'status', 'phase', 'created_at')
            ->latest()
            ->limit(5)
            ->get();

        foreach ($cases as $case) {
            $statusLabels = [
                'phase1_pending' => 'قيد الانتظار',
                'phase1_processing' => 'جاري التحليل',
                'phase1_completed' => 'مرحلة 1 مكتملة',
                'awaiting_laws' => 'في انتظار الأنظمة',
                'phase2_pending' => 'قيد المعالجة',
                'phase2_processing' => 'جاري التحليل القانوني',
                'phase2_completed' => 'مرحلة 2 مكتملة',
                'phase3_pending' => 'قيد المراجعة',
                'phase3_processing' => 'جاري التحكيم',
                'phase3_completed' => 'مكتملة',
                'completed_with_warnings' => 'مكتملة مع تحذيرات',
                'failed' => 'فشلت',
                'paused' => 'متوقفة مؤقتاً',
                'cancelled' => 'ملغاة',
            ];
            $statusValue = $case->status instanceof \BackedEnum ? $case->status->value : $case->status;
            $results[] = [
                'type' => 'case',
                'type_label' => 'قضية',
                'id' => $case->id,
                'title' => $case->title,
                'subtitle' => $statusLabels[$statusValue] ?? $statusValue,
                'url' => route('cases.show', $case->id),
                'icon' => 'work',
            ];
        }

        // Search documents
        $documents = CaseDocument::whereHas('case', fn ($q) => $q->where('user_id', $userId))
            ->where('filename', 'like', "%{$query}%")
            ->with('case:id,title')
            ->select('id', 'case_id', 'filename', 'mime_type', 'created_at')
            ->latest()
            ->limit(5)
            ->get();

        foreach ($documents as $doc) {
            $results[] = [
                'type' => 'document',
                'type_label' => 'مستند',
                'id' => $doc->id,
                'title' => $doc->filename,
                'subtitle' => $doc->case->title ?? '',
                'url' => route('documents.index'),
                'icon' => 'description',
            ];
        }

        // Search laws
        $laws = \App\Models\LawRegistry::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->select('id', 'name', 'category')
            ->limit(5)
            ->get();

        foreach ($laws as $law) {
            $results[] = [
                'type' => 'law',
                'type_label' => 'نظام قانوني',
                'id' => $law->id,
                'title' => $law->name,
                'subtitle' => $law->category ?? '',
                'url' => route('law-library.show', $law->id),
                'icon' => 'gavel',
            ];
        }

        return response()->json(['results' => $results]);
    }
}
