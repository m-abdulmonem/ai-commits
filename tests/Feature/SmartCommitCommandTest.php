<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;

class SmartCommitCommandTest extends TestCase
{
    public function test_ai_commit_command_runs_successfully()
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'feat: add new feature']]
                ]
            ], 200),
        ]);

        $this->artisan('ai-commit')
            ->expectsOutput('🔍 Reading unstaged changes...')
            ->assertExitCode(0);
    }

    public function test_ai_commit_with_push_option()
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'feat: add new feature']]
                ]
            ], 200),
        ]);

        $this->artisan('ai-commit --push')
            ->expectsOutput('🚀 Pushing changes...')
            ->assertExitCode(0);
    }

    public function test_ai_commit_dry_run()
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'feat: add new feature']]
                ]
            ], 200),
        ]);

        $this->artisan('ai-commit --dry-run')
            ->expectsOutput('💬 Commit message:')
            ->assertExitCode(0);
    }

    public function test_ai_commit_handles_api_failure()
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([], 500),
        ]);

        $this->artisan('ai-commit')
            ->expectsOutput('❌ Failed to get response from OpenRouter')
            ->assertExitCode(1);
    }
}
