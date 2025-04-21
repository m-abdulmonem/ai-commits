<?php

namespace Mabdulmonem\AICommits\Core\Contracts;

use Mabdulmonem\AICommits\Core\Exceptions\GitException;
use Mabdulmonem\AICommits\Core\DTO\DiffHunk;

interface GitInterface
{
    /**
     * Check if current directory is a Git repository
     *
     * @return bool
     */
    public function isRepository(): bool;

    /**
     * Initialize a new Git repository
     *
     * @return void
     * @throws GitException
     */
    public function init(): void;

    /**
     * Get all unstaged diff hunks
     *
     * @return array<DiffHunk>
     * @throws GitException
     */
    public function getDiffHunks(): array;

    /**
     * Get all untracked files
     *
     * @return array<string>
     * @throws GitException
     */
    public function getUntrackedFiles(): array;

    /**
     * Stage a specific diff hunk
     *
     * @param DiffHunk $hunk
     * @return void
     * @throws GitException
     */
    public function stageHunk(DiffHunk $hunk): void;

    /**
     * Stage all changes
     *
     * @return void
     * @throws GitException
     */
    public function stageAll(): void;

    /**
     * Create a new commit
     *
     * @param string $message
     * @return void
     * @throws GitException
     */
    public function commit(string $message): void;

    /**
     * Push changes to remote
     *
     * @param string $branch
     * @param bool $setUpstream
     * @return void
     * @throws GitException
     */
    public function push(string $branch, bool $setUpstream = false): void;

    /**
     * Add a new remote
     *
     * @param string $name
     * @param string $url
     * @return void
     * @throws GitException
     */
    public function addRemote(string $name, string $url): void;

    /**
     * Get current branch name
     *
     * @return string
     * @throws GitException
     */
    public function getCurrentBranch(): string;

    /**
     * Check if branch has upstream configured
     *
     * @param string $branch
     * @return bool
     * @throws GitException
     */
    public function hasUpstream(string $branch): bool;

    /**
     * Get list of remotes
     *
     * @return array<string, string> [name => url]
     * @throws GitException
     */
    public function getRemotes(): array;

    /**
     * Get commit history
     *
     * @param int $limit
     * @return array<array{hash: string, message: string, date: string}>
     * @throws GitException
     */
    public function getCommitHistory(int $limit = 10): array;
}