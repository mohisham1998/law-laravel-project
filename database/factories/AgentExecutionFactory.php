<?php

namespace Database\Factories;

use App\Enums\AgentStatus;
use App\Models\AgentExecution;
use App\Models\LegalCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentExecution>
 */
class AgentExecutionFactory extends Factory
{
    protected $model = AgentExecution::class;

    public function definition(): array
    {
        return [
            'case_id' => LegalCase::factory(),
            'agent_number' => fake()->numberBetween(1, 10),
            'agent_name' => fake()->slug(2),
            'status' => AgentStatus::Completed,
            'prompt_tokens' => fake()->numberBetween(100, 5000),
            'completion_tokens' => fake()->numberBetween(50, 2000),
            'total_tokens' => null,
            'cost_usd' => fake()->randomFloat(4, 0.001, 0.5),
            'duration_ms' => fake()->numberBetween(500, 5000),
            'api_latency_ms' => fake()->numberBetween(200, 3000),
            'error_message' => null,
            'retry_count' => 0,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ];
    }
}
