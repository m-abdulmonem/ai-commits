<?php

namespace Mabdulmonem\AICommits\Providers\AI;

use Mabdulmonem\AICommits\Core\Contracts\AIInterface;
use Mabdulmonem\AICommits\Core\DTO\CommitMessage;
use Mabdulmonem\AICommits\Core\Enums\AIProvider;
use Mabdulmonem\AICommits\Core\Exceptions\AIException;
use Illuminate\Support\Facades\{Http,Log};

class OpenRouterService implements AIInterface
{
    public const DEFAULT_MODEL = 'openai/gpt-3.5-turbo';
    private const MAX_TOKENS = 1000;
    private const TEMPERATURE = 0.7;
    private const BASE_URL = 'https://openrouter.ai/api/v1';

    public function __construct(
        private string $apiKey,
        private string $baseUrl = self::BASE_URL
    ) {
        // if (empty($this->apiKey)) {
        //     throw new \InvalidArgumentException('OpenRouter API key cannot be empty');
        // }
    }

    /**
     * Generate a commit message for the given diff
     *
     * @param string $diff The git diff content
     * @param string|null $model The model to use (format: provider/model)
     * @return CommitMessage
     * @throws AIException
     */
    public function generateCommitMessage(string $diff,AIProvider $provider, ?string $model = null): CommitMessage
    {
        $model = $model ?: self::DEFAULT_MODEL;
        $messages = $this->buildMessages($diff);

        try {
            $response = Http::withHeaders($this->getHeaders($model))
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => self::TEMPERATURE,
                    'max_tokens' => self::MAX_TOKENS,
                ]);

            if ($response->failed()) {
                $this->handleErrorResponse($response, $model);
            }

