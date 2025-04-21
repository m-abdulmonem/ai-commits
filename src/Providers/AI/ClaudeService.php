<?php

namespace Mabdulmonem\AICommits\Providers\AI;

use Mabdulmonem\AICommits\Core\Contracts\AIInterface;
use Mabdulmonem\AICommits\Core\DTO\CommitMessage;
use Mabdulmonem\AICommits\Core\Enums\AIProvider;
use Mabdulmonem\AICommits\Core\Enums\CommitType;
use Mabdulmonem\AICommits\Core\Exceptions\AIException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeService implements AIInterface
{
    public const DEFAULT_MODEL = 'claude-2';
    private const MAX_TOKENS = 1000;
    private const TEMPERATURE = 0.7;
    private const BASE_URL = 'https://api.anthropic.com/v1';

    public function __construct(
        private ?string $apiKey,
        private string $baseUrl = self::BASE_URL
    ) {
        // if (empty($this->apiKey)) {
        //     throw new \InvalidArgumentException('Anthropic API key cannot be empty');
        // }
    }

    /**
     * Generate a commit message for the given diff
     *
     * @param string $diff The git diff content
     * @param AIProvider $provider The AI provider to use
     * @param string|null $model The model to use (default: claude-2)
     * @return CommitMessage
     * @throws AIException
     */
    public function generateCommitMessage(
        string $diff,
        AIProvider $provider,
        ?string $model = null
    ): CommitMessage {
        $model = $model ?: self::DEFAULT_MODEL;
        $prompt = $this->buildPrompt($diff);

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->post("{$this->baseUrl}/complete", [
                    'model' => $model,
                    'prompt' => $prompt,
                    'max_tokens_to_sample' => self::MAX_TOKENS,
                    'temperature' => self::TEMPERATURE,
                    'stop_sequences' => ["\n\nHuman:"],
                ]);

            if ($response->failed()) {
                $this->handleErrorResponse($response, $model);
            }

            return $this->parseResponse($response->json(), $diff);
        } catch (\Exception $e) {
            Log::error('Anthropic API request failed', [
                'error' => $e->getMessage(),
                'model' => $model,
                'diff' => $diff
            ]);

            throw new AIException(
                $provider,
                "Failed to generate commit message: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get available AI models for the provider
     */
    public function getAvailableModels(AIProvider $provider): array
    {
        return [
            'claude-2',
            'claude-instant-1'
        ];
    }

    /**
     * Validate the given commit message
     */
    public function validateCommitMessage(string $message, AIProvider $provider): bool
    {
        try {
            CommitMessage::fromString($message);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Suggest improvements for a commit message
     */
    public function suggestMessageImprovement(string $message, AIProvider $provider): CommitMessage
    {
        $prompt = $this->buildImprovementPrompt($message);

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->post("{$this->baseUrl}/complete", [
                    'model' => self::DEFAULT_MODEL,
                    'prompt' => $prompt,
                    'max_tokens_to_sample' => self::MAX_TOKENS,
                    'temperature' => self::TEMPERATURE,
                    'stop_sequences' => ["\n\nHuman:"],
                ]);

            if ($response->failed()) {
                $this->handleErrorResponse($response, self::DEFAULT_MODEL);
            }

            return $this->parseResponse($response->json(), '');
        } catch (\Exception $e) {
            Log::error('Anthropic API request failed', [
                'error' => $e->getMessage(),
                'model' => self::DEFAULT_MODEL,
                'message' => $message
            ]);

            throw new AIException(
                $provider,
                "Failed to suggest message improvement: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Build the prompt for message improvement
     */
    private function buildImprovementPrompt(string $message): string
    {
        return sprintf(
            "\n\nHuman: %s\n\n%s\n\nAssistant:",
            $this->getImprovementInstructions(),
            "Here is the commit message to improve:\n\n{$message}\n\nImproved commit message:"
        );
    }

    /**
     * Get the instructions for message improvement
     */
    private function getImprovementInstructions(): string
    {
        return <<<INSTRUCTIONS
You are an expert at writing perfect Git commit messages following Conventional Commits specification.
Please improve the given commit message following these rules:

1. Use one of these types: feat, fix, docs, style, refactor, test, chore, perf, build, ci, revert
2. Use imperative mood ("Add feature" not "Added feature")
3. Keep the subject line under 50 characters
4. Only respond with the raw commit message, no explanations or formatting
5. For complex changes, include a body separated by a blank line
6. Breaking changes should be indicated with ! after type/scope
7. Scope is optional but recommended when applicable

Example responses:
feat(auth): add OAuth2 support
fix: handle null pointer in user service
docs: update README installation section
INSTRUCTIONS;
    }

    /**
     * Handle error responses from Anthropic API
     */
    private function handleErrorResponse($response, string $model): void
    {
        $statusCode = $response->status();
        $errorBody = $response->json();

        if ($statusCode === 429) {
            $retryAfter = $errorBody['error']['retry_after'] ?? 0;
            throw AIException::rateLimited(
                AIProvider::ANTHROPIC,
                $retryAfter
            );
        }

        $errorMessage = $errorBody['error']['message'] ?? $response->body();
        throw AIException::apiRequestFailed(
            AIProvider::ANTHROPIC,
            $statusCode,
            $errorMessage
        );
    }

    /**
     * Build the prompt for Claude's specific format
     */
    private function buildPrompt(string $diff): string
    {
        return sprintf(
            "\n\nHuman: %s\n\n%s\n\nAssistant:",
            $this->getInstructions(),
            "Here is the git diff to analyze:\n\n{$diff}\n\nGenerate a commit message:"
        );
    }

    /**
     * Get the instructions for commit message generation
     */
    private function getInstructions(): string
    {
        return <<<INSTRUCTIONS
You are an expert at writing perfect Git commit messages following Conventional Commits specification.
Please generate a concise commit message for the given git diff following these rules:

1. Use one of these types: feat, fix, docs, style, refactor, test, chore, perf, build, ci, revert
2. Use imperative mood ("Add feature" not "Added feature")
3. Keep the subject line under 50 characters
4. Only respond with the raw commit message, no explanations or formatting
5. For complex changes, include a body separated by a blank line
6. Breaking changes should be indicated with ! after type/scope
7. Scope is optional but recommended when applicable

Example responses:
feat(auth): add OAuth2 support
fix: handle null pointer in user service
docs: update README installation section
INSTRUCTIONS;
    }

    /**
     * Parse the API response into a CommitMessage
     */
    private function parseResponse(array $response, string $originalDiff): CommitMessage
    {
        if (!isset($response['completion'])) {
            throw AIException::invalidResponse(
                AIProvider::ANTHROPIC,
                'Missing completion in response'
            );
        }

        $message = trim($response['completion']);

        try {
            return CommitMessage::fromString($message);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Failed to parse Claude response, using raw message', [
                'response' => $message,
                'error' => $e->getMessage()
            ]);

            return new CommitMessage(
                CommitType::CHORE,
                $message
            );
        }
    }

    /**
     * Get the API headers
     */
    private function getHeaders(): array
    {
        return [
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ];
    }

    /**
     * Validate the API key format
     */
    public static function validateApiKey(string $apiKey): bool
    {
        return preg_match('/^sk-ant-[a-zA-Z0-9-]+$/', $apiKey) === 1;
    }
}