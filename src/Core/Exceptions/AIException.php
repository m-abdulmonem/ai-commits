<?php

namespace Mabdulmonem\AICommits\Core\Exceptions;

use Mabdulmonem\AICommits\Core\Enums\AIProvider;
use Throwable;

/**
 * Exception thrown when AI-related operations fail
 */
class AIException extends \RuntimeException
{
    /**
     * @param AIProvider $provider The AI provider that caused the exception
     * @param string $message The exception message
     * @param int $code The exception code
     * @param Throwable|null $previous The previous exception
     */
    public function __construct(
        private readonly AIProvider $provider,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            "[{$provider->value}] {$message}",
            $code,
            $previous
        );
    }

    /**
     * Create an exception for API request failure
     */
    public static function apiRequestFailed(
        AIProvider $provider,
        int $statusCode,
        string $response = '',
        ?Throwable $previous = null
    ): self {
        return new self(
            $provider,
            "API request failed with status {$statusCode}. Response: {$response}",
            $statusCode,
            $previous
        );
    }

    /**
     * Create an exception for invalid API response
     */
    public static function invalidResponse(
        AIProvider $provider,
        string $details = '',
        ?Throwable $previous = null
    ): self {
        return new self(
            $provider,
            "Invalid API response format. {$details}",
            500,
            $previous
        );
    }

    /**
     * Create an exception for rate limiting
     */
    public static function rateLimited(
        AIProvider $provider,
        int $retryAfter = 0,
        ?Throwable $previous = null
    ): self {
        return new self(
            $provider,
            $retryAfter > 0
            ? "Rate limited. Try again in {$retryAfter} seconds."
            : "Rate limit exceeded.",
            429,
            $previous
        );
    }

    /**
     * Create an exception for unsupported model
     */
    public static function unsupportedModel(
        AIProvider $provider,
        string $model,
        ?Throwable $previous = null
    ): self {
        return new self(
            $provider,
            "Model '{$model}' is not supported by {$provider->value}",
            400,
            $previous
        );
    }


    /**
     * Create an exception for unsupported model
     */
    public static function unsupportedProvider(
        AIProvider $provider,
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
     * Get the AI provider associated with this exception
     */
    public function getProvider(): AIProvider
    {
        return $this->provider;
    }

    /**
     * Get formatted error message with provider context
     */
    public function getFullMessage(): string
    {
        return "[AI:{$this->provider->value}] {$this->getMessage()}";
    }
}