<?php

namespace Mabdulmonem\AICommits\Core\DTO;

final class RepositoryData
{
    /**
     * @param string $id Unique repository identifier
     * @param string $name Repository name
     * @param string $fullName Repository full name (owner/repo)
     * @param string $url Web URL of the repository
     * @param string $sshUrl SSH clone URL
     * @param string $cloneUrl HTTPS clone URL
     * @param bool $private Whether repository is private
     * @param string $defaultBranch Default branch name
     * @param array $owner Owner information [id, name, type, url]
     * @param string|null $description Repository description (optional)
     * @param string|null $createdAt Creation timestamp (optional)
     * @param string|null $updatedAt Last update timestamp (optional)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $fullName,
        public readonly string $url,
        public readonly string $sshUrl,
        public readonly string $cloneUrl,
        public readonly bool $private,
        public readonly string $defaultBranch,
        public readonly array $owner,
        public readonly ?string $description = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
    ) {
        $this->validate();
    }

    /**
     * Create from API response array
     *
     * @param array $data API response data
     * @param VCSProvider $provider The VCS provider
     * @return self
     */
    public static function fromApiResponse(array $data, string $provider): self
    {
        return match (strtolower($provider->value)) {
            'github' => new self(
                id: (string) $data['id'],
                name: $data['name'],
                fullName: $data['full_name'],
                url: $data['html_url'],
                sshUrl: $data['ssh_url'],
                cloneUrl: $data['clone_url'],
                private: $data['private'],
                defaultBranch: $data['default_branch'],
                owner: [
                    'id' => (string) $data['owner']['id'],
                    'name' => $data['owner']['login'],
                    'type' => $data['owner']['type'],
                    'url' => $data['owner']['html_url']
                ],
                description: $data['description'] ?? null,
                createdAt: $data['created_at'] ?? null,
                updatedAt: $data['updated_at'] ?? null
            ),
            'gitlab' => new self(
                id: (string) $data['id'],
                name: $data['name'],
                fullName: $data['path_with_namespace'],
                url: $data['web_url'],
                sshUrl: $data['ssh_url_to_repo'],
                cloneUrl: $data['http_url_to_repo'],
                private: $data['visibility'] !== 'public',
                defaultBranch: $data['default_branch'],
                owner: [
                    'id' => (string) $data['owner']['id'],
                    'name' => $data['owner']['username'],
                    'type' => $data['owner']['kind'],
                    'url' => $data['owner']['web_url']
                ],
                description: $data['description'] ?? null,
                createdAt: $data['created_at'] ?? null,
                updatedAt: $data['last_activity_at'] ?? null
            ),
            'bitbucket' => new self(
                id: $data['uuid'],
                name: $data['name'],
                fullName: $data['full_name'],
                url: $data['links']['html']['href'],
                sshUrl: $data['links']['clone'][0]['href'], // SSH is usually first
                cloneUrl: $data['links']['clone'][1]['href'], // HTTPS is usually second
                private: $data['is_private'],
                defaultBranch: $data['mainbranch']['name'] ?? 'master',
                owner: [
                    'id' => $data['owner']['uuid'],
                    'name' => $data['owner']['username'],
                    'type' => $data['owner']['type'],
                    'url' => $data['owner']['links']['html']['href']
                ],
                description: $data['description'] ?? null,
                createdAt: $data['created_on'] ?? null,
                updatedAt: $data['updated_on'] ?? null
            ),
            default => throw new \InvalidArgumentException("Unsupported provider: $provider->value")
        };
    }

    /**
     * Get repository owner identifier
     */
    public function getOwnerId(): string
    {
        return $this->owner['id'];
    }

    /**
     * Get repository owner name
     */
    public function getOwnerName(): string
    {
        return $this->owner['name'];
    }

    /**
     * Get repository owner type (user/organization)
     */
    public function getOwnerType(): string
    {
        return $this->owner['type'];
    }

    /**
     * Get preferred clone URL based on authentication method
     */
    public function getPreferredCloneUrl(bool $useSsh): string
    {
        return $useSsh ? $this->sshUrl : $this->cloneUrl;
    }

    /**
     * Validate the repository data
     */
    private function validate(): void
    {
        $requiredOwnerFields = ['id', 'name', 'type', 'url'];
        foreach ($requiredOwnerFields as $field) {
            if (!isset($this->owner[$field])) {
                throw new \InvalidArgumentException("Missing owner field: $field");
            }
        }

        if (empty($this->defaultBranch)) {
            throw new \InvalidArgumentException("Default branch cannot be empty");
        }
    }
}