<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mabdulmonem\AICommits\Services\GitService;
use Symfony\Component\Process\Exception\ProcessFailedException;

class GitServiceTest extends TestCase
{
    public function test_git_init_creates_repository()
    {
        $gitService = $this->app->make(GitService::class);
        $result = $gitService->initializeRepository();

        $this->assertTrue($result);
        $this->assertDirectoryExists('.git');
    }

    public function test_stage_files_success()
    {
        $gitService = $this->app->make(GitService::class);
        $result = $gitService->stageFiles(['test.txt']);

        $this->assertTrue($result);
    }

    public function test_stage_files_failure()
    {
        $gitService = $this->app->make(GitService::class);

        $this->expectException(ProcessFailedException::class);
        $gitService->stageFiles(['nonexistent.txt']);
    }

    public function test_commit_changes()
    {
        $gitService = $this->app->make(GitService::class);
        $result = $gitService->commitChanges('feat: add new feature');

        $this->assertTrue($result);
    }

    public function test_commit_changes_failure()
    {
        $gitService = $this->app->make(GitService::class);

        $this->expectException(ProcessFailedException::class);
        $gitService->commitChanges('');
    }
}
