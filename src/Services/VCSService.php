<?php

namespace Mabdulmonem\AICommits\Services;

use Mabdulmonem\AICommits\Core\Contracts\VCSInterface;
use Mabdulmonem\AICommits\Core\DTO\RepositoryData;
use Mabdulmonem\AICommits\Core\Enums\VCSProvider;
use Mabdulmonem\AICommits\Core\Exceptions\VCSException;
use Mabdulmonem\AICommits\Providers\VCS\GitHubService;
use Mabdulmonem\AICommits\Providers\VCS\GitLabService;
use Mabdulmonem\AICommits\Providers\VCS\BitbucketService;

class VCSService implements VCSInterface
{
    /** @var array<string,GitHubService|GitLabService|BitbucketService> */
    private array $providers = [];

    public function __construct(
        GitHubService $github,
        GitLabService $gitlab,
        BitbucketService $bitbucket
    ) {
        $this->providers = [
            VCSProvider::GITHUB->value => $github,
            VCSProvider::GITLAB->value => $gitlab,
            VCSProvider::BITBUCKET->value => $bitbucket,
        ];
    }

    /**
     * Create a new repository
     */
    public function createRepository(
        VCSProvider $provider,
        string $name,
        array $options = []
    ): RepositoryData {
        if (!isset($this->providers[$provider->value])) {
            throw VCSException::unsupportedProvider($provider);
        }

        try {
            return $this->providers[$provider->value]->createRepository($name, $options);
        } catch (\Exception $e) {
            throw new VCSException(
                $provider,
                "Failed to create repository: {$e->getMessage()}",
                $e->getCode() ?: 500,
                $e
            );
        }
    }

    /**
     * Get list of repositories
     */
    public function getRepositories(
        VCSProvider $provider,
        int $perPage = 30,
        int $page = 1
    ): array {
        if (!isset($this->providers[$provider->value])) {
            throw VCSException::unsupportedProvider($provider);
        }

        try {
            return $this->providers[$provider->value]->getRepositories($perPage, $page);
        } catch (\Exception $e) {
            throw new VCSException(
                $provider,
                "Failed to fetch repositories: {$e->getMessage()}",
                $e->getCode() ?: 500,
                $e
            );
        }
    }

    /**
     * Get repository details
     */
    public function getRepository(
        VCSProvider $provider,
        string $identifier
    ): RepositoryData {
        if (!isset($this->providers[$provider->value])) {
            throw VCSException::unsupportedProvider($provider);
        }

        try {
            return $this->providers[$provider->value]->getRepository($identifier);
        } catch (\Exception $e) {
            throw VCSException::repositoryNotFound(
                $provider,
                $identifier,
                $e
            );
        }
    }

    /**
     * Get authenticated user information
     */
    public function getUserInfo(VCSProvider $provider): array
    {
        if (!isset($this->providers[$provider->value])) {
            throw VCSException::unsupportedProvider($provider);
        }

        try {
            return $this->providers[$provider->value]->getUserInfo();
        } catch (\Exception $e) {
            throw VCSException::authenticationFailed(
                $provider,
                $e
            );
        }
    }

    /**
     * Create a new pull/merge request
     */
    public function createPullRequest(
        VCSProvider $provider,
        string $repositoryId,
        array $requestData
    ): array {
        if (!isset($this->providers[$provider->value])) {
            throw VCSException::unsupportedProvider($provider);
        }

        try {
            return $this->providers[$provider->value]->createPullRequest(
                $repositoryId,
                $requestData
            );
        } catch (\Exception $e) {
            throw new VCSException(
                $provider,
                "Failed to create pull request: {$e->getMessage()}",
                $e->getCode() ?: 500,
                $e
            );
        }
    }

    /**
     * Check if repository exists
     */
    public function repositoryExists(VCSProvider $provider, string $identifier): bool
    {
        if (!isset($this->providers[$provider->value])) {
            throw VCSException::unsupportedProvider($provider);
        }

        try {
            $this->providers[$provider->value]->getRepository($identifier);
            return true;
        } catch (VCSException $e) {
            if ($e->isNotFoundError()) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Get default branch name for repository
     */
    public function getDefaultBranch(VCSProvider $provider, string $identifier): string
    {
        if (!isset($this->providers[$provider->value])) {
            throw VCSException::unsupportedProvider($provider);
        }

        try {
            $repo = $this->providers[$provider->value]->getRepository($identifier);
            return $repo->defaultBranch;
        } catch (\Exception $e) {
            throw new VCSException(
                $provider,
                "Failed to get default branch: {$e->getMessage()}",
                $e->getCode() ?: 500,
                $e
            );
        }
    }

    /**
     * Get API rate limit information
     */
    public function getRateLimit(VCSProvider $provider): array
    {
        if (!isset($this->providers[$provider->value])) {
            throw VCSException::unsupportedProvider($provider);
        }

        try {
            // GitHub specific implementation
            if ($provider === VCSProvider::GITHUB) {
                $response = $this->providers[$provider->value]->getRateLimit();
                return [
                    'limit' => $response['resources']['core']['limit'],
                    'remaining' => $response['resources']['core']['remaining'],
                    'reset' => $response['resources']['core']['reset'],
                ];
            }

            // GitLab specific implementation
            if ($provider === VCSProvider::GITLAB) {
                $response = $this->providers[$provider->value]->getRateLimit();
                return [
                    'limit' => $response['rate_limit'],
                    'remaining' => $response['remaining'],
                    'reset' => $response['reset_time'],
                ];
            }

            // Bitbucket doesn't provide rate limit info in their API
            return [
                'limit' => null,
                'remaining' => null,
                'reset' => null,
            ];

        } catch (\Exception $e) {
            throw new VCSException(
                $provider,
                "Failed to get rate limit: {$e->getMessage()}",
                $e->getCode() ?: 500,
                $e
            );
        }
    }

    /**
     * Get available VCS providers
     */
    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Check if provider is supported
     */
    public function isProviderSupported(VCSProvider $provider): bool
    {
        return isset($this->providers[$provider->value]);
    }

    /**
     * Test connection to VCS provider
     */
    public function testConnection(VCSProvider $provider): bool
    {
        if (!isset($this->providers[$provider->value])) {
            throw VCSException::unsupportedProvider($provider);
        }

        try {
            $this->providers[$provider->value]->getUserInfo();
            return true;
        } catch (\Exception $e) {
            throw new VCSException(
                $provider,
                "Connection test failed: {$e->getMessage()}",
                $e->getCode() ?: 500,
                $e
            );
        }
    }
}