<?php

namespace Mabdulmonem\AICommits\Core\Enums;

/**
 * Enum representing different Git operations with metadata
 */
enum GitOperation: string
{

    case INIT = 'init';
    case CLONE = 'clone';
    case COMMIT = 'commit';
    case PUSH = 'push';
    case PULL = 'pull';
    case BRANCH = 'branch';
    case CHECKOUT = 'checkout';
    case MERGE = 'merge';
    case REBASE = 'rebase';
    case STASH = 'stash';
    case TAG = 'tag';
    case FETCH = 'fetch';
    case REMOTE = 'remote';
    case DIFF = 'diff';
    case LOG = 'log';
    case STATUS = 'status';
    case RESET = 'reset';
    case REVERT = 'revert';
    case ADD = 'add';
    case RM = 'rm';
    case MV = 'mv';
    case LS_FILES = 'ls-files';  // Added missing case
    case APPLY = 'apply';       // Added missing case

    /**
     * Get the human-readable description of the operation
     */
    public function description(): string
    {
        return match ($this) {
            self::INIT => 'Initialize a new repository',
            self::CLONE => 'Clone a repository',
            self::COMMIT => 'Record changes to the repository',
            self::PUSH => 'Update remote refs along with associated objects',
            self::PULL => 'Fetch from and integrate with another repository',
            self::BRANCH => 'List, create, or delete branches',
            self::CHECKOUT => 'Switch branches or restore working tree files',
            self::MERGE => 'Join two or more development histories together',
            self::REBASE => 'Reapply commits on top of another base tip',
            self::STASH => 'Stash the changes in a dirty working directory',
            self::TAG => 'Create, list, delete or verify a tag object',
            self::FETCH => 'Download objects and refs from another repository',
            self::REMOTE => 'Manage set of tracked repositories',
            self::DIFF => 'Show changes between commits, commit and working tree, etc',
            self::LOG => 'Show commit logs',
            self::STATUS => 'Show the working tree status',
            self::RESET => 'Reset current HEAD to the specified state',
            self::REVERT => 'Revert some existing commits',
            self::ADD => 'Add file contents to the index',
            self::RM => 'Remove files from the working tree and the index',
            self::MV => 'Move or rename a file, directory, or symlink',
            self::LS_FILES => 'Show information about files in the index and the working tree',
            self::APPLY => 'Apply a patch to files and/or to the index'
        };
    }

    /**
     * Get the base Git command for this operation
     */
    public function command(): string
    {
        return "git {$this->value}";
    }

    /**
     * Check if this operation modifies the repository history
     */
    public function modifiesHistory(): bool
    {
        return in_array($this, [
            self::COMMIT,
            self::MERGE,
            self::REBASE,
            self::RESET,
            self::REVERT
        ]);
    }

    /**
     * Check if this operation requires network access
     */
    public function requiresNetwork(): bool
    {
        return in_array($this, [
            self::CLONE ,
            self::PUSH,
            self::PULL,
            self::FETCH
        ]);
    }

    /**
     * Check if this operation is read-only
     */
    public function isReadOnly(): bool
    {
        return in_array($this, [
            self::LOG,
            self::STATUS,
            self::DIFF
        ]);
    }

    /**
     * Get all operations as choice array for CLI/UI selection
     * 
     * @return array<string,string> [value => description]
     */
    public static function choices(): array
    {
        return array_reduce(
            self::cases(),
            fn(array $choices, self $op) => $choices + [$op->value => "{$op->value} - {$op->description()}"],
            []
        );
    }

    /**
     * Get the default operation
     */
    public static function default(): self
    {
        return self::COMMIT;
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