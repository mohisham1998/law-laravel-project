<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function index()
    {
        $models = $this->getOpenRouterModels();
        $selectedModel = auth()->user()->selected_model ?? config('openrouter.default_model', 'anthropic/claude-3.5-sonnet');
        $llmProvider = auth()->user()->llm_provider ?? 'openrouter';
        $puterModel = auth()->user()->puter_model ?? 'gpt-5-nano';
        $puterDisclosureAcknowledged = (bool) (auth()->user()->puter_disclosure_acknowledged ?? false);
        $notificationsEnabled = (bool) (auth()->user()->notifications_enabled ?? true);
        $openrouterApiKey = auth()->user()->openrouter_api_key ?? '';

        return view('pages.settings', compact('models', 'selectedModel', 'llmProvider', 'puterModel', 'puterDisclosureAcknowledged', 'notificationsEnabled', 'openrouterApiKey'));
    }

    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'                          => 'required|string|max:255',
                'email'                         => 'required|email|unique:users,email,' . auth()->id(),
                'selected_model'                => 'nullable|string',
                'llm_provider'                  => 'nullable|in:openrouter,puter',
                'puter_model'                   => 'nullable|string|max:255',
                'puter_disclosure_acknowledged' => 'nullable|boolean',
                'notifications_enabled'         => 'nullable|boolean',
                'openrouter_api_key'            => 'nullable|string|max:500',
            ]);

            $updateData = array_filter([
                'name'               => $validated['name'],
                'email'              => $validated['email'],
                'selected_model'     => $validated['selected_model'] ?? null,
                'llm_provider'       => $validated['llm_provider'] ?? null,
                'puter_model'        => $validated['puter_model'] ?? null,
                'notifications_enabled' => (bool) ($validated['notifications_enabled'] ?? false),
                'openrouter_api_key' => $validated['openrouter_api_key'] ?? null,
            ], fn ($v) => $v !== null);

            // Only update disclosure flag when it becomes true (never reset to false)
            if (!empty($validated['puter_disclosure_acknowledged'])) {
                $updateData['puter_disclosure_acknowledged'] = true;
            }

            auth()->user()->update($updateData);

            return back()->with('success', 'تم تحديث الإعدادات بنجاح');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', 'حدث خطأ أثناء الحفظ. يرجى المحاولة مرة أخرى.');
        }
    }

    public function getPuterModels()
    {
        $cacheKey = 'puter_models';
        $ttl = config('puter.cache_ttl', 300);

        $result = Cache::remember($cacheKey, $ttl, function () {
            try {
                $baseUrl = rtrim(config('puter.api_base_url', 'https://api.puter.com'), '/');
                $endpoint = $baseUrl . config('puter.models_endpoint', '/puterai/chat/models/details');

                $response = Http::timeout(10)->get($endpoint);

                if ($response->successful()) {
                    $raw = $response->json();
                    // API returns {"models": [...]} wrapper
                    $list = $raw['models'] ?? (is_array($raw) ? $raw : []);

                    if (empty($list)) {
                        return ['ok' => true, 'models' => $this->getPuterFallbackModels(), 'fallback' => true];
                    }

                    $models = collect($list)->map(function ($m) {
                        $costs    = $m['costs'] ?? null;
                        $tokens   = (float) ($costs['tokens'] ?? 1_000_000);
                        $inKey    = $m['input_cost_key']  ?? 'input_tokens';
                        $outKey   = $m['output_cost_key'] ?? 'output_tokens';
                        $inCents  = (float) ($costs[$inKey]  ?? 0);
                        $outCents = (float) ($costs[$outKey] ?? 0);

                        $isFree = $costs === null || ($inCents === 0.0 && $outCents === 0.0);

                        $inUsdPer1M  = $tokens > 0 ? round($inCents  / 100 / $tokens * 1_000_000, 4) : 0;
                        $outUsdPer1M = $tokens > 0 ? round($outCents / 100 / $tokens * 1_000_000, 4) : 0;

                        $pricingLabel = $isFree
                            ? 'مجاني'
                            : '$' . number_format($inUsdPer1M, 2) . ' / $' . number_format($outUsdPer1M, 2) . ' per 1M';

                        $rawName   = $m['name'] ?? null;
                        $cleanName = $rawName
                            ? trim(preg_replace('/\s*\(OpenRouter\)\s*$/i', '', $rawName))
                            : null;
                        if (!$cleanName) {
                            $cleanName = ucwords(str_replace(['-', '_', '.'], ' ', $m['id'] ?? ''));
                        }

                        return [
                            'id'            => $m['id'] ?? '',
                            'name'          => $cleanName,
                            'tier'          => $isFree ? 'free' : 'paid',
                            'pricing_label' => $pricingLabel,
                            'pricing'       => [
                                'prompt'     => (string) ($inUsdPer1M / 1_000_000),
                                'completion' => (string) ($outUsdPer1M / 1_000_000),
                            ],
                            'context_length' => $m['context'] ?? ($m['context_length'] ?? 0),
                        ];
                    })->filter(fn ($m) => !empty($m['id']))->values()->toArray();

                    return ['ok' => true, 'models' => $models, 'fallback' => false];
                }
            } catch (\Exception $e) {
                // Fall through to fallback
            }

            return ['ok' => true, 'models' => $this->getPuterFallbackModels(), 'fallback' => true];
        });

        return response()->json($result);
    }

    private function getPuterFallbackModels(): array
    {
        return [
            ['id' => 'gpt-5-nano',   'name' => 'GPT-5 Nano',   'tier' => 'free', 'pricing' => ['prompt' => '0', 'completion' => '0'], 'context_length' => 128000, 'description' => 'Fast, free model via Puter'],
            ['id' => 'gpt-4o',       'name' => 'GPT-4o',       'tier' => 'paid', 'pricing' => ['prompt' => '0.000005', 'completion' => '0.000015'], 'context_length' => 128000, 'description' => ''],
            ['id' => 'gpt-4o-mini',  'name' => 'GPT-4o Mini',  'tier' => 'free', 'pricing' => ['prompt' => '0', 'completion' => '0'], 'context_length' => 128000, 'description' => ''],
            ['id' => 'claude-sonnet-4-5', 'name' => 'Claude Sonnet 4.5', 'tier' => 'free', 'pricing' => ['prompt' => '0', 'completion' => '0'], 'context_length' => 200000, 'description' => ''],
            ['id' => 'claude-opus-4-5',   'name' => 'Claude Opus 4.5',   'tier' => 'paid', 'pricing' => ['prompt' => '0.000015', 'completion' => '0.000075'], 'context_length' => 200000, 'description' => ''],
            ['id' => 'gemini-2.5-flash',  'name' => 'Gemini 2.5 Flash',  'tier' => 'free', 'pricing' => ['prompt' => '0', 'completion' => '0'], 'context_length' => 1000000, 'description' => ''],
        ];
    }

    private function getOpenRouterModels()
    {
        return Cache::remember('openrouter_models', 3600, function () {
            try {
                $response = Http::timeout(10)
                    ->retry(2, 1000)
                    ->get('https://openrouter.ai/api/v1/models');
                
                if ($response->successful()) {
                    $data = $response->json();
                    $models = collect($data['data'] ?? [])
                        ->map(function ($model) {
                            return [
                                'id' => $model['id'],
                                'name' => $model['name'] ?? $model['id'],
                                'context_length' => $model['context_length'] ?? 0,
                                'pricing' => [
                                    'prompt' => $model['pricing']['prompt'] ?? '0',
                                    'completion' => $model['pricing']['completion'] ?? '0',
                                ],
                                'description' => $model['description'] ?? '',
                            ];
                        })
                        ->sortBy('name')
                        ->values()
                        ->toArray();
                    
                    return $models;
                }
            } catch (\Exception $e) {
                // Silently fall through to fallback models
            }
            
            return $this->getFallbackModels();
        });
    }

    private function getFallbackModels()
    {
        return [
            [
                'id' => 'anthropic/claude-sonnet-4.6',
                'name' => 'Claude Sonnet 4.6',
                'context_length' => 1000000,
                'pricing' => ['prompt' => '0.000003', 'completion' => '0.000015'],
                'description' => 'Best for coding and analysis',
            ],
            [
                'id' => 'anthropic/claude-opus-4.6',
                'name' => 'Claude Opus 4.6',
                'context_length' => 1000000,
                'pricing' => ['prompt' => '0.000005', 'completion' => '0.000025'],
                'description' => 'Top-tier reasoning and coding',
            ],
            [
                'id' => 'anthropic/claude-3.5-sonnet',
                'name' => 'Claude 3.5 Sonnet',
                'context_length' => 200000,
                'pricing' => ['prompt' => '0.000006', 'completion' => '0.000030'],
                'description' => 'Strong coding and analysis',
            ],
            [
                'id' => 'openai/gpt-4o',
                'name' => 'GPT-4o',
                'context_length' => 128000,
                'pricing' => ['prompt' => '0.000005', 'completion' => '0.000015'],
                'description' => 'Fast and capable GPT-4',
            ],
            [
                'id' => 'google/gemini-2.5-flash',
                'name' => 'Gemini 2.5 Flash',
                'context_length' => 1050000,
                'pricing' => ['prompt' => '0.00000030', 'completion' => '0.00000250'],
                'description' => 'Large context, fast model',
            ],
            [
                'id' => 'google/gemini-2.5-pro',
                'name' => 'Gemini 2.5 Pro',
                'context_length' => 1050000,
                'pricing' => ['prompt' => '0.00000125', 'completion' => '0.000010'],
                'description' => 'Advanced reasoning model',
            ],
            [
                'id' => 'google/gemini-3-flash-preview',
                'name' => 'Gemini 3 Flash Preview',
                'context_length' => 1050000,
                'pricing' => ['prompt' => '0.00000050', 'completion' => '0.000003'],
                'description' => 'Latest high-speed reasoning model',
            ],
            [
                'id' => 'meta-llama/llama-3.3-70b-instruct',
                'name' => 'Llama 3.3 70B',
                'context_length' => 131072,
                'pricing' => ['prompt' => '0.00000010', 'completion' => '0.00000032'],
                'description' => 'Open source, cost-effective',
            ],
            [
                'id' => 'meta-llama/llama-4-scout',
                'name' => 'Llama 4 Scout',
                'context_length' => 328000,
                'pricing' => ['prompt' => '0.00000008', 'completion' => '0.00000030'],
                'description' => 'Multimodal, 10M context',
            ],
            [
                'id' => 'mistralai/mistral-small-2603',
                'name' => 'Mistral Small 4',
                'context_length' => 262144,
                'pricing' => ['prompt' => '0.00000015', 'completion' => '0.00000060'],
                'description' => 'Fast and efficient',
            ],
        ];
    }

    public function getModels()
    {
        $models = $this->getOpenRouterModels();
        return response()->json($models);
    }

    /**
     * Fetch live OpenRouter key info + credits. Used internally and by checkOpenRouter().
     * Calls both /auth/key (usage/limit) and /credits (total credits purchased).
     */
    private function fetchOpenRouterBalance(?string $keyOverride = null): array
    {
        $apiKey = $keyOverride ?: auth()->user()?->openrouter_api_key ?: config('openrouter.api_key');
        if (empty($apiKey)) {
            return ['ok' => false, 'error' => 'no_key', 'message' => 'لم يتم تعيين مفتاح OpenRouter في الإعدادات.'];
        }

        $baseUrl = rtrim(config('openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');
        $headers = ['Authorization' => 'Bearer ' . $apiKey, 'HTTP-Referer' => config('app.url')];

        // Fetch key metadata
        $keyResp = Http::timeout(10)->withHeaders($headers)->get($baseUrl . '/auth/key');
        if (!$keyResp->successful()) {
            $status = $keyResp->status();
            if ($status === 401) return ['ok' => false, 'error' => 'invalid_key', 'message' => 'مفتاح API غير صالح أو منتهي.'];
            return ['ok' => false, 'error' => 'request_failed', 'message' => "HTTP {$status}"];
        }
        $keyData = $keyResp->json('data', []);

        // Fetch purchased credits total
        $creditsResp = Http::timeout(10)->withHeaders($headers)->get($baseUrl . '/credits');
        $creditsData = $creditsResp->successful() ? ($creditsResp->json('data', []) ?: []) : [];

        $totalCredits  = (float) ($creditsData['total_credits'] ?? 0);
        $totalUsage    = (float) ($creditsData['total_usage']   ?? $keyData['usage'] ?? 0);
        $remaining     = round($totalCredits - $totalUsage, 4);
        $isDepleted    = $totalCredits > 0 && $remaining <= 0;
        $isLow         = !$isDepleted && $totalCredits > 0 && $remaining < 1.0; // < $1 remaining
        $usageDaily    = (float) ($keyData['usage_daily'] ?? 0);
        $canRun        = !$isDepleted;

        return [
            'ok'                      => true,
            'total_credits'           => $totalCredits,
            'total_credits_sar'       => round($totalCredits * 3.75, 2),
            'total_usage'             => $totalUsage,
            'usage_sar'               => round($totalUsage * 3.75, 2),
            'usage_daily'             => $usageDaily,
            'remaining'               => $remaining,
            'remaining_sar'           => round($remaining * 3.75, 2),
            'remaining_display'       => $remaining >= 0 ? (string) round($remaining, 2) : '0.00',
            'is_depleted'             => $isDepleted,
            'is_low'                  => $isLow,
            'can_run_cases'           => $canRun,
            // Legacy fields kept for backward compatibility with existing JS
            'limit_remaining'         => $remaining >= 0 ? $remaining : 0,
            'limit_remaining_display' => $remaining >= 0 ? (string) round($remaining, 2) : '0.00',
            'limit_remaining_sar'     => round(max($remaining, 0) * 3.75, 2),
            'usage'                   => $totalUsage,
            'message'                 => $isDepleted
                ? 'نفد الرصيد. أضف رصيداً من OpenRouter لتشغيل القضايا.'
                : ($isLow ? 'الرصيد منخفض (أقل من $1). يُنصح بإعادة الشحن قريباً.' : 'المفتاح صالح ويوجد رصيد. يمكنك تشغيل القضايا.'),
        ];
    }

    /**
     * Check OpenRouter API key and credit/usage (for UI).
     * GET /settings/check-openrouter
     */
    public function checkOpenRouter(Request $request)
    {
        // If a key is supplied in the request body, test it directly without touching the cache.
        if ($request->filled('api_key')) {
            return response()->json($this->fetchOpenRouterBalance($request->input('api_key')));
        }

        // Manual refresh — bust cache so user gets fresh data for their stored key.
        Cache::forget('openrouter_balance_status_' . auth()->id());
        return response()->json($this->fetchOpenRouterBalance());
    }

    /**
     * Lightweight cached balance status — used by global toast check on every page load.
     * Cached 5 minutes to avoid hammering the OpenRouter API.
     * GET /settings/openrouter-status
     */
    public function openRouterStatus()
    {
        $cacheKey = 'openrouter_balance_status_' . auth()->id();
        $data = Cache::remember($cacheKey, 300, fn () => $this->fetchOpenRouterBalance());
        return response()->json(array_merge($data, ['cached' => true]));
    }

    public function modelPreview(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|in:openrouter,puter',
            'model'    => 'required|string|max:255',
        ]);

        $demoPrompts = [
            'في سطر واحد فقط: ما معنى العدالة في القانون؟',
            'أعطني جملة عربية قصيرة تشرح دور المحامي.',
            'اكتب نصيحة قانونية عامة في جملة واحدة فقط.',
            'في جملة موجزة: لماذا توثيق الأدلة مهم؟',
        ];
        $prompt = $demoPrompts[array_rand($demoPrompts)];

        try {
            if ($validated['provider'] === 'puter') {
                $token = $request->header('X-Puter-Token') ?? $request->input('puter_token');
                $token = $this->normalizeBearerToken($token);
                if (empty($token)) {
                    return response()->json(['ok' => false, 'error' => 'يجب ربط حساب Puter أولاً من الإعدادات.'], 200);
                }
                $baseUrl  = rtrim(config('puter.api_base_url', 'https://api.puter.com'), '/');
                $endpoint = $baseUrl . config('puter.chat_endpoint', '/puterai/openai/v1/chat/completions');
                $payload = [
                        'model'      => $validated['model'],
                        'messages'   => [['role' => 'user', 'content' => $prompt]],
                        'max_tokens' => 64,
                        'temperature' => 0.6,
                    ];

                $resp = Http::timeout(12)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'X-Puter-Token' => $token,
                        'Accept'        => 'application/json',
                    ])
                    ->post($endpoint, $payload);
                if (!$resp->successful()) {
                    if ($resp->status() === 401) {
                        return response()->json(['ok' => false, 'error' => 'جلسة Puter غير صالحة. أعد الربط من جديد ثم جرّب مرة أخرى.'], 200);
                    }
                    if ($resp->status() === 403) {
                        $fallbackIds = ['gpt-5-nano', 'gpt-4o-mini', 'claude-sonnet-4-5', 'gemini-2.5-flash'];
                        foreach ($fallbackIds as $fallbackId) {
                            if ($fallbackId === $validated['model']) {
                                continue;
                            }

                            $retryPayload = $payload;
                            $retryPayload['model'] = $fallbackId;
                            $retryResp = Http::timeout(12)
                                ->withHeaders([
                                    'Authorization' => 'Bearer ' . $token,
                                    'X-Puter-Token' => $token,
                                    'Accept'        => 'application/json',
                                ])
                                ->post($endpoint, $retryPayload);

                            if ($retryResp->successful()) {
                                $content = $this->extractPreviewContent($retryResp->json());
                                $content = trim((string) $content);
                                if ($content !== '') {
                                    return response()->json([
                                        'ok' => true,
                                        'content' => $content,
                                        'fallback_model' => $fallbackId,
                                    ]);
                                }
                            }
                        }

                        return response()->json(['ok' => false, 'error' => 'تعذر التحقق من صلاحية Puter (403). الحساب متصل لكن هذا النموذج غير مسموح حالياً. جرّب إعادة الربط أو نموذجاً مجانياً آخر.'], 200);
                    }
                    return response()->json(['ok' => false, 'error' => 'Puter API error ' . $resp->status()], 200);
                }
                $content = $this->extractPreviewContent($resp->json());
            } else {
                $apiKey = auth()->user()?->openrouter_api_key ?: config('openrouter.api_key', '');
                if (empty($apiKey)) {
                    return response()->json(['ok' => false, 'error' => 'مفتاح OpenRouter غير مضبوط حالياً.'], 200);
                }
                $baseUrl = rtrim(config('openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');
                $resp = Http::timeout(12)
                    ->withToken($apiKey)
                    ->withHeaders(['HTTP-Referer' => config('app.url', 'https://example.com')])
                    ->post($baseUrl . '/chat/completions', [
                        'model'      => $validated['model'],
                        'messages'   => [['role' => 'user', 'content' => $prompt]],
                        'max_tokens' => 64,
                        'temperature' => 0.6,
                    ]);
                if (!$resp->successful()) {
                    return response()->json(['ok' => false, 'error' => 'OpenRouter API error ' . $resp->status()], 200);
                }
                $content = $this->extractPreviewContent($resp->json());
            }

            $content = trim((string) $content);
            if ($content === '') {
                return response()->json(['ok' => false, 'error' => 'لم يتم استلام نص صالح من النموذج.'], 200);
            }

            return response()->json(['ok' => true, 'content' => $content]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 200);
        }
    }

    private function extractPreviewContent(array $data): string
    {
        $content = $data['choices'][0]['message']['content'] ?? '';

        if (is_array($content)) {
            $joined = collect($content)
                ->map(function ($item) {
                    if (is_string($item)) {
                        return $item;
                    }

                    if (is_array($item)) {
                        return $item['text'] ?? ($item['content'] ?? '');
                    }

                    return '';
                })
                ->filter()
                ->implode(' ');

            return (string) $joined;
        }

        return (string) $content;
    }

    private function normalizeBearerToken(?string $token): string
    {
        if ($token === null) {
            return '';
        }

        $token = trim($token);
        if ($token === '') {
            return '';
        }

        if (preg_match('/^bearer\s+/i', $token) === 1) {
            $token = preg_replace('/^bearer\s+/i', '', $token) ?? '';
        }

        return trim($token);
    }

    public function estimateCost(Request $request)
    {
        $validated = $request->validate([
            'model_id' => 'required|string',
            'prompt_tokens' => 'required|integer|min:0',
            'completion_tokens' => 'required|integer|min:0',
        ]);

        $models = $this->getOpenRouterModels();
        $model = collect($models)->firstWhere('id', $validated['model_id']);

        if (!$model) {
            return response()->json(['error' => 'Model not found'], 404);
        }

        $promptCost = floatval($model['pricing']['prompt']) * $validated['prompt_tokens'];
        $completionCost = floatval($model['pricing']['completion']) * $validated['completion_tokens'];
        $totalUSD = $promptCost + $completionCost;
        
        // Convert to SAR (1 USD = 3.75 SAR)
        $totalSAR = $totalUSD * 3.75;

        return response()->json([
            'cost_usd' => round($totalUSD, 6),
            'cost_sar' => round($totalSAR, 4),
            'prompt_cost_usd' => round($promptCost, 6),
            'completion_cost_usd' => round($completionCost, 6),
        ]);
    }
}
