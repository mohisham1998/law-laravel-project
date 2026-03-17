<?php

namespace Tests\Unit\Services;

use App\Services\OpenRouter\OpenRouterClient;
use App\Services\OpenRouter\OpenRouterException;
use App\Services\OpenRouter\OpenRouterService;
use GuzzleHttp\Client;
use Mockery;
use PHPUnit\Framework\TestCase;

class OpenRouterServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_complete_returns_content_and_token_usage(): void
    {
        $mockClient = Mockery::mock(OpenRouterClient::class);
        $mockClient->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Hello, world!']],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 5,
                    'total_tokens' => 15,
                ],
            ]);

        $service = new OpenRouterService($mockClient);
        $result = $service->complete('test-model', [['role' => 'user', 'content' => 'Hi']]);

        $this->assertEquals('Hello, world!', $result['content']);
        $this->assertEquals(10, $result['prompt_tokens']);
        $this->assertEquals(5, $result['completion_tokens']);
        $this->assertEquals(15, $result['total_tokens']);
    }

    public function test_complete_throws_on_client_exception(): void
    {
        $mockClient = Mockery::mock(OpenRouterClient::class);
        $mockClient->shouldReceive('chat')
            ->andThrow(OpenRouterException::fromResponse(500, ['error' => ['message' => 'Server error']]));

        $service = new OpenRouterService($mockClient);

        $this->expectException(OpenRouterException::class);
        $service->complete('test-model', []);
    }
}
