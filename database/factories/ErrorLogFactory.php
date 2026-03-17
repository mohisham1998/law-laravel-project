<?php

namespace Database\Factories;

use App\Enums\ErrorType;
use App\Models\AgentExecution;
use App\Models\ErrorLog;
use App\Models\LegalCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ErrorLog>
 */
class ErrorLogFactory extends Factory
{
    protected $model = ErrorLog::class;

    public function definition(): array
    {
        return [
            'case_id' => LegalCase::factory(),
            'agent_execution_id' => AgentExecution::factory(),
            'agent_number' => fake()->numberBetween(1, 10),
            'error_type' => fake()->randomElement(array_map(fn ($e) => $e->value, ErrorType::cases())),
            'error_details' => fake()->sentence(),
            'fix_applied' => fake()->sentence(),
            'lesson_learned' => fake()->optional(0.5)->sentence(),
            'confidence_score' => fake()->optional(0.5)->randomFloat(3, 0, 1),
        ];
    }
}
