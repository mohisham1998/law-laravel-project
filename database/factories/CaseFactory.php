<?php

namespace Database\Factories;

use App\Enums\CaseStatus;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalCase>
 */
class CaseFactory extends Factory
{
    protected $model = LegalCase::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'intake_text' => fake()->paragraphs(3, true),
            'status' => CaseStatus::Phase1Pending,
            'phase' => 1,
            'progress_percentage' => 0,
            'skill_version' => config('legal.skill_version', 'v2.4.0'),
            'skill_hash' => hash('sha256', 'test'),
            'model_used' => config('openrouter.default_model', 'anthropic/claude-3.5-sonnet'),
        ];
    }
}
