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
    public function stream(Request $request, LegalCase $case): StreamedResponse
    {
        $key = "case:{$case->id}:events";

        return response()->stream(function () use ($key) {
            while (true) {
                $events = Redis::lrange($key, 0, -1);
                foreach ($events as $event) {
                    echo "data: {$event}\n\n";
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                }
                if (! empty($events)) {
                    Redis::del($key);
                }
                usleep(200000); // 200ms
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
