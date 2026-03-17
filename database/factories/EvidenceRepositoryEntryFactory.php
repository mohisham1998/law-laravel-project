<?php

namespace Database\Factories;

use App\Models\CaseDocument;
use App\Models\EvidenceRepositoryEntry;
use App\Models\LegalCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvidenceRepositoryEntry>
 */
class EvidenceRepositoryEntryFactory extends Factory
{
    protected $model = EvidenceRepositoryEntry::class;

    public function definition(): array
    {
        $case = LegalCase::factory()->create();
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        return [
            'case_id' => $case->id,
            'document_id' => $document->id,
            'evidence_type' => fake()->randomElement(array_keys(EvidenceRepositoryEntry::EVIDENCE_TYPES)),
            'relevance_score' => fake()->randomFloat(2, 0, 1),
            'notes' => fake()->optional(0.7)->sentence(),
        ];
    }
}
