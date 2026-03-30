<?php

namespace App\Http\Controllers\Api;

use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Models\CaseDocument;
use App\Models\LegalCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics for authenticated user
     * T004 - API Infrastructure
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $user->cases();

        $total = (clone $query)->count();
        $processing = (clone $query)->whereIn('status', [
            CaseStatus::Phase1Processing,
            CaseStatus::Phase2Processing,
            CaseStatus::Phase3Processing,
            CaseStatus::Phase1Pending,
            CaseStatus::Phase2Pending,
            CaseStatus::Phase3Pending,
            CaseStatus::AwaitingLaws,
        ])->count();
        $completed = (clone $query)->whereIn('status', [
            CaseStatus::Phase1Completed,
            CaseStatus::Phase2Completed,
            CaseStatus::Phase3Completed,
            CaseStatus::CompletedWithWarnings,
        ])->count();
        $failed = (clone $query)->where('status', CaseStatus::Failed)->count();

        return response()->json([
            'data' => [
                'total' => $total,
                'processing' => $processing,
                'completed' => $completed,
                'failed' => $failed,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Get detailed dashboard stats including monthly data and agent progress
     * T004 - API Infrastructure
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $casesQuery = $user->cases();

        // Get case counts by status
        $totalCases = (clone $casesQuery)->count();
        $activeCases = (clone $casesQuery)->whereIn('status', [
            CaseStatus::Phase1Processing,
            CaseStatus::Phase2Processing,
            CaseStatus::Phase3Processing,
            CaseStatus::Phase1Pending,
            CaseStatus::Phase2Pending,
            CaseStatus::Phase3Pending,
            CaseStatus::AwaitingLaws,
            CaseStatus::Paused,
        ])->count();
        $analyzingCases = (clone $casesQuery)->whereIn('status', [
            CaseStatus::Phase2Processing,
            CaseStatus::Phase3Processing,
        ])->count();
        $completedCases = (clone $casesQuery)->whereIn('status', [
            CaseStatus::Phase1Completed,
            CaseStatus::Phase2Completed,
            CaseStatus::Phase3Completed,
            CaseStatus::CompletedWithWarnings,
        ])->count();
        $failedCases = (clone $casesQuery)->whereIn('status', [
            CaseStatus::Failed,
            CaseStatus::Halted,
            CaseStatus::TimedOut,
        ])->count();

        // Calculate percentages
        $completionRate = $totalCases > 0 ? round(($completedCases / $totalCases) * 100) : 0;
        $activeRate = $totalCases > 0 ? round(($activeCases / $totalCases) * 100) : 0;

        // Get monthly case data for last 6 months
        $monthlyData = (clone $casesQuery)
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Convert to Arabic month names and fill missing months
        $monthNames = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];
        $currentMonth = now()->month;
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = ($currentMonth - $i + 12) % 12 + 1;
            $monthData = $monthlyData->firstWhere('month', $month);
            $months[] = [
                'name' => $monthNames[$month],
                'value' => $monthData ? $monthData->count : 0,
            ];
        }

        // Calculate max for chart height normalization
        $maxCount = max(array_column($months, 'value'), 1);
        $months = array_map(function ($m) use ($maxCount) {
            $m['height'] = $maxCount > 0 ? round(($m['value'] / $maxCount) * 100) : 0;
            return $m;
        }, $months);

        // Get agent progress from latest case with agent executions
        $latestCaseWithAgents = (clone $casesQuery)
            ->with(['agentExecutions' => function ($q) {
                $q->orderBy('agent_number');
            }])
            ->whereHas('agentExecutions')
            ->latest()
            ->first();

        $agentProgress = [];
        if ($latestCaseWithAgents) {
            $agentNames = [
                0 => 'تحليل النصوص والاستشهادات',
                1 => 'استنتاج الثغرات القانونية',
                2 => 'صياغة المذكرة المبدئية',
            ];
            foreach ($latestCaseWithAgents->agentExecutions->take(3) as $execution) {
                $agentProgress[] = [
                    'name' => $agentNames[$execution->agent_number] ?? 'وكيل ' . ($execution->agent_number + 1),
                    'progress' => $execution->progress_percentage ?? 0,
                    'status' => $execution->status,
                ];
            }
        }

        return response()->json([
            'data' => [
                'active_cases' => $activeCases,
                'analyzing_cases' => $analyzingCases,
                'completed_cases' => $completedCases,
                'failed_cases' => $failedCases,
                'total_documents' => CaseDocument::whereIn('case_id', $user->cases()->select('id'))->count(),
                'completion_rate' => $completionRate,
                'active_rate' => $activeRate,
                'monthly_data' => $months,
                'agent_progress' => $agentProgress,
            ],
            'meta' => ['message' => 'Dashboard stats retrieved successfully'],
        ])->header('Cache-Control', 'no-store');
    }
}
