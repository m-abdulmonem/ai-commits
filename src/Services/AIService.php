<?php

namespace Mabdulmonem\AICommits\Services;

use Mabdulmonem\AICommits\Core\Contracts\AIInterface;
use Mabdulmonem\AICommits\Core\DTO\CommitMessage;
use Mabdulmonem\AICommits\Core\Enums\AIProvider;
use Mabdulmonem\AICommits\Core\Exceptions\AIException;
use Mabdulmonem\AICommits\Providers\AI\OllamaService;
use Mabdulmonem\AICommits\Providers\AI\OpenAIService;
use Mabdulmonem\AICommits\Providers\AI\OpenRouterService;
use Mabdulmonem\AICommits\Providers\AI\ClaudeService;

class AIService implements AIInterface
{
    /** @var array<string,OpenAIService|OpenRouterService|ClaudeService> */
    private array $providers = [];

    public function __construct(
        OpenAIService $openAI,
        OpenRouterService $openRouter,
        ClaudeService $claude
    ) {
        $this->providers = [
            AIProvider::OPENAI->value => $openAI,
            AIProvider::OPENROUTER->value => $openRouter,
            AIProvider::ANTHROPIC->value => $claude,
        ];
    }

    /**
     * Generate a commit message for the given diff
     */
    public function generateCommitMessage(
        string $diff,
        AIProvider $provider,
        ?string $model = null
    ): CommitMessage {
        $this->validateProvider($provider);

        try {
            return $this->providers[$provider->value]->generateCommitMessage($diff,$provider, $model);
        } catch (\Exception $e) {
            throw new AIException(
                $provider,
                "Failed to generate commit message: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get available models from all providers
     *
     * @return array<string,array> [provider => models]
     */
    public function getAvailableModels(AIProvider $provider): array
    {
        $models = [];

        foreach ($this->providers as $provider => $service) {
            try {
                if (method_exists($service, 'getAvailableModels')) {
                    $models[$provider] = $service->getAvailableModels($provider);
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $models;
    }

    /**
     * Validate the given commit message
     */
    public function validateCommitMessage(
        string $message,
        AIProvider $provider
    ): bool {
        $this->validateProvider($provider);

        try {
            $result = $this->providers[$provider->value]->generateCommitMessage(
                "Validate this commit message:\n\n{$message}\n\nIs it valid?",
                $provider,
                model: $this->getDefaultModel($provider)
            );
            return str_contains(strtolower($result->toString()), 'yes');
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Suggest improvements for a commit message
     */
    public function suggestMessageImprovement(
        string $message,
        AIProvider $provider
    ): CommitMessage {
        $this->validateProvider($provider);

        try {
            return $this->providers[$provider->value]->generateCommitMessage(
                "Improve this commit message following Conventional Commits:\n\n{$message}",
                $provider,
                model: $this->getDefaultModel($provider)
            );
        } catch (\Exception $e) {
            throw new AIException(
                $provider,
                "Failed to suggest improvements: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get the default model for a provider
     */
    public function getDefaultModel(AIProvider $provider): string
    {
        return match ($provider) {
            AIProvider::OPENAI => OpenAIService::DEFAULT_MODEL,
            AIProvider::OPENROUTER => OpenRouterService::DEFAULT_MODEL,
            AIProvider::ANTHROPIC => ClaudeService::DEFAULT_MODEL,
            AIProvider::LOCAL_LLM => OllamaService::DEFAULT_MODEL,
            default => throw AIException::unsupportedProvider($provider)
        };
    }

    /**
     * Test connection to an AI provider
     */
    public function testConnection(AIProvider $provider): bool
    {
        $this->validateProvider($provider);

        try {
            $this->providers[$provider->value]->generateCommitMessage(
                'diff --git a/test.txt b/test.txt\nnew file mode 100644',
                $provider,
                model: $this->getDefaultModel($provider)
            );
            return true;
        } catch (\Exception $e) {
            throw new AIException(
                $provider,
                "Connection test failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Validate that the provider is supported
     */
    private function validateProvider(AIProvider $provider): void
    {
        if (!isset($this->providers[$provider->value])) {
            throw AIException::unsupportedProvider($provider);
        }
    }
}