<?php

namespace App\Http\Controllers;

use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaseStreamController extends Controller
{
    /**
     * SSE stream for case events (agent.started, agent.output, agent.completed, agent.failed).
     */
    /**
     * Active statuses where the SSE stream should remain open.
     * These are statuses where the case is still being processed or waiting for user input.
     */
    private const ACTIVE_STATUSES = [
        'phase1_pending', 'phase1_processing', 'phase1_completed',
        'awaiting_laws',
        'phase2_pending', 'phase2_processing', 'phase2_completed',
        'phase3_pending', 'phase3_processing', 'phase3_completed',
        'completed_with_warnings',
    ];

    /**
     * Terminal statuses where the SSE stream should close.
     * These are final states where no more processing will occur.
     */
    private const TERMINAL_STATUSES = [
        'failed',
        'paused',
        'cancelled',
        'completed',
    ];

    public function stream(Request $request, LegalCase $case): StreamedResponse
    {
        $key = "case:{$case->id}:events";

        // Support resuming from Last-Event-ID (sent by browser on reconnect)
        $lastId = (int) $request->header('Last-Event-ID', -1);
        $readIndex = max(0, $lastId + 1); // next event to deliver

        // Release the session lock before streaming so other browser requests
        // (e.g. AJAX calls, page navigations) are not blocked while SSE is open.
        if (session()->isStarted()) {
            session()->save();
        }

        return response()->stream(function () use ($key, $case, $readIndex) {
            $startTime = time();
            $maxDuration = 600; // 10 minutes max
            $idleTimeout = 90;  // 90 seconds idle → disconnect (agents can take 60 s+)
            $lastEventTime = time();
            $eventId = $readIndex;

            // Send initial connection message
            echo "data: " . json_encode([
                'event_type' => 'connection.established',
                'case_id' => $case->id,
                'timestamp' => now()->toISOString(),
            ]) . "\n\n";
            if (ob_get_level()) ob_flush();
            flush();

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                // Check timeouts
                $elapsed = time() - $startTime;
                $idleTime = time() - $lastEventTime;

                if ($elapsed > $maxDuration || $idleTime > $idleTimeout) {
                    echo "data: " . json_encode([
                        'event_type' => 'connection.timeout',
                        'reason' => $elapsed > $maxDuration ? 'max_duration' : 'idle',
                    ]) . "\n\n";
                    if (ob_get_level()) ob_flush();
                    flush();
                    break;
                }

                // Check if case has reached a terminal status
                $case->refresh();
                $status = $case->status->value ?? $case->status;
                if (in_array($status, self::TERMINAL_STATUSES, true)) {
                    echo "data: " . json_encode([
                        'event_type' => 'case.status_changed',
                        'status' => $status,
                    ]) . "\n\n";
                    if (ob_get_level()) ob_flush();
                    flush();
                    break;
                }

                // Fetch new events from Redis list using index (non-destructive)
                // Events stay in Redis (TTL-based expiry) so reconnects can replay missed events
                $total = Redis::llen($key);
                if ($eventId < $total) {
                    $newEvents = Redis::lrange($key, $eventId, -1);
                    foreach ($newEvents as $event) {
                        echo "id: {$eventId}\n";
                        echo "data: {$event}\n\n";
                        if (ob_get_level()) ob_flush();
                        flush();
                        $eventId++;
                    }
                    $lastEventTime = time();
                } else {
                    // Send a keepalive comment to prevent proxy timeout
                    echo ": keepalive\n\n";
                    if (ob_get_level()) ob_flush();
                    flush();
                }

                usleep(200000); // 200ms polling interval
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
