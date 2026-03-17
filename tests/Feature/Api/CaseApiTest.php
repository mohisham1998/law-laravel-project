<?php

namespace Tests\Feature\Api;

use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CaseApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Queue::fake();
    }

    protected function actingAsSanctum(): self
    {
        $token = $this->user->createToken('test')->plainTextToken;
        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function test_authenticated_user_can_list_cases(): void
    {
        LegalCase::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->actingAsSanctum()
            ->getJson('/api/v1/cases');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_authenticated_user_can_create_case(): void
    {
        $response = $this->actingAsSanctum()
            ->postJson('/api/v1/cases', [
                'title' => 'Test Case',
                'intake_text' => 'This is the case intake text for testing.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Test Case')
            ->assertJsonPath('data.status', 'phase1_pending');

        $this->assertDatabaseHas('cases', [
            'title' => 'Test Case',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_authenticated_user_can_show_case(): void
    {
        $case = LegalCase::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAsSanctum()
            ->getJson("/api/v1/cases/{$case->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $case->id)
            ->assertJsonPath('data.title', $case->title);
    }

    public function test_user_cannot_show_another_users_case(): void
    {
        $otherUser = User::factory()->create();
        $case = LegalCase::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAsSanctum()
            ->getJson("/api/v1/cases/{$case->id}");

        $response->assertStatus(404);
    }
}
