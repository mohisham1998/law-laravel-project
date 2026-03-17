<?php

namespace App\Http\Controllers\Api;

use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
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
        ]);
    }
}
