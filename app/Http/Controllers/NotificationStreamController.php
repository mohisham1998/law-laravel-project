<?php

namespace App\Http\Controllers;

use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationStreamController extends Controller
{
    /**
     * SSE stream that watches ALL active cases for the authenticated user
     * and emits notifications when agents complete, fail, or cases finish.
     */
    public function stream(Request $request): StreamedResponse
    {
        $user = auth()->user();
        $userId = $user?->id;
        $notificationsEnabled = (bool) ($user?->notifications_enabled ?? true);

        if (session()->isStarted()) {
            session()->save();
        }

        return response()->stream(function () use ($userId, $notificationsEnabled) {
            $startTime = time();
            $maxDuration = 1800; // 30 min max
            $lastSeen = []; // caseId => last event index seen
            $lastUserIdx = -1;

            // Send initial heartbeat
            echo "data: " . json_encode([
                'event_type' => 'notifications.connected',
                'notifications_enabled' => $notificationsEnabled,
            ]) . "\n\n";
            if (ob_get_level()) ob_flush();
            flush();

            while (true) {
                if (connection_aborted() || (time() - $startTime) > $maxDuration) {
                    break;
                }

                if (!$notificationsEnabled) {
                    echo ": ping\n\n";
                    if (ob_get_level()) ob_flush();
                    flush();
                    usleep(500000);
                    continue;
                }

                // Read user-scoped notifications first (bulk actions, RAG ingestion updates, etc.)
                $userKey = "user:{$userId}:notifications";
                $userTotal = Redis::llen($userKey);
                if ($userTotal > $lastUserIdx + 1) {
                    $userEvents = Redis::lrange($userKey, $lastUserIdx + 1, -1);
                    foreach ($userEvents as $rawEvent) {
                        $event = json_decode($rawEvent, true);
                        if (!$event) {
                            $lastUserIdx++;
                            continue;
                        }

                        if (($event['event_type'] ?? '') !== 'notification') {
                            $lastUserIdx++;
                            continue;
                        }

                        echo "data: " . json_encode($event) . "\n\n";
                        if (ob_get_level()) ob_flush();
                        flush();
                        $lastUserIdx++;
                    }
                }

                // Fetch all cases that are currently active
                $activeCases = LegalCase::where('user_id', $userId)
                    ->whereNotIn('status', ['failed', 'cancelled', 'paused', 'completed', 'completed_with_warnings'])
                    ->select('id', 'title', 'status')
                    ->get();

                foreach ($activeCases as $case) {
                    $key = "case:{$case->id}:events";
                    $lastIdx = $lastSeen[$case->id] ?? -1;
                    $total = Redis::llen($key);

                    if ($total > $lastIdx + 1) {
                        $newEvents = Redis::lrange($key, $lastIdx + 1, -1);
                        foreach ($newEvents as $rawEvent) {
                            $event = json_decode($rawEvent, true);
                            if (!$event) continue;

                            $eventType = $event['event_type'] ?? '';

                            // Only emit notification-worthy events
                            if (in_array($eventType, [
                                'agent.completed',
                                'agent.failed',
                                'agent.started',
                                'agent.low_confidence',
                                'pipeline.halted',
                                'pipeline.paused',
                                'pipeline.timeout_warning',
                                'case.status_changed',
                            ], true)) {
                                $severity = match ($eventType) {
                                    'agent.failed', 'pipeline.halted', 'pipeline.paused' => 'error',
                                    'pipeline.timeout_warning', 'agent.low_confidence' => 'warning',
                                    'agent.completed', 'case.status_changed' => 'success',
                                    default => 'info',
                                };

                                $notification = [
                                    'event_type' => 'notification',
                                    'notification_type' => $eventType,
                                    'severity' => $severity,
                                    'case_id' => $case->id,
                                    'case_title' => $case->title,
                                    'case_status' => $case->status instanceof \BackedEnum ? $case->status->value : $case->status,
                                    'agent_number' => $event['agent_number'] ?? null,
                                    'agent_name' => $event['agent_name'] ?? null,
                                    'timestamp' => $event['timestamp'] ?? now()->toISOString(),
                                    'url' => route('cases.show', $case->id),
                                ];

                                if ($eventType === 'agent.failed' || $eventType === 'pipeline.halted' || $eventType === 'pipeline.paused') {
                                    $notification['error'] = $event['error'] ?? $event['halt_reason'] ?? $event['reason'] ?? null;
                                }

                                if ($eventType === 'case.status_changed') {
                                    $notification['new_status'] = $event['status'] ?? null;
                                }

                                if ($eventType === 'agent.low_confidence') {
                                    $notification['confidence_score'] = $event['confidence_score'] ?? null;
                                    $notification['threshold'] = $event['threshold'] ?? null;
                                }

                                if ($eventType === 'pipeline.timeout_warning') {
                                    $notification['elapsed_minutes'] = $event['elapsed_minutes'] ?? null;
                                    $notification['remaining_minutes'] = $event['remaining_minutes'] ?? null;
                                }

                                echo "data: " . json_encode($notification) . "\n\n";
                                if (ob_get_level()) ob_flush();
                                flush();
                            }

                            $lastIdx++;
                        }
                        $lastSeen[$case->id] = $lastIdx;
                    }
                }

                // Keepalive
                echo ": ping\n\n";
                if (ob_get_level()) ob_flush();
                flush();

                usleep(500000); // poll every 500ms
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
