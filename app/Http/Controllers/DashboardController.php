<?php

namespace App\Http\Controllers;

use App\Models\LegalCase;
use App\Models\CaseDocument;

class DashboardController extends Controller
{
    public function index()
    {
        $totalCases = LegalCase::count();
        $completedCases = LegalCase::whereIn('status', ['phase2_completed', 'phase3_completed'])->count();
        
        $stats = [
            'active_cases' => LegalCase::whereIn('status', ['phase1_pending', 'phase2_processing', 'phase3_processing'])->count(),
            'analyzing_cases' => LegalCase::where('status', 'phase2_processing')->count(),
            'completed_briefs' => $completedCases,
            'total_documents' => CaseDocument::count(),
            'completion_rate' => $totalCases > 0 ? round(($completedCases / $totalCases) * 100) : 0,
        ];

        return view('pages.dashboard', compact('stats'));
    }
}
