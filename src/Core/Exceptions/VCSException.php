<?php

namespace Mabdulmonem\AICommits\Core\Exceptions;

use Mabdulmonem\AICommits\Core\Enums\VCSProvider;
use Throwable;

/**
 * Exception thrown when Version Control System operations fail
 */
class VCSException extends \RuntimeException
{
    /**
     * @param VCSProvider $provider The VCS provider that caused the exception
     * @param string $message The exception message
     * @param int $code The HTTP status code or custom error code
     * @param Throwable|null $previous The previous exception
     * @param array $context Additional error context
     */
    public function __construct(
        private readonly VCSProvider $provider,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        private readonly array $context = []
    ) {
        parent::__construct(
            "[{$provider->displayName()}] {$message}",
            $code,
            $previous
        );
    }

    /**
     * Create an exception for API request failure
     */
    public static function apiRequestFailed(
        VCSProvider $provider,
        int $statusCode,
        string $response = '',
        ?Throwable $previous = null
    ): self {
        return new self(
            $provider,
            "API request failed with status {$statusCode}. Response: {$response}",
            $statusCode,
            $previous,
            ['response' => $response]
        );
    }

    /**
     * Create an exception for repository not found
     */
    public static function repositoryNotFound(
        VCSProvider $provider,
        string $repoIdentifier,
        ?Throwable $previous = null
    ): self {
        return new self(
            $provider,
            "Repository not found: {$repoIdentifier}",
            404,
            $previous,
            ['repository' => $repoIdentifier]
        );
    }

    /**
     * Create an exception for authentication failures
     */
    public static function authenticationFailed(
        VCSProvider $provider,
        ?Throwable $previous = null
    ): self {
        return new self(
            $provider,
            "Authentication failed. Please check your credentials.",
            401,
            $previous
        );
    }

    /**
     * Create an exception for authentication failures
     */
    public static function unsupportedProvider(
        VCSProvider $provider,
        ?Throwable $previous = null
    ): self {
        return new self(
            $provider,
            "Provider '{$provider->value}' is not supported",
            400,
            $previous
        );
    }

    /**
     * Create an exception for rate limiting
     */
    public static function rateLimited(
        VCSProvider $provider,
        int $retryAfter = 0,
        ?Throwable $previous = null
    ): self {
        return new self(
            $provider,
            $retryAfter > 0
            ? "Rate limited. Try again in {$retryAfter} seconds."
            : "Rate limit exceeded.",
            429,
            $previous,
            ['retry_after' => $retryAfter]
        );
    }

    /**
     * Create an exception for insufficient permissions
     */
    public static function insufficientPermissions(
        VCSProvider $provider,
        string $requiredPermission,
        ?Throwable $previous = null
    ): self {
        return new self(
            $provider,
            "Insufficient permissions. Required: {$requiredPermission}",
            403,
            $previous,
            ['required_permission' => $requiredPermission]
        );
    }

    /**
     * Get the VCS provider associated with this exception
     */
    public function getProvider(): VCSProvider
    {
        return $this->provider;
    }

    /**
     * Get additional error context
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get the retry-after time if this is a rate limit error
     */
    public function getRetryAfter(): ?int
    {
        return $this->context['retry_after'] ?? null;
    }

    /**
     * Get formatted error message with provider context
     */
    public function getFullMessage(): string
    {
        return "[VCS:{$this->provider->value}] {$this->getMessage()}";
    }

    /**
     * Check if this is an authentication error
     */
    public function isAuthenticationError(): bool
    {
        return $this->getCode() === 401;
    }

    /**
     * Check if this is a "not found" error
     */
    public function isNotFoundError(): bool
    {
        return $this->getCode() === 404;
    }
}