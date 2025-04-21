<?php

namespace Mabdulmonem\AICommits\Core\Contracts;

use Mabdulmonem\AICommits\Core\Enums\VCSProvider;
use Mabdulmonem\AICommits\Core\Exceptions\VCSException;
use Mabdulmonem\AICommits\Core\DTO\RepositoryData;

interface VCSInterface
{
    /**
     * Create a new repository
     *
     * @param VCSProvider $provider
     * @param string $name
     * @param array $options Additional repository options
     * @return RepositoryData
     * @throws VCSException
     */
    public function createRepository(
        VCSProvider $provider,
        string $name,
        array $options = []
    ): RepositoryData;

    /**
     * Get list of available repositories
     *
     * @param VCSProvider $provider
     * @param int $perPage Number of items per page
     * @param int $page Page number
     * @return array<RepositoryData>
     * @throws VCSException
     */
    public function getRepositories(
        VCSProvider $provider,
        int $perPage = 30,
        int $page = 1
    ): array;

    /**
     * Get repository details
     *
     * @param VCSProvider $provider
     * @param string $identifier Repository ID or full name
     * @return RepositoryData
     * @throws VCSException
     */
    public function getRepository(
        VCSProvider $provider,
        string $identifier
    ): RepositoryData;

    /**
     * Get authenticated user's information
     *
     * @param VCSProvider $provider
     * @return array
     * @throws VCSException
     */
    public function getUserInfo(VCSProvider $provider): array;

    /**
     * Check if repository exists
     *
     * @param VCSProvider $provider
     * @param string $identifier Repository ID or full name
     * @return bool
     * @throws VCSException
     */
    public function repositoryExists(
        VCSProvider $provider,
        string $identifier
    ): bool;

    /**
     * Get default branch name for repository
     *
     * @param VCSProvider $provider
     * @param string $identifier Repository ID or full name
     * @return string
     * @throws VCSException
     */
    public function getDefaultBranch(
        VCSProvider $provider,
        string $identifier
    ): string;

    /**
     * Create a pull/merge request
     *
     * @param VCSProvider $provider
     * @param string $repositoryId
     * @param array $requestData
     * @return array
     * @throws VCSException
     */
    public function createPullRequest(
        VCSProvider $provider,
        string $repositoryId,
        array $requestData
    ): array;

    /**
     * Get API rate limit information
     *
     * @param VCSProvider $provider
     * @return array
     * @throws VCSException
     */
    public function getRateLimit(VCSProvider $provider): array;

    /**
     * Test API connection
     *
     * @param VCSProvider $provider
     * @return bool
     * @throws VCSException
     */
    public function testConnection(VCSProvider $provider): bool;
}