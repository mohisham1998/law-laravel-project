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
        
        return view('pages.settings', compact('models', 'selectedModel'));
    }

    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . auth()->id(),
                'selected_model' => 'nullable|string',
            ]);

            auth()->user()->update($validated);

            return back()->with('success', 'تم تحديث الإعدادات بنجاح');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', 'حدث خطأ أثناء الحفظ. يرجى المحاولة مرة أخرى.');
        }
    }

    private function getOpenRouterModels()
    {
        return Cache::remember('openrouter_models', 3600, function () {
            try {
                $response = Http::timeout(10)->get('https://openrouter.ai/api/v1/models');
                
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
                \Log::error('Failed to fetch OpenRouter models: ' . $e->getMessage());
            }
            
            // Fallback popular models if API fails
            return $this->getFallbackModels();
        });
    }

    private function getFallbackModels()
    {
        return [
            [
                'id' => 'openai/gpt-4-turbo',
                'name' => 'GPT-4 Turbo',
                'context_length' => 128000,
                'pricing' => ['prompt' => '0.00001', 'completion' => '0.00003'],
                'description' => 'Most capable GPT-4 model',
            ],
            [
                'id' => 'openai/gpt-4o',
                'name' => 'GPT-4o',
                'context_length' => 128000,
                'pricing' => ['prompt' => '0.000005', 'completion' => '0.000015'],
                'description' => 'Faster and cheaper GPT-4',
            ],
            [
                'id' => 'anthropic/claude-3.5-sonnet',
                'name' => 'Claude 3.5 Sonnet',
                'context_length' => 200000,
                'pricing' => ['prompt' => '0.000003', 'completion' => '0.000015'],
                'description' => 'Best for coding and analysis',
            ],
            [
                'id' => 'google/gemini-pro-1.5',
                'name' => 'Gemini Pro 1.5',
                'context_length' => 1000000,
                'pricing' => ['prompt' => '0.00000125', 'completion' => '0.000005'],
                'description' => 'Largest context window',
            ],
            [
                'id' => 'meta-llama/llama-3.1-70b-instruct',
                'name' => 'Llama 3.1 70B',
                'context_length' => 131072,
                'pricing' => ['prompt' => '0.00000052', 'completion' => '0.00000075'],
                'description' => 'Open source, cost-effective',
            ],
        ];
    }

    public function getModels()
    {
        $models = $this->getOpenRouterModels();
        return response()->json($models);
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
