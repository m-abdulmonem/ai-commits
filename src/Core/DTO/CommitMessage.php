<?php

namespace Mabdulmonem\AICommits\Core\DTO;

use Mabdulmonem\AICommits\Core\Enums\CommitType;

final class CommitMessage
{
    /**
     * @param CommitType $type The commit type (feat, fix, etc.)
     * @param string $description The commit description
     * @param string|null $scope Optional scope of the change
     * @param bool $breaking Whether this is a breaking change
     * @param string|null $body Optional detailed message body
     * @param string|null $footer Optional footer information
     */
    public function __construct(
        public readonly CommitType $type,
        public readonly string $description,
        public readonly ?string $scope = null,
        public readonly bool $breaking = false,
        public readonly ?string $body = null,
        public readonly ?string $footer = null
    ) {
        $this->validate();
    }

    /**
     * Create from raw commit message string
     *
     * @throws \Exception
     */
    public static function fromString(string $message): self
    {
        $pattern = '/^(?<type>\w+)(?:\((?<scope>[^)]+)\))?(?<breaking>!)?:\s(?<description>.+)$/';

        if (!preg_match($pattern, $message, $matches)) {
            throw new \Exception("Invalid commit message format");
        }

        return new self(
            type: CommitType::tryFrom($matches['type']) ?? CommitType::CHORE,
            description: trim($matches['description']),
            scope: $matches['scope'] ?? null,
            breaking: isset($matches['breaking'])
        );
    }

    /**
     * Convert to conventional commit format string
     */
    public function toString(): string
    {
        $scope = $this->scope ? "({$this->scope})" : '';
        $breaking = $this->breaking ? '!' : '';

        $message = "{$this->type->value}{$scope}{$breaking}: {$this->description}";

        if ($this->body) {
            $message .= "\n\n{$this->body}";
        }

        if ($this->footer) {
            $message .= "\n\n{$this->footer}";
        }

        return $message;
    }

    /**
     * Update the commit description
     */
    public function withDescription(string $description): self
    {
        return new self(
            $this->type,
            $description,
            $this->scope,
            $this->breaking,
            $this->body,
            $this->footer
        );
    }

    /**
     * Mark as breaking change
     */
    public function withBreakingChange(bool $breaking = true): self
    {
        return new self(
            $this->type,
            $this->description,
            $this->scope,
            $breaking,
            $this->body,
            $this->footer
        );
    }

    /**
     * Validate the commit message components
     *
     * @throws \Exception
     */
    private function validate(): void
    {
        if (empty($this->description)) {
            throw new \Exception("Commit description cannot be empty");
        }

        if ($this->scope && !preg_match('/^[a-z0-9-]+$/', $this->scope)) {
            throw new \Exception("Scope can only contain lowercase letters, numbers and hyphens");
        }
    }
}
