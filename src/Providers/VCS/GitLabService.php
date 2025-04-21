<?php

namespace Mabdulmonem\AICommits\Providers\VCS;

use Mabdulmonem\AICommits\Core\DTO\RepositoryData;
use Mabdulmonem\AICommits\Core\Enums\VCSProvider;
use Mabdulmonem\AICommits\Core\Exceptions\VCSException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitLabService
{
    private const BASE_URL = 'https://gitlab.com/api/v4';
    private const DEFAULT_PER_PAGE = 30;
    private const DEFAULT_BRANCH = 'main';

    public function __construct(
        private ?string $apiToken,
        private string $baseUrl = self::BASE_URL
    ) {
        // if (empty($this->apiToken)) {
        //     throw new \InvalidArgumentException('GitLab API token cannot be empty');
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
            'visibility' => $options['private'] ?? false ? 'private' : 'public',
            'initialize_with_readme' => $options['auto_init'] ?? true,
            'default_branch' => $options['default_branch'] ?? self::DEFAULT_BRANCH,
        ], $options);

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/projects", $payload);

            if ($response->failed()) {
                $this->handleErrorResponse($response, 'createRepository');
            }

            return RepositoryData::fromApiResponse($response->json(), 'gitlab');
        } catch (\Exception $e) {
            Log::error('GitLab repository creation failed', [
                'error' => $e->getMessage(),
                'repository' => $name
            ]);

            throw VCSException::apiRequestFailed(
                VCSProvider::GITLAB,
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
                ->get("{$this->baseUrl}/projects", [
                    'per_page' => $perPage,
                    'page' => $page,
                    'order_by' => 'last_activity_at',
                    'sort' => 'desc',
                    'membership' => true // Only projects user is a member of
                ]);

            if ($response->failed()) {
                $this->handleErrorResponse($response, 'getRepositories');
            }

            return array_map(
                fn($repo) => RepositoryData::fromApiResponse($repo, 'gitlab'),
                $response->json()
            );
        } catch (\Exception $e) {
            Log::error('GitLab repositories fetch failed', [
                'error' => $e->getMessage()
            ]);

            throw VCSException::apiRequestFailed(
                VCSProvider::GITLAB,
                $e->getCode(),
                $e->getMessage()
            );
        }
    }

    /**
     * Get repository details
     */
    public function getRepository(string $projectId): RepositoryData
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/projects/" . urlencode($projectId));

            if ($response->failed()) {
                $this->handleErrorResponse($response, 'getRepository');
            }

            return RepositoryData::fromApiResponse($response->json(), 'gitlab');
        } catch (\Exception $e) {
            Log::error('GitLab repository fetch failed', [
                'error' => $e->getMessage(),
                'project' => $projectId
            ]);

            throw VCSException::repositoryNotFound(
                VCSProvider::GITLAB,
                $projectId
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
            Log::error('GitLab user info fetch failed', [
                'error' => $e->getMessage()
            ]);

            throw VCSException::authenticationFailed(VCSProvider::GITLAB);
        }
    }

    /**
     * Create a new merge request
     */
    public function createMergeRequest(
        string $projectId,
        string $title,
        string $sourceBranch,
        string $targetBranch,
        string $description = ''
    ): array {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/projects/" . urlencode($projectId) . "/merge_requests", [
                    'title' => $title,
                    'source_branch' => $sourceBranch,
                    'target_branch' => $targetBranch,
                    'description' => $description,
                    'remove_source_branch' => true
                ]);

            if ($response->failed()) {
                $this->handleErrorResponse($response, 'createMergeRequest');
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('GitLab merge request creation failed', [
                'error' => $e->getMessage(),
                'project' => $projectId
            ]);

            throw VCSException::apiRequestFailed(
                VCSProvider::GITLAB,
                $e->getCode(),
                $e->getMessage()
            );
        }
    }

    /**
     * Handle GitLab API error responses
     */
    private function handleErrorResponse($response, string $context): void
    {
        $statusCode = $response->status();
        $errorBody = $response->json();

        if ($statusCode === 401 || $statusCode === 403) {
            throw VCSException::authenticationFailed(VCSProvider::GITLAB);
        }

        if ($statusCode === 404 && $context === 'getRepository') {
            $project = $errorBody['message'] ?? 'unknown';
            throw VCSException::repositoryNotFound(VCSProvider::GITLAB, $project);
        }

        if ($statusCode === 429) {
            $retryAfter = $response->header('Retry-After') ?? 60;
            throw VCSException::rateLimited(VCSProvider::GITLAB, (int) $retryAfter);
        }

        $errorMessage = $errorBody['message'] ?? $response->body();
        throw VCSException::apiRequestFailed(
            VCSProvider::GITLAB,
            $statusCode,
            $errorMessage
        );
    }

    /**
     * Get GitLab API headers
     */
    private function getHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->apiToken}",
        ];
    }

    /**
     * Validate GitLab API token format
     */
    public static function validateApiToken(string $token): bool
    {
        return preg_match('/^glpat-[a-zA-Z0-9_-]{20,}$/', $token) === 1;
    }

    /**
     * Get repository ID from URL
     */
    public static function extractProjectIdFromUrl(string $url): string
    {
        // Handle both HTTP and SSH URLs
        $pattern = '/^(?:https?:\/\/[^\/]+\/|git@[^:]+:)([^\/]+\/[^\/]+?)(?:\.git)?$/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return $url; // Fallback to raw input
    }
}