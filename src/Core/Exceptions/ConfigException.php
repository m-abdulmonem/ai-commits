<?php

namespace Mabdulmonem\AICommits\Core\Exceptions;

use Throwable;

/**
 * Exception thrown when configuration is invalid or missing
 */
class ConfigException extends \RuntimeException
{
    /**
     * @param string $configKey The configuration key that caused the exception
     * @param string $message The exception message
     * @param int $code The exception code
     * @param Throwable|null $previous The previous exception
     * @param array $context Additional context about the error
     */
    public function __construct(
        private readonly string $configKey,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        private readonly array $context = []
    ) {
        parent::__construct(
            $message ?: "Configuration error for key: {$configKey}",
            $code,
            $previous
        );
    }

    /**
     * Create an exception for missing required configuration
     */
    public static function missingConfig(
        string $configKey,
        ?string $instructions = null,
        ?Throwable $previous = null
    ): self {
        $message = "Missing required configuration: {$configKey}";
        if ($instructions) {
            $message .= ". {$instructions}";
        }

        return new self(
            $configKey,
            $message,
            500,
            $previous,
            ['instructions' => $instructions]
        );
    }

    /**
     * Create an exception for invalid configuration value
     */
    public static function invalidConfig(
        string $configKey,
        $invalidValue,
        ?string $expected = null,
        ?Throwable $previous = null
    ): self {
        $message = "Invalid value for configuration {$configKey}";
        if ($expected) {
            $message .= ". Expected: {$expected}";
        }
        $message .= ", got: " . (is_scalar($invalidValue) ? $invalidValue : gettype($invalidValue));

        return new self(
            $configKey,
            $message,
            500,
            $previous,
            [
                'invalid_value' => $invalidValue,
                'expected' => $expected
            ]
        );
    }

    /**
     * Create an exception for unsupported configuration combination
     */
    public static function unsupportedCombination(
        string $configKey,
        array $conflictingValues,
        ?string $reason = null,
        ?Throwable $previous = null
    ): self {
        $message = "Unsupported configuration combination for {$configKey}: "
            . json_encode($conflictingValues);
        if ($reason) {
            $message .= ". {$reason}";
        }

        return new self(
            $configKey,
            $message,
            500,
            $previous,
            [
                'conflicting_values' => $conflictingValues,
                'reason' => $reason
            ]
        );
    }

    /**
     * Get the configuration key that caused the exception
     */
    public function getConfigKey(): string
    {
        return $this->configKey;
    }

    /**
     * Get additional error context
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get formatted error message with configuration context
     */
    public function getFullMessage(): string
    {
        $context = empty($this->context) ? '' : "\nContext: " . json_encode($this->context, JSON_PRETTY_PRINT);
        return "[Config:{$this->configKey}] {$this->getMessage()}{$context}";
    }

    /**
     * Check if this is a missing configuration error
     */
    public function isMissingConfig(): bool
    {
        return strpos($this->getMessage(), 'Missing required configuration') === 0;
    }
}