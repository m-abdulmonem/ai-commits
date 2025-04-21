<?php

namespace Mabdulmonem\AICommits\Providers\VCS;

use Mabdulmonem\AICommits\Core\DTO\RepositoryData;
use Mabdulmonem\AICommits\Core\Enums\VCSProvider;
use Mabdulmonem\AICommits\Core\Exceptions\VCSException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BitbucketService
{
    private const BASE_URL = 'https://api.bitbucket.org/2.0';
    private const DEFAULT_PER_PAGE = 30;
    private const DEFAULT_BRANCH = 'main';

    public function __construct(
        private ?string $username,
        private ?string $appPassword,
        private string $baseUrl = self::BASE_URL
    ) {
        // if (empty($this->username)) {
        //     throw new \InvalidArgumentException('Bitbucket username cannot be empty');
        // }
        // if (empty($this->appPassword)) {
        //     throw new \InvalidArgumentException('Bitbucket app password cannot be empty');
        // }
    }

    /**
     * Create a new repository
     */
    public function createRepository(string $name, array $options = []): RepositoryData
    {
        $workspace = $options['workspace'] ?? $this->username;
        $payload = array_merge([
            'name' => $name,
            'scm' => 'git',
            'is_private' => $options['private'] ?? true,
            'description' => $options['description'] ?? '',
            'fork_policy' => $options['fork_policy'] ?? 'allow_forks',
        ], $options);

        try {
            $response = Http::withBasicAuth($this->username, $this->appPassword)
                ->withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/repositories/{$workspace}", $payload);

            if ($response->failed()) {
                $this->handleErrorResponse($response, 'createRepository');
            }

            return RepositoryData::fromApiResponse($response->json(), 'bitbucket');
        } catch (\Exception $e) {
            Log::error('Bitbucket repository creation failed', [
                'error' => $e->getMessage(),
                'repository' => $name
            ]);

            throw VCSException::apiRequestFailed(
                VCSProvider::BITBUCKET,
                $e->getCode(),
                $e->getMessage()
            );
        }
    }

    /**
     * Get list of repositories
     */
    public function getRepositories(int $perPage = self::DEFAULT_PER_PAGE, int $page = 1): array
    {
        try {
            $response = Http::withBasicAuth($this->username, $this->appPassword)
                ->withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/repositories/{$this->username}", [
                    'pagelen' => $perPage,
                    'page' => $page,
                    'sort' => '-updated_on'
                ]);

            if ($response->failed()) {
                $this->handleErrorResponse($response, 'getRepositories');
            }

            return array_map(
                fn($repo) => RepositoryData::fromApiResponse($repo, 'bitbucket'),
                $response->json()['values'] ?? []
            );
        } catch (\Exception $e) {
            Log::error('Bitbucket repositories fetch failed', [
                'error' => $e->getMessage()
            ]);

            throw VCSException::apiRequestFailed(
                VCSProvider::BITBUCKET,
                $e->getCode(),
                $e->getMessage()
            );
        }
    }

    /**
     * Get repository details
     */
    public function getRepository(string $workspace, string $repoSlug): RepositoryData
    {
        try {
            $response = Http::withBasicAuth($this->username, $this->appPassword)
                ->withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/repositories/{$workspace}/{$repoSlug}");

            if ($response->failed()) {
                $this->handleErrorResponse($response, 'getRepository');
            }

            return RepositoryData::fromApiResponse($response->json(), 'bitbucket');
        } catch (\Exception $e) {
            Log::error('Bitbucket repository fetch failed', [
                'error' => $e->getMessage(),
                'repository' => "{$workspace}/{$repoSlug}"
            ]);

            throw VCSException::repositoryNotFound(
                VCSProvider::BITBUCKET,
                "{$workspace}/{$repoSlug}"
            );
        }
    }

    /**
     * Get authenticated user information
     */
    public function getUserInfo(): array
    {
        try {
            $response = Http::withBasicAuth($this->username, $this->appPassword)
                ->withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/user");

            if ($response->failed()) {
                $this->handleErrorResponse($response, 'getUserInfo');
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Bitbucket user info fetch failed', [
                'error' => $e->getMessage()
            ]);

            throw VCSException::authenticationFailed(VCSProvider::BITBUCKET);
        }
    }

    /**
     * Create a new pull request
     */
    public function createPullRequest(
        string $workspace,
        string $repoSlug,
        string $title,
        string $sourceBranch,
        string $targetBranch,
        string $description = ''
    ): array {
        try {
            $response = Http::withBasicAuth($this->username, $this->appPassword)
                ->withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/repositories/{$workspace}/{$repoSlug}/pullrequests", [
                    'title' => $title,
                    'source' => ['branch' => ['name' => $sourceBranch]],
                    'destination' => ['branch' => ['name' => $targetBranch]],
                    'description' => $description,
                    'close_source_branch' => true
                ]);

            if ($response->failed()) {
                $this->handleErrorResponse($response, 'createPullRequest');
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Bitbucket pull request creation failed', [
                'error' => $e->getMessage(),
                'repository' => "{$workspace}/{$repoSlug}"
            ]);

            throw VCSException::apiRequestFailed(
                VCSProvider::BITBUCKET,
                $e->getCode(),
                $e->getMessage()
            );
        }
    }

    /**
     * Handle Bitbucket API error responses
     */
    private function handleErrorResponse($response, string $context): void
    {
        $statusCode = $response->status();
        $errorBody = $response->json();

        if ($statusCode === 401 || $statusCode === 403) {
            throw VCSException::authenticationFailed(VCSProvider::BITBUCKET);
        }

        if ($statusCode === 404 && $context === 'getRepository') {
            $repo = $errorBody['error']['fields']['repository'] ?? 'unknown';
            throw VCSException::repositoryNotFound(VCSProvider::BITBUCKET, $repo);
        }

        if ($statusCode === 429) {
            $retryAfter = $response->header('Retry-After') ?? 60;
            throw VCSException::rateLimited(VCSProvider::BITBUCKET, (int)$retryAfter);
        }

        $errorMessage = $errorBody['error']['message'] ?? $response->body();
        throw VCSException::apiRequestFailed(
            VCSProvider::BITBUCKET,
            $statusCode,
            $errorMessage
        );
    }

    /**
     * Get Bitbucket API headers
     */
    private function getHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Validate Bitbucket credentials format
     */
    public static function validateCredentials(string $username, string $appPassword): bool
    {
        return !empty($username) && preg_match('/^[a-zA-Z0-9_-]+$/', $appPassword);
    }

    /**
     * Extract workspace and repo slug from Bitbucket URL
     */
    public static function parseRepositoryUrl(string $url): array
    {
        // Handle both HTTP and SSH URLs
        $pattern = '/^(?:https?:\/\/[^\/]+\/|git@[^:]+:)([^\/]+)\/([^\/]+?)(?:\.git)?$/';
        if (preg_match($pattern, $url, $matches)) {
            return [
                'workspace' => $matches[1],
                'repo_slug' => $matches[2]
            ];
        }

        throw new \InvalidArgumentException("Invalid Bitbucket repository URL: {$url}");
    }
}