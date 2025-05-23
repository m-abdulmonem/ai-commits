<?php

namespace Mabdulmonem\AICommits\Providers\VCS;

use Mabdulmonem\AICommits\Core\DTO\RepositoryData;
use Mabdulmonem\AICommits\Core\Enums\VCSProvider;
use Mabdulmonem\AICommits\Core\Exceptions\VCSException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubService
{
    private const BASE_URL = 'https://api.github.com';
    private const DEFAULT_PER_PAGE = 30;

    public function __construct(
        private ?string $apiToken,
        private string $baseUrl = self::BASE_URL
    ) {
        // if (empty($this->apiToken)) {
        //     throw new \InvalidArgumentException('GitHub API token cannot be empty');
        // }
    }

    /**
     * Create a new repository
     */
 public function createRepository(string $name, array $options = []): RepositoryData
{
    $payload = array_merge([
        'name' => $name,
        'description' => $options['description'] ?? '',
        'private' => $options['private'] ?? true,
        'auto_init' => $options['auto_init'] ?? true,
    ], $options);

    $endpoint = isset($options['organization'])
        ? "{$this->baseUrl}/orgs/{$options['organization']}/repos"
        : "{$this->baseUrl}/user/repos";

    try {
        $response = Http::withHeaders($this->getHeaders())
            ->post($endpoint, $payload);

        // Log response for debugging
        Log::debug('GitHub API response', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->failed()) {
            $this->handleErrorResponse($response, 'createRepository');
        }

        return RepositoryData::fromApiResponse($response->json(), 'github');
    } catch (\Exception $e) {
        Log::error('GitHub repository creation failed', [
            'error' => $e->getMessage(),
            'repository' => $name
        ]);

        throw VCSException::apiRequestFailed(
            VCSProvider::GITHUB,
            $e->getCode(),
            $e->getMessage()
        );
    }
}

    public function listOrganizations(): array
{
    try {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/user/orgs");

        if ($response->failed()) {
            $this->handleErrorResponse($response, 'listOrganizations');
        }

        return $response->json(); // returns array of orgs
    } catch (\Exception $e) {
        Log::error('Failed to fetch GitHub organizations', [
            'error' => $e->getMessage(),
        ]);

        throw VCSException::apiRequestFailed(
            VCSProvider::GITHUB,
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
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/user/repos", [
                    'per_page' => $perPage,
                    'page' => $page,
                    'sort' => 'updated',
                    'direction' => 'desc'
                ]);

            if ($response->failed()) {
                $this->handleErrorResponse($response, 'getRepositories');
            }

            return array_map(
                fn($repo) => RepositoryData::fromApiResponse($repo, 'github'),
                $response->json()
            );
        } catch (\Exception $e) {
            Log::error('GitHub repositories fetch failed', [
                'error' => $e->getMessage()
            ]);

            throw VCSException::apiRequestFailed(
                VCSProvider::GITHUB,
                $e->getCode(),
                $e->getMessage()
            );
        }
    }

    /**
     * Get repository details
     */
    public function getRepository(string $owner, string $repo): RepositoryData
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/repos/{$owner}/{$repo}");

            if ($response->failed()) {
                $this->handleErrorResponse($response, 'getRepository');
            }

            return RepositoryData::fromApiResponse($response->json(), 'github');
        } catch (\Exception $e) {
            Log::error('GitHub repository fetch failed', [
                'error' => $e->getMessage(),
                'repository' => "{$owner}/{$repo}"
            ]);

            throw VCSException::repositoryNotFound(
                VCSProvider::GITHUB,
                "{$owner}/{$repo}"
            );
        }
    }

    /**
     * Get authenticated user information
     */
    public function getUserInfo(): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/user");

            if ($response->failed()) {
                $this->handleErrorResponse($response, 'getUserInfo');
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('GitHub user info fetch failed', [
                'error' => $e->getMessage()
            ]);

            throw VCSException::authenticationFailed(VCSProvider::GITHUB);
        }
    }

    /**
     * Create a new pull request
     */
    public function createPullRequest(
        string $owner,
        string $repo,
        string $title,
        string $head,
        string $base,
        string $body = '',
        bool $draft = false
    ): array {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/repos/{$owner}/{$repo}/pulls", [
                    'title' => $title,
                    'head' => $head,
                    'base' => $base,
                    'body' => $body,
                    'draft' => $draft
                ]);

            if ($response->failed()) {
                $this->handleErrorResponse($response, 'createPullRequest');
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('GitHub pull request creation failed', [
                'error' => $e->getMessage(),
                'repository' => "{$owner}/{$repo}"
            ]);

            throw VCSException::apiRequestFailed(
                VCSProvider::GITHUB,
                $e->getCode(),
                $e->getMessage()
            );
        }
    }

    /**
     * Handle GitHub API error responses
     */
    private function handleErrorResponse($response, string $context): void
    {
        $statusCode = $response->status();
        $errorBody = $response->json();

        if ($statusCode === 401 || $statusCode === 403) {
            throw VCSException::authenticationFailed(VCSProvider::GITHUB);
        }

        if ($statusCode === 404 && $context === 'getRepository') {
            $repo = $errorBody['documentation_url'] ?? 'unknown';
            throw VCSException::repositoryNotFound(VCSProvider::GITHUB, $repo);
        }

        if ($statusCode === 429) {
            $retryAfter = $response->header('Retry-After') ?? 60;
            throw VCSException::rateLimited(VCSProvider::GITHUB, (int) $retryAfter);
        }

        $errorMessage = $errorBody['message'] ?? $response->body();
        throw VCSException::apiRequestFailed(
            VCSProvider::GITHUB,
            $statusCode,
            $errorMessage
        );
    }

    /**
     * Get GitHub API headers
     */
    private function getHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.github+json',
            'Authorization' => "Bearer {$this->apiToken}",
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
    }

    /**
     * Validate GitHub API token format
     */
    public static function validateApiToken(string $token): bool
    {
        return preg_match('/^(ghp|github_pat)_[a-zA-Z0-9_]+$/', $token) === 1;
    }
}
