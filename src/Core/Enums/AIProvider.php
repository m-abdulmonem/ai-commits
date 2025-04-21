<?php

namespace Mabdulmonem\AICommits\Core\Enums;

/**
 * Supported AI providers with their configuration details
 */
enum AIProvider: string
{
    case OPENAI = 'openai';
    case OPENROUTER = 'openrouter';
    case ANTHROPIC = 'anthropic';
    case LOCAL_LLM = 'local';

    /**
     * Get the default model for this provider
     */
    public function defaultModel(): string
    {
        return match ($this) {
            self::OPENAI => 'gpt-3.5-turbo',
            self::OPENROUTER => 'openai/gpt-3.5-turbo',
            self::ANTHROPIC => 'claude-2',
            self::LOCAL_LLM => 'llama2'
        };
    }

    /**
     * Get the API base URL for this provider
     */
    public function baseUrl(): string
    {
        return match ($this) {
            self::OPENAI => 'https://api.openai.com/v1',
            self::OPENROUTER => 'https://openrouter.ai/api/v1',
            self::ANTHROPIC => 'https://api.anthropic.com/v1',
            self::LOCAL_LLM => 'http://localhost:11434' // Ollama default
        };
    }

    /**
     * Get the required API key header name
     */
    public function apiKeyHeader(): string
    {
        return match ($this) {
            self::OPENAI => 'Authorization',
            self::OPENROUTER => 'Authorization',
            self::ANTHROPIC => 'x-api-key',
            self::LOCAL_LLM => '' // Local LLMs may not need auth
        };
    }

    /**
     * Get the authentication prefix (e.g., 'Bearer ')
     */
    public function authPrefix(): string
    {
        return match ($this) {
            self::OPENAI => 'Bearer ',
            self::OPENROUTER => 'Bearer ',
            self::ANTHROPIC => '',
            self::LOCAL_LLM => ''
        };
    }

    /**
     * Get all available providers as choice array
     * 
     * @return array<string,string> [value => label]
     */
    public static function choices(): array
    {
        return [
            self::OPENAI->value => 'OpenAI',
            self::OPENROUTER->value => 'OpenRouter',
            self::ANTHROPIC->value => 'Anthropic',
            self::LOCAL_LLM->value => 'Local LLM'
        ];
    }

    /**
     * Get the default provider
     */
    public static function default(): self
    {
        return self::OPENAI;
    }

    /**
     * Check if this provider requires an API key
     */
    public function requiresApiKey(): bool
    {
        return $this !== self::LOCAL_LLM;
    }

    /**
     * Get the message format required by this provider
     */
    public function messageFormat(): string
    {
        return match ($this) {
            self::ANTHROPIC => 'anthropic',
            default => 'openai'
        };
    }
}