            return $this->parseResponse($response->json(), $diff);
        } catch (\Exception $e) {
            Log::error('OpenRouter API request failed', [
                'error' => $e->getMessage(),
                'model' => $model,
                'diff' => $diff
            ]);

            throw new AIException(
                AIProvider::OPENROUTER,
                "Failed to generate commit message: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Handle error responses from OpenRouter
     */
    private function handleErrorResponse($response, string $model): void
    {
        $statusCode = $response->status();
        $errorBody = $response->json();

        if ($statusCode === 429) {
            $retryAfter = $errorBody['error']['retry_after'] ?? 0;
            throw AIException::rateLimited(
                AIProvider::OPENROUTER,
                $retryAfter
            );
        }

        $errorMessage = $errorBody['error']['message'] ?? $response->body();
        throw AIException::apiRequestFailed(
            AIProvider::OPENROUTER,
            $statusCode,
            $errorMessage
        );
    }

    /**
     * Build the messages for the API request
     */
    private function buildMessages(string $diff): array
    {
        return [
            [
                'role' => 'system',
                'content' => $this->getSystemPrompt()
            ],
            [
                'role' => 'user',
                'content' => "Generate a commit message for this diff:\n\n{$diff}\n\n" .
                    "Respond ONLY with the commit message following Conventional Commits."
            ]
        ];
    }

    /**
     * Get the system prompt for commit message generation
     */
    private function getSystemPrompt(): string
    {
        return <<<PROMPT
You are an expert at writing perfect Git commit messages following Conventional Commits specification.
Rules:
1. Use one of these types: feat, fix, docs, style, refactor, test, chore, perf, build, ci, revert
2. Use imperative mood ("Add feature" not "Added feature")
3. Keep the subject line under 72 characters
4. Only respond with the raw commit message, no explanations or formatting
5. For complex changes, include a body separated by a blank line
6. Breaking changes should be indicated with ! after type/scope
7. Scope is optional but recommended when applicable

Examples:
feat(auth): add OAuth2 support
fix: handle null pointer in user service
docs: update README installation section
PROMPT;
    }

    /**
     * Parse the API response into a CommitMessage
     */
    private function parseResponse(array $response, string $originalDiff): CommitMessage
    {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw AIException::invalidResponse(
                AIProvider::OPENROUTER,
                'Missing content in response'
            );
        }

        $message = trim($response['choices'][0]['message']['content']);

        try {
            return CommitMessage::fromString($message);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Failed to parse OpenRouter response, using raw message', [
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
     * Get the API headers including model information
     */
    private function getHeaders(string $model): array
    {
        return [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('app.url', 'https://github.com/Mabdulmonem/ai-commits'),
            'X-Title' => 'AI Commits',
        ];
    }

    /**
     * Get available models from OpenRouter
     */
    public function getAvailableModels(AIProvider $provider): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders(self::DEFAULT_MODEL))
                ->get("{$this->baseUrl}/models");

            if ($response->failed()) {
                throw AIException::apiRequestFailed(
                    AIProvider::OPENROUTER,
                    $response->status(),
                    $response->body()
                );
            }

            return array_map(fn($model) => $model['id'], $response->json()['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Failed to fetch OpenRouter models', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Validate the API key format
     */
    public static function validateApiKey(string $apiKey): bool
    {
        return str_starts_with($apiKey, 'sk-or-') && strlen($apiKey) > 30;
    }

    public function validateCommitMessage(string $message, AIProvider $provider): bool
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->getValidationSystemPrompt()
            ],
            [
                'role' => 'user',
                'content' => "Validate this commit message following Conventional Commits:\n\n{$message}\n\n" .
                    "Respond ONLY with 'true' if valid or 'false' if invalid."
            ]
        ];

        try {
            $response = Http::withHeaders($this->getHeaders(self::DEFAULT_MODEL))
                ->timeout(15)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => self::DEFAULT_MODEL,
                    'messages' => $messages,
                    'temperature' => 0.3, // Lower temperature for validation
                    'max_tokens' => 10,   // Only need a simple true/false response
                ]);

            if ($response->failed()) {
                $this->handleErrorResponse($response, self::DEFAULT_MODEL);
            }

            $content = trim(strtolower($response->json()['choices'][0]['message']['content'] ?? 'false'));
            return $content === 'true';
        } catch (\Exception $e) {
            Log::error('OpenRouter validation failed', [
                'error' => $e->getMessage(),
                'message' => $message
            ]);
            return false;
        }
    }

    public function suggestMessageImprovement(string $message, AIProvider $provider): CommitMessage
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->getSystemPrompt() // Reuse the same system prompt
            ],
            [
                'role' => 'user',
                'content' => "Improve this commit message to follow Conventional Commits:\n\n{$message}\n\n" .
                    "Respond ONLY with the improved commit message."
            ]
        ];

        try {
            $response = Http::withHeaders($this->getHeaders(self::DEFAULT_MODEL))
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => self::DEFAULT_MODEL,
                    'messages' => $messages,
                    'temperature' => self::TEMPERATURE,
                    'max_tokens' => self::MAX_TOKENS,
                ]);

            if ($response->failed()) {
                $this->handleErrorResponse($response, self::DEFAULT_MODEL);
            }

            return $this->parseResponse($response->json(), $message);
        } catch (\Exception $e) {
            Log::error('OpenRouter message improvement failed', [
                'error' => $e->getMessage(),
                'message' => $message
            ]);

            throw new AIException(
                AIProvider::OPENROUTER,
                "Failed to improve commit message: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    private function getValidationSystemPrompt(): string
    {
        return <<<PROMPT
You are an expert at validating Git commit messages following Conventional Commits specification.
Rules:
1. Check if the message has one of these types: feat, fix, docs, style, refactor, test, chore, perf, build, ci, revert
2. Check if the subject uses imperative mood
3. Check if the subject line is under 72 characters
4. For complex changes, check if body is properly formatted
5. Check if breaking changes are properly indicated with ! after type/scope
6. Check if scope is properly used when applicable

Respond ONLY with 'true' if the message follows all rules or 'false' if it violates any rule.
PROMPT;
    }
}