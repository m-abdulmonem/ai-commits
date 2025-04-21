<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mabdulmonem\AICommits\Services\VCSService;
use Mabdulmonem\AICommits\Core\Enums\VCSProvider;
use Mabdulmonem\AICommits\Core\Exceptions\VCSException;

class VCSServiceTest extends TestCase
{
    public function test_create_repository_success()
    {
        $vcsService = $this->app->make(VCSService::class);
        $provider = VCSProvider::GITHUB;

        $result = $vcsService->createRepository('test-repo', $provider);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('url', $result);
    }

    public function test_unsupported_provider_throws_exception()
    {
        $this->expectException(VCSException::class);

        $vcsService = $this->app->make(VCSService::class);
        $vcsService->createRepository('test-repo', 'unsupported_provider');
    }
}