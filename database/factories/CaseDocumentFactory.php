<?php

namespace Database\Factories;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseDocument>
 */
class CaseDocumentFactory extends Factory
{
    protected $model = CaseDocument::class;

    public function definition(): array
    {
        $ext = fake()->randomElement(['txt', 'pdf']);
        $mimes = ['txt' => 'text/plain', 'pdf' => 'application/pdf'];
        $filename = fake()->slug(3) . '.' . $ext;

        return [
            'case_id' => LegalCase::factory(),
            'filename' => $filename,
            'file_path' => 'cases/' . fake()->uuid . '/documents/' . $filename,
            'file_size' => fake()->numberBetween(1024, 1024 * 1024),
            'mime_type' => $mimes[$ext],
            'encoding' => 'UTF-8',
        ];
    }
}
