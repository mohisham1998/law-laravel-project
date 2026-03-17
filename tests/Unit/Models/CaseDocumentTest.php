<?php

namespace Tests\Unit\Models;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_case(): void
    {
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertInstanceOf(LegalCase::class, $doc->case);
        $this->assertEquals($case->id, $doc->case->id);
    }

    public function test_human_readable_size_attribute(): void
    {
        $doc = CaseDocument::factory()->make(['file_size' => 1024]);
        $this->assertStringContainsString('KB', $doc->human_readable_size);

        $doc = CaseDocument::factory()->make(['file_size' => 500]);
        $this->assertStringContainsString('B', $doc->human_readable_size);
    }

    public function test_is_pdf_returns_true_for_pdf(): void
    {
        $doc = CaseDocument::factory()->make(['mime_type' => 'application/pdf']);
        $this->assertTrue($doc->isPdf());
    }

    public function test_is_pdf_returns_false_for_non_pdf(): void
    {
        $doc = CaseDocument::factory()->make(['mime_type' => 'text/plain']);
        $this->assertFalse($doc->isPdf());
    }

    public function test_is_image_returns_true_for_images(): void
    {
        $doc = CaseDocument::factory()->make(['mime_type' => 'image/jpeg']);
        $this->assertTrue($doc->isImage());
    }

    public function test_is_text_returns_true_for_plain_text(): void
    {
        $doc = CaseDocument::factory()->make(['mime_type' => 'text/plain']);
        $this->assertTrue($doc->isText());
    }

    public function test_is_docx_returns_true_for_docx(): void
    {
        $doc = CaseDocument::factory()->make([
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
        $this->assertTrue($doc->isDocx());
    }
}
