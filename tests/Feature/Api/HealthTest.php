<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
                'services' => [
                    'database' => 'up',
                    'redis' => 'up',
                ],
            ])
            ->assertJsonStructure(['timestamp']);
    }
}
