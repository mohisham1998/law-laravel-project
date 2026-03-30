<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckOpenRouterCommand extends Command
{
    protected $signature = 'openrouter:check
                            {--test-completion : Also send a minimal chat request to verify case runs}';

    protected $description = 'Check OpenRouter API key and credit/usage so you know if you can run a case.';

    public function handle(): int
    {
        $apiKey = config('openrouter.api_key');
        $baseUrl = rtrim(config('openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');

        $this->info('OpenRouter API check');
        $this->newLine();

        if (empty($apiKey)) {
            $this->error('OPENROUTER_API_KEY is not set in .env');
            $this->line('Set OPENROUTER_API_KEY to your key, then run this command again.');
            return self::FAILURE;
        }

        $this->line('Key: ' . substr($apiKey, 0, 8) . '...' . substr($apiKey, -4));
        $this->newLine();

        // 1) GET /key – key info and credits (works with normal API key)
        $keyUrl = $baseUrl . '/key';
        $response = Http::timeout(15)
            ->withToken($apiKey)
            ->get($keyUrl);

        if ($response->successful()) {
            $data = $response->json('data', []);
            $limitRemaining = $data['limit_remaining'] ?? null;
            $limit = $data['limit'] ?? null;
            $usage = $data['usage'] ?? 0;
            $usageDaily = $data['usage_daily'] ?? 0;
            $isFreeTier = $data['is_free_tier'] ?? true;

            $this->info('API key is valid.');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Credits remaining', $limitRemaining === null ? 'Unlimited' : (string) $limitRemaining],
                    ['Credit limit', $limit === null ? 'None' : (string) $limit],
                    ['Usage (all time)', (string) $usage],
                    ['Usage (today)', (string) $usageDaily],
                    ['Free tier', $isFreeTier ? 'Yes' : 'No'],
                ]
            );

            $canRun = $limitRemaining === null || (float) $limitRemaining > 0;
            if ($canRun) {
                $this->info('You have credit available; you can run cases.');
            } else {
                $this->warn('No credits remaining. Add credits at https://openrouter.ai/credits to run cases.');
            }
        } else {
            $status = $response->status();
            $body = $response->json();
            $message = $body['error']['message'] ?? $response->body();

            if ($status === 401) {
                $this->error('Invalid or expired API key.');
                $this->line($message);
                return self::FAILURE;
            }
            if ($status === 402) {
                $this->warn('Payment required: no credits (or negative balance).');
                $this->line($message);
                return self::FAILURE;
            }

            $this->error("Request failed (HTTP {$status}): {$message}");
            return self::FAILURE;
        }

        $this->newLine();

        // 2) Optional: minimal chat completion to verify we can run a case
        if ($this->option('test-completion')) {
            $this->info('Sending a minimal chat request to verify case execution...');
            $model = config('openrouter.default_model', 'anthropic/claude-3.5-sonnet');
            $chatUrl = $baseUrl . '/chat/completions';
            $chatResponse = Http::timeout(30)
                ->withToken($apiKey)
                ->post($chatUrl, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Reply with exactly: OK'],
                    ],
                    'max_tokens' => 5,
                ]);

            if ($chatResponse->successful()) {
                $content = $chatResponse->json('choices.0.message.content', '');
                $this->info("Test completion succeeded. Model replied: " . trim($content));
                $this->info('You can run cases with this key.');
            } else {
                $this->warn('Test completion failed: ' . ($chatResponse->json('error.message') ?? $chatResponse->body()));
                $this->line('Check your key and credits; cases may still work for some models.');
            }
        } else {
            $this->line('Tip: run with <info>--test-completion</info> to send a minimal chat request and confirm case execution.');
        }

        return self::SUCCESS;
    }
}
