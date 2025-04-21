<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mabdulmonem\AICommits\Services\AIService;
use Mabdulmonem\AICommits\Core\Enums\AIProvider;
use Mabdulmonem\AICommits\Core\Exceptions\AIException;
use Illuminate\Support\Facades\Http;

class AIServiceTest extends TestCase
{
    public function test_generate_commit_message_success()
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'feat: add new feature']]
                ]
            ], 200),
        ]);

        $aiService = $this->app->make(AIService::class);
        $provider = AIProvider::OPENAI;

        $message = $aiService->generateCommitMessage('diff --git a/test.txt b/test.txt', $provider);

        $this->assertNotEmpty($message);
        $this->assertStringContainsString('feat:', $message);
    }

    public function test_generate_commit_message_failure()
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([], 500),
        ]);

        $aiService = $this->app->make(AIService::class);
        $provider = AIProvider::OPENAI;

        $this->expectException(AIException::class);
        $aiService->generateCommitMessage('diff --git a/test.txt b/test.txt', $provider);
    }

    public function test_unsupported_provider_throws_exception()
    {
        $this->expectException(AIException::class);

        $aiService = $this->app->make(AIService::class);
        $aiService->generateCommitMessage('diff --git a/test.txt b/test.txt', 'unsupported_provider');
    }

    public function test_connection_to_provider()
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([], 200),
        ]);

        $aiService = $this->app->make(AIService::class);
        $provider = AIProvider::OPENAI;

        $result = $aiService->testConnection($provider);

        $this->assertTrue($result);
    }
}
