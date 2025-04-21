<?php

namespace Mabdulmonem\AICommits\Core\Enums;

/**
 * Conventional Commit types with descriptions and emoji support
 */
enum CommitType: string
{
    case FEAT = 'feat';
    case FIX = 'fix';
    case DOCS = 'docs';
    case STYLE = 'style';
    case REFACTOR = 'refactor';
    case TEST = 'test';
    case CHORE = 'chore';
    case PERF = 'perf';
    case BUILD = 'build';
    case CI = 'ci';
    case REVERT = 'revert';

    /**
     * Get human-readable description of the commit type
     */
    public function description(): string
    {
        return match ($this) {
            self::FEAT => 'A new feature',
            self::FIX => 'A bug fix',
            self::DOCS => 'Documentation changes',
            self::STYLE => 'Code style/formatting changes',
            self::REFACTOR => 'Code refactoring (no functional changes)',
            self::TEST => 'Test additions/modifications',
            self::CHORE => 'Maintenance tasks',
            self::PERF => 'Performance improvements',
            self::BUILD => 'Build system changes',
            self::CI => 'CI/CD pipeline changes',
            self::REVERT => 'Reverts a previous commit'
        };
    }

    /**
     * Get Git emoji for the commit type
     */
    public function emoji(): string
    {
        return match ($this) {
            self::FEAT => '✨',
            self::FIX => '🐛',
            self::DOCS => '📚',
            self::STYLE => '🎨',
            self::REFACTOR => '♻️',
            self::TEST => '🧪',
            self::CHORE => '🔧',
            self::PERF => '⚡',
            self::BUILD => '🏗️',
            self::CI => '👷',
            self::REVERT => '⏪'
        };
    }

    /**
     * Get all types as choice array for CLI/UI selection
     * 
     * @return array<string,string> [value => description]
     */
    public static function choices(): array
    {
        return array_reduce(
            self::cases(),
            fn(array $choices, self $type) => $choices + [$type->value => $type->value . ' - ' . $type->description()],
            []
        );
    }

    /**
     * Get default commit type
     */
    public static function default(): self
    {
        return self::CHORE;
    }

    /**
     * Check if this type represents a significant change
     * (Should appear in changelog/release notes)
     */
    public function isSignificant(): bool
    {
        return in_array($this, [
            self::FEAT,
            self::FIX,
            self::PERF,
            self::REVERT
        ]);
    }

    /**
     * Parse from string with fallback to default
     */
    public static function tryFromString(?string $value): self
    {
        if ($value === null) {
            return self::default();
        }

        return self::tryFrom(strtolower($value)) ?? self::default();
    }
}