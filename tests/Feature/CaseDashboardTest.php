<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

/** Dashboard output and PDF/stream/concurrent-limit tests. Skipped when Redis extension is not loaded. */
#[RequiresPhpExtension('redis')]
class CaseDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Redis::fake();
    }

    public function test_case_show_page_returns_200_and_includes_dashboard_sections(): void
    {
        $case = LegalCase::factory()->create([
            'user_id' => $this->user->id,
            'status' => CaseStatus::Phase2Processing,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('cases.show', $case));

        $response->assertStatus(200);
        $response->assertSee('مراحل التحليل الذكي');
        $response->assertSee('سلسلة المخرجات');
        $response->assertSee('مخرجات الوكيل');
        $response->assertSee('تصدير PDF');
    }

    public function test_pdf_export_returns_403_when_case_not_completed(): void
    {
        $case = LegalCase::factory()->create([
            'user_id' => $this->user->id,
            'status' => CaseStatus::Phase2Processing,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('cases.pdf', $case));

        $response->assertStatus(403);
    }

    public function test_pdf_export_returns_download_when_case_completed_and_has_brief(): void
    {
        $case = LegalCase::factory()->create([
            'user_id' => $this->user->id,
            'status' => CaseStatus::Phase2Completed,
        ]);
        Storage::fake('local');
        Storage::disk('local')->put("cases/{$case->id}/outputs/09_final_brief_v2.md", "# المذكرة النهائية\n\nمحتوى تجريبي بالعربية.");

        $response = $this->actingAs($this->user)
            ->get(route('cases.pdf', $case));

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_concurrent_case_limit_blocks_fourth_case(): void
    {
        LegalCase::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => CaseStatus::Phase2Processing,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('cases.store'), [
                'title' => 'Fourth Case',
                'description' => 'Test intake',
            ]);

        $response->assertRedirect(route('cases.create'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('٣', session('error'));
        $this->assertDatabaseCount('cases', 3);
    }

    public function test_stream_route_returns_sse_content_type(): void
    {
        $case = LegalCase::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('cases.stream', $case));

        $response->assertSuccessful();
        $response->assertHeader('Content-Type', 'text/event-stream');
    }

    public function test_case_insights_shown_when_completed(): void
    {
        $case = LegalCase::factory()->create([
            'user_id' => $this->user->id,
            'status' => CaseStatus::Phase2Completed,
        ]);
        $case->metrics()->create([
            'total_duration_seconds' => 120,
            'total_tokens' => 5000,
            'statutes_matched' => 3,
            'average_confidence' => 0.85,
            'corrections_count' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('cases.show', $case));

        $response->assertStatus(200);
        $response->assertSee('رؤى القضية');
        $response->assertSee('120');
    }
}
