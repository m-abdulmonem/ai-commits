<?php

namespace Mabdulmonem\AICommits\Providers\AI;

use Mabdulmonem\AICommits\Core\Contracts\AIInterface;
use Mabdulmonem\AICommits\Core\DTO\CommitMessage;
use Mabdulmonem\AICommits\Core\Enums\AIProvider;
use Mabdulmonem\AICommits\Core\Enums\CommitType;
use Mabdulmonem\AICommits\Core\Exceptions\AIException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService implements AIInterface
{
    public const DEFAULT_MODEL = 'llama2';
    private const MAX_TOKENS = 1000;
    private const TEMPERATURE = 0.7;
    private const BASE_URL = 'http://localhost:11434/api';

    public function __construct(
        private string $baseUrl = self::BASE_URL
    ) {
        // Ollama typically doesn't require an API key for local use
    }

    /**
     * Generate a commit message for the given diff
     *
     * @param string $diff The git diff content
     * @param AIProvider $provider The AI provider to use
     * @param string|null $model The model to use (default: llama2)
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
            $response = Http::timeout(30)
                ->post("{$this->baseUrl}/generate", [
                    'model' => $model,
                    'prompt' => $prompt,
                    'options' => [
                        'temperature' => self::TEMPERATURE,
                        'num_predict' => self::MAX_TOKENS,
                    ],
                    'stream' => false,
                ]);

            if ($response->failed()) {
                $this->handleErrorResponse($response, $model);
            }

            return $this->parseResponse($response->json(), $diff);
        } catch (\Exception $e) {
            Log::error('Ollama API request failed', [
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
        try {
            $response = Http::timeout(10)
                ->get("{$this->baseUrl}/tags");

            if ($response->failed()) {
                return [self::DEFAULT_MODEL];
            }

            $models = $response->json()['models'] ?? [];
            return array_map(fn($m) => $m['name'], $models);
        } catch (\Exception $e) {
            Log::error('Failed to fetch Ollama models', ['error' => $e->getMessage()]);
            return [self::DEFAULT_MODEL];
        }
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
            $response = Http::timeout(30)
                ->post("{$this->baseUrl}/generate", [
                    'model' => self::DEFAULT_MODEL,
                    'prompt' => $prompt,
                    'options' => [
                        'temperature' => self::TEMPERATURE,
                        'num_predict' => self::MAX_TOKENS,
                    ],
                    'stream' => false,
                ]);

            if ($response->failed()) {
                $this->handleErrorResponse($response, self::DEFAULT_MODEL);
            }

            return $this->parseResponse($response->json(), '');
        } catch (\Exception $e) {
            Log::error('Ollama API request failed', [
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
            "%s\n\nHere is the commit message to improve:\n\n%s\n\nImproved commit message:",
            $this->getImprovementInstructions(),
            $message
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
     * Handle error responses from Ollama API
     */
    private function handleErrorResponse($response, string $model): void
    {
        $statusCode = $response->status();
        $errorBody = $response->json();

        $errorMessage = $errorBody['error'] ?? $response->body();
        throw AIException::apiRequestFailed(
            AIProvider::LOCAL_LLM,
            $statusCode,
            $errorMessage
        );
    }

    /**
     * Build the prompt for commit message generation
     */
    private function buildPrompt(string $diff): string
    {
        return sprintf(
            "%s\n\nHere is the git diff to analyze:\n\n%s\n\nGenerate a commit message:",
            $this->getInstructions(),
            $diff
        );
    }

    /**
     * Get the instructions for commit message generation
     */
    private function getInstructions(): string
    {
        return $this->getImprovementInstructions(); // Same instructions for both generation and improvement
    }

    /**
     * Parse the API response into a CommitMessage
     */
    private function parseResponse(array $response, string $originalDiff): CommitMessage
    {
        if (!isset($response['response'])) {
            throw AIException::invalidResponse(
                AIProvider::LOCAL_LLM,
                'Missing response in Ollama output'
            );
        }

        $message = trim($response['response']);

        try {
            return CommitMessage::fromString($message);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Failed to parse Ollama response, using raw message', [
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
     * Validate the API configuration
     */
    public static function validateApiKey(string $apiKey): bool
    {
        // Ollama doesn't typically use API keys for local instances
        return true;
    }
}