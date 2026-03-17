<?php

namespace Database\Factories;

use App\Models\LegalCase;
use App\Models\RequiredLaw;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequiredLaw>
 */
class RequiredLawFactory extends Factory
{
    protected $model = RequiredLaw::class;

    public function definition(): array
    {
        return [
            'case_id' => LegalCase::factory(),
            'law_name' => fake()->sentence(3),
            'reason' => fake()->sentence(),
            'is_uploaded' => fake()->boolean(30),
        ];
    }
}
