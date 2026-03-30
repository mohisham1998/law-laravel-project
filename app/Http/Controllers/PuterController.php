<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PuterController extends Controller
{
    public function getPuterModels()
    {
        $cacheKey = 'puter_models';
        $ttl      = config('puter.cache_ttl', 300);

        $result = Cache::remember($cacheKey, $ttl, function () {
            try {
                $baseUrl  = rtrim(config('puter.api_base_url', 'https://api.puter.com'), '/');
                $endpoint = $baseUrl . config('puter.models_endpoint', '/puterai/chat/models/details');

                $response = Http::timeout(10)->get($endpoint);

                if ($response->successful()) {
                    $raw  = $response->json();
                    // API returns {"models": [...]} wrapper
                    $list = $raw['models'] ?? (is_array($raw) ? $raw : []);

                    if (empty($list)) {
                        return ['ok' => true, 'models' => $this->getFallbackModels(), 'fallback' => true];
                    }

                    $models = collect($list)->map(function ($m) {
                        // Costs are in USD-cents per `tokens` (usually 1,000,000)
                        $costs    = $m['costs'] ?? null;
                        $tokens   = (float) ($costs['tokens'] ?? 1_000_000);
                        $inKey    = $m['input_cost_key']  ?? 'input_tokens';
                        $outKey   = $m['output_cost_key'] ?? 'output_tokens';
                        $inCents  = (float) ($costs[$inKey]  ?? 0);
                        $outCents = (float) ($costs[$outKey] ?? 0);

                        $isFree = $costs === null || ($inCents === 0.0 && $outCents === 0.0);

                        // Convert to USD per token, then per 1M tokens for display
                        $inUsdPer1M  = $tokens > 0 ? round($inCents  / 100 / $tokens * 1_000_000, 4) : 0;
                        $outUsdPer1M = $tokens > 0 ? round($outCents / 100 / $tokens * 1_000_000, 4) : 0;

                        if ($isFree) {
                            $pricingLabel = 'مجاني';
                        } else {
                            $pricingLabel = '$' . number_format($inUsdPer1M, 2) . ' / $' . number_format($outUsdPer1M, 2) . ' per 1M';
                        }

                        $rawName  = $m['name'] ?? null;
                        $provider = $m['provider'] ?? '';
                        // Strip redundant "(OpenRouter)" suffix Puter injects into some names
                        $cleanName = $rawName
                            ? trim(preg_replace('/\s*\(OpenRouter\)\s*$/i', '', $rawName))
                            : null;
                        // Fall back to a humanised version of the id
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
                // Fall through to static fallback
            }

            return ['ok' => true, 'models' => $this->getFallbackModels(), 'fallback' => true];
        });

        return response()->json($result);
    }

    private function getFallbackModels(): array
    {
        return [
            ['id' => 'gpt-5-nano',        'name' => 'GPT-5 Nano',        'tier' => 'free', 'pricing' => ['prompt' => '0',          'completion' => '0'],          'context_length' => 128000,  'description' => 'Fast, free model via Puter'],
            ['id' => 'gpt-4o',            'name' => 'GPT-4o',            'tier' => 'paid', 'pricing' => ['prompt' => '0.000005',    'completion' => '0.000015'],   'context_length' => 128000,  'description' => ''],
            ['id' => 'gpt-4o-mini',       'name' => 'GPT-4o Mini',       'tier' => 'free', 'pricing' => ['prompt' => '0',          'completion' => '0'],          'context_length' => 128000,  'description' => ''],
            ['id' => 'claude-sonnet-4-5', 'name' => 'Claude Sonnet 4.5', 'tier' => 'free', 'pricing' => ['prompt' => '0',          'completion' => '0'],          'context_length' => 200000,  'description' => ''],
            ['id' => 'claude-opus-4-5',   'name' => 'Claude Opus 4.5',   'tier' => 'paid', 'pricing' => ['prompt' => '0.000015',   'completion' => '0.000075'],   'context_length' => 200000,  'description' => ''],
            ['id' => 'gemini-2.5-flash',  'name' => 'Gemini 2.5 Flash',  'tier' => 'free', 'pricing' => ['prompt' => '0',          'completion' => '0'],          'context_length' => 1000000, 'description' => ''],
        ];
    }
}
