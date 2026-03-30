<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Enums\AgentStatus;
use App\Models\LegalCase;
use App\Models\CaseDocument;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalCases = LegalCase::count();
        $completedCases = LegalCase::whereIn('status', [
            CaseStatus::Phase2Completed->value,
            CaseStatus::Phase3Completed->value,
            CaseStatus::CompletedWithWarnings->value,
        ])->count();
        
        // Active cases are those that are not completed, failed, halted, or cancelled
        $activeStatuses = [
            CaseStatus::Phase1Pending->value,
            CaseStatus::Phase1Processing->value,
            CaseStatus::Phase1Completed->value,
            CaseStatus::AwaitingLaws->value,
            CaseStatus::Phase2Pending->value,
            CaseStatus::Phase2Processing->value,
            CaseStatus::Phase2Completed->value,
            CaseStatus::Phase3Pending->value,
            CaseStatus::Phase3Processing->value,
            CaseStatus::Paused->value,
        ];
        
        // Analyzing cases are those currently being processed
        $analyzingStatuses = [
            CaseStatus::Phase1Processing->value,
            CaseStatus::Phase2Processing->value,
            CaseStatus::Phase3Processing->value,
        ];
        
        // Calculate completion and pending rates for the doughnut chart
        $completionRate = $totalCases > 0 ? round(($completedCases / $totalCases) * 100) : 0;
        $pendingRate = 100 - $completionRate;
        
        $stats = [
            'active_cases' => LegalCase::whereIn('status', $activeStatuses)->count(),
            'analyzing_cases' => LegalCase::whereIn('status', $analyzingStatuses)->count(),
            'completed_briefs' => $completedCases,
            'total_documents' => CaseDocument::count(),
            'completion_rate' => $completionRate,
            'pending_rate' => $pendingRate,
        ];

        // Get agent execution stats for the AI Agents Progress section
        $agentStats = \App\Models\AgentExecution::select('agent_number')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed', [AgentStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed', [AgentStatus::Failed->value])
            ->groupBy('agent_number')
            ->orderBy('agent_number')
            ->get();
        
        // If no agent executions, use default progress values
        $agentProgress = [];
        if ($agentStats->isEmpty()) {
            $agentProgress = [
                ['name' => 'تحليل النصوص والاستشهادات', 'progress' => 0, 'status' => 'idle'],
                ['name' => 'استنتاج الثغرات القانونية', 'progress' => 0, 'status' => 'idle'],
                ['name' => 'صياغة المذكرة المبدئية', 'progress' => 0, 'status' => 'idle'],
            ];
        } else {
            foreach ($agentStats as $stat) {
                $progress = $stat->total > 0 ? round(($stat->completed / $stat->total) * 100) : 0;
                $status = $stat->failed > 0 ? 'failed' : ($progress === 100 ? 'completed' : 'processing');
                
                $agentNames = [
                    1 => 'تحليل النصوص والاستشهادات',
                    2 => 'استنتاج الثغرات القانونية',
                    3 => 'صياغة المذكرة المبدئية',
                ];
                
                $agentProgress[] = [
                    'name' => $agentNames[$stat->agent_number] ?? 'وكيل ' . $stat->agent_number,
                    'progress' => $progress,
                    'status' => $status,
                ];
            }
        }

        // Monthly cases chart: last 6 months with real counts
        $monthlyData = [];
        $arabicMonths = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        $rawRows = LegalCase::select(
                DB::raw('EXTRACT(YEAR FROM created_at)::int as yr'),
                DB::raw('EXTRACT(MONTH FROM created_at)::int as mo'),
                DB::raw('COUNT(*) as total')
            )
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('yr', 'mo')
            ->orderBy('yr')
            ->orderBy('mo')
            ->get();

        // Index by "year-month" for fast lookup
        $rawCounts = [];
        foreach ($rawRows as $row) {
            $rawCounts[$row->yr . '-' . $row->mo] = (int) $row->total;
        }

        $maxVal = 0;
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->year . '-' . $date->month;
            $val = $rawCounts[$key] ?? 0;
            $monthlyData[] = ['name' => $arabicMonths[$date->month], 'value' => $val];
            if ($val > $maxVal) $maxVal = $val;
        }

        // Compute height% relative to max; ensure at least 5% so bar is visible
        foreach ($monthlyData as &$m) {
            $m['height'] = $maxVal > 0 ? max(5, round(($m['value'] / $maxVal) * 95)) : 5;
        }
        unset($m);

        // Active cases trend: compare this month vs last month
        $thisMonth = LegalCase::whereIn('status', $activeStatuses)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $lastMonth = LegalCase::whereIn('status', $activeStatuses)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
        $activeTrend = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100) : null;

        // Storage: compute real disk usage for uploaded case documents
        $totalBytes = CaseDocument::sum('file_size');
        $totalGB = $totalBytes > 0 ? round($totalBytes / (1024 * 1024 * 1024), 1) : 0;
        $storageCapacityGB = 10;
        $storageUsedPercent = $storageCapacityGB > 0 ? min(100, round(($totalGB / $storageCapacityGB) * 100)) : 0;

        return view('pages.dashboard', compact('stats', 'agentProgress', 'monthlyData', 'activeTrend', 'totalGB', 'storageCapacityGB', 'storageUsedPercent'));
    }
}
