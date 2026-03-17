<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitCases
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $key = 'cases:'.$user->id;
        $limit = config('legal.rate_limit_cases_per_hour', 10);

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json([
                'errors' => [[
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Rate limit exceeded. You can create '.$limit.' cases per hour. Try again later.',
                ]],
                'meta' => [
                    'retry_after' => RateLimiter::availableIn($key),
                ],
            ], 429)->withHeaders([
                'Retry-After' => RateLimiter::availableIn($key),
            ]);
        }

        $response = $next($request);

        if ($request->isMethod('POST') && $request->is('api/v1/cases')) {
            RateLimiter::hit($key, 3600); // 1 hour decay
        }

        return $response;
    }
}
