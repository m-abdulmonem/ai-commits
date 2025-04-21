<?php

namespace Mabdulmonem\AICommits\Core\Enums;

/**
 * Supported Version Control System providers with their configuration details
 */
enum VCSProvider: string
{
    case GITHUB = 'github';
    case GITLAB = 'gitlab';
    case BITBUCKET = 'bitbucket';
    case GITEA = 'gitea';
    case AZURE_DEVOPS = 'azure';

    /**
     * Get the display name of the provider
     */
    public function displayName(): string
    {
        return match ($this) {
            self::GITHUB => 'GitHub',
            self::GITLAB => 'GitLab',
            self::BITBUCKET => 'Bitbucket',
            self::GITEA => 'Gitea',
            self::AZURE_DEVOPS => 'Azure DevOps'
        };
    }

    /**
     * Get the API base URL for this provider
     */
    public function apiBaseUrl(): string
    {
        return match ($this) {
            self::GITHUB => 'https://api.github.com',
            self::GITLAB => 'https://gitlab.com/api/v4',
            self::BITBUCKET => 'https://api.bitbucket.org/2.0',
            self::GITEA => 'https://gitea.example.com/api/v1', // Default, should be configured
            self::AZURE_DEVOPS => 'https://dev.azure.com/{organization}/_apis/git'
        };
    }

    /**
     * Get the repository URL pattern for web access
     */
    public function repoUrlPattern(): string
    {
        return match ($this) {
            self::GITHUB => 'https://github.com/{owner}/{repo}',
            self::GITLAB => 'https://gitlab.com/{owner}/{repo}',
            self::BITBUCKET => 'https://bitbucket.org/{owner}/{repo}',
            self::GITEA => 'https://gitea.example.com/{owner}/{repo}', // Default, should be configured
            self::AZURE_DEVOPS => 'https://dev.azure.com/{organization}/{project}/_git/{repo}'
        };
    }

    /**
     * Get the required authentication token type
     */
    public function tokenType(): string
    {
        return match ($this) {
            self::GITHUB => 'Bearer',
            self::GITLAB => 'Bearer',
            self::BITBUCKET => 'Basic', // Bitbucket uses app passwords
            self::GITEA => 'Bearer',
            self::AZURE_DEVOPS => 'Basic'
        };
    }

    /**
     * Get all available providers as choice array
     * 
     * @return array<string,string> [value => display name]
     */
    public static function choices(): array
    {
        return array_reduce(
            self::cases(),
            fn(array $choices, self $provider) => $choices + [$provider->value => $provider->displayName()],
            []
        );
    }

    /**
     * Get the default provider
     */
    public static function default(): self
    {
        return self::GITHUB;
    }

    /**
     * Check if this provider supports SSH keys
     */
    public function supportsSSH(): bool
    {
        return match ($this) {
            self::AZURE_DEVOPS => false,
            default => true
        };
    }

    /**
     * Get the default branch name for new repositories
     */
    public function defaultBranchName(): string
    {
        return match ($this) {
            self::GITHUB, self::GITLAB, self::GITEA => 'main',
            self::BITBUCKET, self::AZURE_DEVOPS => 'master'
        };
    }

    /**
     * Get the API header for the authentication token
     */
    public function tokenHeader(): string
    {
        return match ($this) {
            self::GITHUB, self::GITLAB, self::GITEA => 'Authorization',
            self::BITBUCKET, self::AZURE_DEVOPS => 'Authorization'
        };
    }

    /**
     * Parse from string with fallback to default
     */
    public static function tryFromString(?string $value): self
    {
        if ($value === null) {
            return self::default();
        }

        return self::tryFrom(strtolower($value)) ?? self::default();
    }
}