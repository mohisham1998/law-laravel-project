<?php

namespace Tests\Unit\Models;

use App\Models\CaseDocument;
use App\Models\EvidenceRepositoryEntry;
use App\Models\LegalCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvidenceRepositoryEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_case_and_document(): void
    {
        $entry = EvidenceRepositoryEntry::factory()->create();

        $this->assertInstanceOf(LegalCase::class, $entry->case);
        $this->assertInstanceOf(CaseDocument::class, $entry->document);
    }

    public function test_evidence_types_constant_exists(): void
    {
        $this->assertIsArray(EvidenceRepositoryEntry::EVIDENCE_TYPES);
        $this->assertArrayHasKey('contract', EvidenceRepositoryEntry::EVIDENCE_TYPES);
        $this->assertArrayHasKey('other', EvidenceRepositoryEntry::EVIDENCE_TYPES);
    }

    public function test_get_evidence_type_label_ar_returns_arabic_label(): void
    {
        $entry = EvidenceRepositoryEntry::factory()->create(['evidence_type' => 'contract']);
        $this->assertEquals('عقد', $entry->getEvidenceTypeLabelAr());
    }

    public function test_is_high_relevance_returns_true_when_score_above_threshold(): void
    {
        $entry = EvidenceRepositoryEntry::factory()->create(['relevance_score' => 0.85]);
        $this->assertTrue($entry->isHighRelevance());
    }

    public function test_is_high_relevance_returns_false_when_below_threshold(): void
    {
        $entry = EvidenceRepositoryEntry::factory()->create(['relevance_score' => 0.5]);
        $this->assertFalse($entry->isHighRelevance());
    }
}
