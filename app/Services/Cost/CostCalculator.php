<?php

namespace App\Services\Cost;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CostCalculator
{
    /**
     * OpenRouter pricing (USD per 1M tokens) - approximate for Claude 3.5 Sonnet.
     * Update per OpenRouter pricing page.
     */
    protected float $inputPricePerM = 3.00;
    protected float $outputPricePerM = 15.00;

    public function calculateUsd(int $promptTokens, int $completionTokens): float
    {
        $inputCost = ($promptTokens / 1_000_000) * $this->inputPricePerM;
        $outputCost = ($completionTokens / 1_000_000) * $this->outputPricePerM;
        return round($inputCost + $outputCost, 6);
    }

    public function usdToSar(float $usd): float
    {
        $rate = $this->getUsdToSarRate();
        return round($usd * $rate, 2);
    }

    public function getUsdToSarRate(): float
    {
        return Cache::remember('usd_to_sar_rate', 86400, function () {
            try {
                $response = Http::timeout(5)->get('https://api.exchangerate-api.com/v4/latest/USD');
                $data = $response->json();
                return (float) ($data['rates']['SAR'] ?? 3.75);
            } catch (\Throwable) {
                return 3.75; // fallback
            }
        });
    }
}
