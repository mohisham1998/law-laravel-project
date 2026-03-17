<?php

namespace Tests\Unit\Services;

use App\Models\LegalCase;
use App\Services\PdfExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfExportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_throws_when_no_final_brief(): void
    {
        $case = LegalCase::factory()->create(['status' => 'phase2_completed']);
        Storage::fake('local');

        $service = new PdfExportService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('لا يوجد مذكرة نهائية');

        $service->generate($case);
    }

    public function test_generate_returns_pdf_content_when_brief_exists(): void
    {
        $case = LegalCase::factory()->create(['status' => 'phase2_completed']);
        Storage::fake('local');
        Storage::disk('local')->put("cases/{$case->id}/outputs/09_final_brief_v2.md", "# المذكرة\n\nمحتوى تجريبي.");

        $service = new PdfExportService();
        $pdf = $service->generate($case);

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
