<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Services\Cost\CostCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class SettingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $key = 'cases:' . $user->id;
        $limit = config('legal.rate_limit_cases_per_hour', 10);

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'selected_model' => $user->selected_model,
                    'confidence_threshold' => (float) $user->confidence_threshold,
                    'total_tokens_consumed' => (int) $user->total_tokens_consumed,
                    'total_cost_usd' => (float) $user->total_cost_usd,
                ],
                'rate_limit' => [
                    'cases_this_hour' => RateLimiter::attempts($key),
                    'limit' => $limit,
                    'resets_at' => now()->addSeconds(RateLimiter::availableIn($key))->toIso8601String(),
                ],
                'system' => [
                    'skill_version' => config('legal.skill_version', 'v2.4.0'),
                    'skill_updated_at' => file_exists(config('legal.skill_path', ''))
                        ? date('c', filemtime(config('legal.skill_path')))
                        : now()->toIso8601String(),
                ],
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->only(['selected_model', 'confidence_threshold']);
        $data = array_filter($data, fn ($v) => $v !== null);
        $user->update($data);

        return response()->json([
            'data' => [
                'selected_model' => $user->selected_model,
                'confidence_threshold' => (float) $user->confidence_threshold,
            ],
            'meta' => ['message' => 'Settings updated successfully. New cases will use these settings.'],
        ]);
    }

    public function models(Request $request): JsonResponse
    {
        $default = config('openrouter.default_model');
        $models = Cache::remember('openrouter_models', 86400, function () use ($default) {
            try {
                $apiKey = config('openrouter.api_key');
                if (empty($apiKey)) {
                    return [['id' => $default, 'name' => 'Claude 3.5 Sonnet', 'provider' => 'Anthropic', 'context_window' => 200000, 'pricing' => ['input_per_million_tokens' => 3, 'output_per_million_tokens' => 15], 'is_default' => true]];
                }
                $response = Http::timeout(10)->withToken($apiKey)->get('https://openrouter.ai/api/v1/models');
                $data = $response->json();
                $list = $data['data'] ?? [];
                return array_map(function ($m) use ($default) {
                    $id = $m['id'] ?? '';
                    $p = $m['pricing'] ?? [];
                    $in = $p['prompt_token_price'] ?? 0;
                    $out = $p['completion_token_price'] ?? 0;
                    return [
                        'id' => $id,
                        'name' => $m['name'] ?? $id,
                        'provider' => $m['architecture']['provider'] ?? $m['organization_id'] ?? 'Unknown',
                        'context_window' => $m['context_length'] ?? 0,
                        'pricing' => [
                            'input_per_million_tokens' => $in ? round(1 / (float) $in * 1_000_000, 2) : 0,
                            'output_per_million_tokens' => $out ? round(1 / (float) $out * 1_000_000, 2) : 0,
                        ],
                        'is_default' => $id === $default,
                    ];
                }, array_slice($list, 0, 80));
            } catch (\Throwable) {
                return [['id' => $default, 'name' => 'Claude 3.5 Sonnet', 'provider' => 'Anthropic', 'context_window' => 200000, 'pricing' => ['input_per_million_tokens' => 3, 'output_per_million_tokens' => 15], 'is_default' => true]];
            }
        });

        $search = $request->get('search');
        if ($search) {
            $models = array_values(array_filter($models, fn ($m) => stripos($m['name'] ?? '', $search) !== false || stripos($m['id'] ?? '', $search) !== false));
        }

        return response()->json([
            'data' => $models,
            'meta' => ['cached_at' => now()->toIso8601String(), 'cache_expires_in_hours' => 24],
        ]);
    }

    public function costBreakdown(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $user->cases()->where('total_tokens', '>', 0);

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $cases = $query->get();
        $costCalc = app(CostCalculator::class);
        $rate = $costCalc->getUsdToSarRate();

        $rows = $cases->map(fn ($c) => [
            'case_id' => $c->id,
            'case_title' => $c->title,
            'date' => $c->created_at?->format('Y-m-d'),
            'tokens_used' => $c->total_tokens,
            'cost_usd' => (float) $c->total_cost_usd,
            'cost_sar' => $costCalc->usdToSar((float) $c->total_cost_usd),
            'model_used' => $c->model_used,
        ]);

        $totalTokens = $cases->sum('total_tokens');
        $totalUsd = $cases->sum(fn ($c) => (float) $c->total_cost_usd);

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total_tokens' => $totalTokens,
                'total_cost_usd' => round($totalUsd, 4),
                'total_cost_sar' => $costCalc->usdToSar($totalUsd),
                'exchange_rate' => $rate,
                'exchange_rate_updated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function costBreakdownExport(Request $request)
    {
        $user = $request->user();
        $query = $user->cases()->where('total_tokens', '>', 0);
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        $cases = $query->get();
        $costCalc = app(CostCalculator::class);

        $csv = "Case ID,Case Title,Date,Tokens Used,Cost (USD),Cost (SAR),Model Used\n";
        foreach ($cases as $c) {
            $csv .= sprintf(
                "%s,\"%s\",%s,%d,%.4f,%.2f,\"%s\"\n",
                $c->id,
                str_replace('"', '""', $c->title),
                $c->created_at?->format('Y-m-d'),
                $c->total_tokens,
                (float) $c->total_cost_usd,
                $costCalc->usdToSar((float) $c->total_cost_usd),
                str_replace('"', '""', $c->model_used ?? '')
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="cost_breakdown.csv"',
        ]);
    }

    public function regenerateToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'data' => ['token' => $token],
            'meta' => ['message' => 'Token regenerated successfully', 'expires_in_days' => 7],
        ]);
    }
}
