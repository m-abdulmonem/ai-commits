<?php

namespace Mabdulmonem\AICommits\Core\Exceptions;

use Mabdulmonem\AICommits\Core\Enums\GitOperation;
use Throwable;

/**
 * Exception thrown when Git operations fail
 */
class GitException extends \RuntimeException
{
    /**
     * @param GitOperation $operation The Git operation that failed
     * @param string $message The exception message
     * @param int $code The exception code
     * @param Throwable|null $previous The previous exception
     * @param string|null $output The command output (if available)
     */
    public function __construct(
        private readonly GitOperation $operation,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        private readonly ?string $output = null
    ) {
        parent::__construct(
            "[git {$operation->value}] {$message}",
            $code,
            $previous
        );
    }

    /**
     * Create an exception for command execution failure
     */
    public static function commandFailed(
        GitOperation $operation,
        string $command,
        int $exitCode,
        string $output = '',
        ?Throwable $previous = null
    ): self {
        return new self(
            $operation,
            "Command failed with exit code {$exitCode}: {$command}",
            $exitCode,
            $previous,
            $output
        );
    }

    /**
     * Create an exception for repository not found
     */
    public static function notARepository(
        string $path = '',
        ?Throwable $previous = null
    ): self {
        return new self(
            GitOperation::INIT,
            $path ? "Not a Git repository: {$path}" : "Not a Git repository",
            128, // Standard Git error code for not a repo
            $previous
        );
    }

    /**
     * Create an exception for merge conflicts
     */
    public static function mergeConflict(
        string $files = '',
        ?Throwable $previous = null
    ): self {
        return new self(
            GitOperation::MERGE,
            $files ? "Merge conflict in files: {$files}" : "Merge conflict",
            1,
            $previous
        );
    }

    /**
     * Create an exception for authentication failures
     */
    public static function authenticationFailed(
        string $remote = '',
        ?Throwable $previous = null
    ): self {
        return new self(
            GitOperation::PUSH,
            $remote ? "Authentication failed for remote: {$remote}" : "Git authentication failed",
            128,
            $previous
        );
    }

    /**
     * Get the Git operation associated with this exception
     */
    public function getOperation(): GitOperation
    {
        return $this->operation;
    }

    /**
     * Get the command output (if available)
     */
    public function getOutput(): ?string
    {
        return $this->output;
    }

    /**
     * Check if this is a "not a repository" error
     */
    public function isNotRepositoryError(): bool
    {
        return $this->operation === GitOperation::INIT && $this->getCode() === 128;
    }

    /**
     * Get formatted error message with operation context
     */
    public function getFullMessage(): string
    {
        $output = $this->output ? "\nOutput: {$this->output}" : '';
        return "[git:{$this->operation->value}] {$this->getMessage()}{$output}";
    }
}