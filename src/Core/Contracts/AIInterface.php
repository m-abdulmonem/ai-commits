<?php

namespace Mabdulmonem\AICommits\Core\Contracts;

use Mabdulmonem\AICommits\Core\DTO\CommitMessage;
use Mabdulmonem\AICommits\Core\Enums\AIProvider;
use Mabdulmonem\AICommits\Core\Exceptions\AIException;

interface AIInterface
{
    /**
     * Generate a commit message for the given diff hunk
     *
     * @param string $diff The git diff hunk
     * @param AIProvider $provider The AI provider to use
     * @param string|null $model Specific model to use (optional)
     * 
     * @return CommitMessage Generated commit message DTO
     * 
     * @throws AIException When message generation fails
     */
    public function generateCommitMessage(
        string $diff,
        AIProvider $provider,
        ?string $model = null
    ): CommitMessage;

    /**
     * Get available AI models for a provider
     *
     * @param AIProvider $provider The AI provider
     * 
     * @return array Array of available models
     */
    public function getAvailableModels(AIProvider $provider): array;

    /**
     * Validate the given commit message
     *
     * @param string $message The commit message to validate
     * @param AIProvider $provider The AI provider to use for validation
     * 
     * @return bool True if message follows conventional commits
     */
    public function validateCommitMessage(
        string $message,
        AIProvider $provider
    ): bool;

    /**
     * Suggest improvements for a commit message
     *
     * @param string $message The original commit message
     * @param AIProvider $provider The AI provider to use
     * 
     * @return CommitMessage Improved commit message
     */
    public function suggestMessageImprovement(
        string $message,
        AIProvider $provider
    ): CommitMessage;
}