<?php

namespace Mabdulmonem\AICommits\Providers\AI;

use Mabdulmonem\AICommits\Core\Contracts\AIInterface;
use Mabdulmonem\AICommits\Core\DTO\CommitMessage;
use Mabdulmonem\AICommits\Core\Enums\AIProvider;
use Mabdulmonem\AICommits\Core\Enums\CommitType;
use Mabdulmonem\AICommits\Core\Exceptions\AIException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService implements AIInterface
{
    public const DEFAULT_MODEL = 'gpt-3.5-turbo';
    private const MAX_TOKENS = 1000;
    private const TEMPERATURE = 0.7;

    public function __construct(
        private ?string $apiKey,
        private string $baseUrl = 'https://api.openai.com/v1'
    ) {
        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('OpenAI API key cannot be empty');
        }
    }

//    /**
//     * Generate a commit message for the given diff
//     *
//     * @param string $diff The git diff content
//     * @param string|null $model The model to use (default: gpt-3.5-turbo)
//     * @return CommitMessage
//     * @throws AIException
//     */
//    public function generateCommitMessage(string $diff, ?string $model = null): CommitMessage
//    {
//        $model = $model ?: self::DEFAULT_MODEL;
//        $messages = $this->buildMessages($diff);
//
//        try {
//            $response = Http::withHeaders($this->getHeaders())
//                ->timeout(30)
//                ->post("{$this->baseUrl}/chat/completions", [
//                    'model' => $model,
//                    'messages' => $messages,
//                    'temperature' => self::TEMPERATURE,
//                    'max_tokens' => self::MAX_TOKENS,
//                ]);
//
//            if ($response->failed()) {
//                throw AIException::apiRequestFailed(
//                    AIProvider::OPENAI,
//                    $response->status(),
//                    $response->body()
//                );
//            }
//
//            return $this->parseResponse($response->json(), $diff);
//        } catch (\Exception $e) {
//            Log::error('OpenAI API request failed', [
//                'error' => $e->getMessage(),
//                'model' => $model,
//                'diff' => $diff
//            ]);
//
//            throw new AIException(
//                AIProvider::OPENAI,
//                "Failed to generate commit message: {$e->getMessage()}",
//                $e->getCode(),
//                $e
//            );
//        }
//    }

    /**
     * Build the messages for the OpenAI API
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
                'content' => "Generate a commit message for this diff:\n\n{$diff}"
            ]
        ];
    }

    /**
     * Get the system prompt for commit message generation
     */
    private function getSystemPrompt(): string
    {
        return <<<PROMPT
You are a helpful assistant that generates concise Git commit messages following Conventional Commits specification.
Rules:
1. Use one of these types: feat, fix, docs, style, refactor, test, chore, perf, build, ci, revert
2. Use imperative mood ("Add feature" not "Added feature")
3. Keep the subject line under 50 characters
4. Only respond with the commit message, no explanations
5. For complex changes, include a body separated by newlines
6. Breaking changes should be indicated with ! after type/scope
7. Scope is optional but recommended when applicable

Examples:
feat(parser): add support for markdown
fix: handle null values in response
docs: update installation instructions
PROMPT;
    }

    /**
     * Parse the OpenAI API response into a CommitMessage
     */
    private function parseResponse(array $response, string $originalDiff): CommitMessage
    {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw AIException::invalidResponse(
                AIProvider::OPENAI,
                'Missing content in response'
            );
        }

        $message = trim($response['choices'][0]['message']['content']);
        $lines = explode("\n", $message);
        $subject = array_shift($lines);
        $body = trim(implode("\n", $lines));

        try {
            return CommitMessage::fromString($subject)->withDescription(
                $body ? "{$subject}\n\n{$body}" : $subject
            );
        } catch (\InvalidArgumentException $e) {
            Log::warning('Failed to parse OpenAI response, retrying with raw message', [
                'response' => $message,
                'error' => $e->getMessage()
            ]);

            // Fallback to using the raw message if parsing fails
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
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Validate the API key format
     */
    public static function validateApiKey(string $apiKey): bool
    {
        return str_starts_with($apiKey, 'sk-') && strlen($apiKey) > 30;
    }
    public function generateCommitMessage(
        string $diff,
        AIProvider $provider,
        ?string $model = null
    ): CommitMessage {
        $model = $model ?: self::DEFAULT_MODEL;
        $messages = $this->buildMessages($diff);

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => self::TEMPERATURE,
                    'max_tokens' => self::MAX_TOKENS,
                ]);

            if ($response->failed()) {
                throw AIException::apiRequestFailed(
                    $provider,
                    $response->status(),
                    $response->body()
                );
            }

            return $this->parseResponse($response->json(), $diff);
        } catch (\Exception $e) {
            Log::error('OpenAI API request failed', [
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

    public function getAvailableModels(AIProvider $provider): array
    {
        return [
            'gpt-3.5-turbo',
            'gpt-4',
            'gpt-4-turbo',
        ];
    }

    public function validateCommitMessage(
        string $message,
        AIProvider $provider
    ): bool {
        try {
            CommitMessage::fromString($message);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    public function suggestMessageImprovement(
        string $message,
        AIProvider $provider
    ): CommitMessage {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->getImprovementPrompt()
            ],
            [
                'role' => 'user',
                'content' => "Improve this commit message following Conventional Commits:\n\n{$message}"
            ]
        ];

        $response = Http::withHeaders($this->getHeaders())
            ->timeout(30)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => self::DEFAULT_MODEL,
                'messages' => $messages,
                'temperature' => self::TEMPERATURE,
                'max_tokens' => self::MAX_TOKENS,
            ]);

        if ($response->failed()) {
            throw AIException::apiRequestFailed(
                $provider,
                $response->status(),
                $response->body()
            );
        }

        return $this->parseResponse($response->json(), $message);
    }

    private function getImprovementPrompt(): string
    {
        return <<<PROMPT
You are a Git commit message expert. Improve the following commit message to follow Conventional Commits specification.
Rules:
1. Use one of these types: feat, fix, docs, style, refactor, test, chore, perf, build, ci, revert
2. Use imperative mood ("Add feature" not "Added feature")
3. Keep the subject line under 50 characters
4. Only respond with the improved commit message, no explanations
5. For complex changes, include a body separated by newlines
6. Breaking changes should be indicated with ! after type/scope
7. Scope is optional but recommended when applicable
PROMPT;
    }
}