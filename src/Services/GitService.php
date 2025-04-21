<?php

namespace Mabdulmonem\AICommits\Services;

use Mabdulmonem\AICommits\Core\Contracts\GitInterface;
use Mabdulmonem\AICommits\Core\DTO\DiffHunk;
use Mabdulmonem\AICommits\Core\Enums\GitOperation;
use Mabdulmonem\AICommits\Core\Exceptions\GitException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class GitService implements GitInterface
{
    /**
     * Check if current directory is a Git repository
     */
    public function isRepository(): bool
    {
        return is_dir('.git') || $this->runCommand('git rev-parse --git-dir', false) !== false;
    }

    /**
     * Initialize a new Git repository
     */
    public function init(): void
    {
        $this->executeGitCommand(GitOperation::INIT);
    }

    /**
     * Get all unstaged diff hunks
     *
     * @return array<DiffHunk>
     */
    public function getDiffHunks(): array
    {
        $diff = $this->executeGitCommand(GitOperation::DIFF, ['--patch']);
        return $this->parseDiffHunks($diff);
    }

    /**
     * Get all untracked files
     *
     * @return array<string>
     */
    public function getUntrackedFiles(): array
    {
        $files = $this->executeGitCommand(GitOperation::LS_FILES, ['--others', '--exclude-standard']);
        return array_filter(explode("\n", $files));
    }

    /**
     * Stage a specific hunk
     */
    public function stageHunk(DiffHunk $hunk): void
    {
        $patchFile = tempnam(sys_get_temp_dir(), 'hunk_');
        file_put_contents($patchFile, $hunk->getFullDiff());

        try {
            $this->executeGitCommand(GitOperation::APPLY, ['--cached', $patchFile]);
        } finally {
            unlink($patchFile);
        }
    }

    /**
     * Stage all changes
     */
    public function stageAll(): void
    {
        $this->executeGitCommand(GitOperation::ADD, ['.']);
    }

    /**
     * Create a new commit
     */
    public function commit(string $message): void
    {
        $this->executeGitCommand(GitOperation::COMMIT, ['-m', $message]);
    }

    /**
     * Push changes to remote
     */
    public function push(string $branch, bool $setUpstream = false): void
    {
        $args = $setUpstream ? ['--set-upstream', 'origin', $branch] : [];
        $this->executeGitCommand(GitOperation::PUSH, $args);
    }

    /**
     * Add a remote repository
     */
    public function addRemote(string $name, string $url): void
    {
        $this->executeGitCommand(GitOperation::REMOTE, ['add', $name, $url]);
    }

    /**
     * Get current branch name
     */
    public function getCurrentBranch(): string
    {
        return trim($this->executeGitCommand(GitOperation::BRANCH, ['--show-current']));
    }

    /**
     * Check if branch has upstream configured
     */
    public function hasUpstream(string $branch): bool
    {
        try {
            $this->executeGitCommand(GitOperation::BRANCH, ['-vv']);
            return true;
        } catch (GitException) {
            return false;
        }
    }

    /**
     * Get list of remotes
     *
     * @return array<string,string> [name => url]
     */
    public function getRemotes(): array
    {
        $output = $this->executeGitCommand(GitOperation::REMOTE, ['-v']);
        $remotes = [];

        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^(\w+)\s+(.*?)\s+\((fetch|push)\)$/', $line, $matches)) {
                $remotes[$matches[1]] = $matches[2];
            }
        }

        return $remotes;
    }

    /**
     * Execute a Git command with error handling
     *
     * @param GitOperation $operation
     * @param array<string> $args
     * @return string
     * @throws GitException
     */
    private function executeGitCommand(GitOperation $operation, array $args = []): string
    {
        $command = array_merge(['git', $operation->value], $args);
        $process = new Process($command);

        try {
            $process->mustRun();
            return trim($process->getOutput());
        } catch (ProcessFailedException $e) {
            throw GitException::commandFailed(
                $operation,
                $process->getCommandLine(),
                $process->getExitCode(),
                $process->getErrorOutput()
            );
        }
    }

    /**
     * Run a command with optional silent failure
     */
    private function runCommand(string $command, bool $throwOnError = true): ?string
    {
        $process = Process::fromShellCommandline($command);

        try {
            $process->run();
            if (!$process->isSuccessful() && $throwOnError) {
                throw new ProcessFailedException($process);
            }
            return $process->isSuccessful() ? trim($process->getOutput()) : null;
        } catch (ProcessFailedException $e) {
            if ($throwOnError) {
                throw $e;
            }
            return null;
        }
    }

    /**
     * Parse raw diff output into hunks
     *
     * @param string $diff
     * @return array<DiffHunk>
     */
    private function parseDiffHunks(string $diff): array
    {
        $hunks = [];
        $rawHunks = preg_split('/^diff --git/m', $diff, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($rawHunks as $rawHunk) {
            try {
                $hunks[] = DiffHunk::fromDiffString("diff --git" . $rawHunk);
            } catch (\Exception $e) {
                continue; // Skip invalid hunks
            }
        }

        return $hunks;
    }

//    public function getCommitHistory(int $limit = 10): array
//    {
//        $format = '%H|%an|%ae|%ad|%s'; // Hash|Author Name|Author Email|Date|Subject
//        $args = [
//            '--pretty=format:' . $format,
//            '--date=iso',
//            '--max-count=' . $limit
//        ];
//
//        $logOutput = $this->executeGitCommand(GitOperation::LOG, $args);
//
//        $commits = [];
//        foreach (explode("\n", $logOutput) as $line) {
//            if (empty($line)) {
//                continue;
//            }
//
//            $parts = explode('|', $line, 5);
//            if (count($parts) !== 5) {
//                continue;
//            }
//
//            $commits[] = [
//                'hash' => $parts[0],
//                'author_name' => $parts[1],
//                'author_email' => $parts[2],
//                'date' => $parts[3],
//                'message' => $parts[4]
//            ];
//        }
//
//        return $commits;
//    }
    public function getCommitHistory(int $limit = 10): array
    {
        $format = '%H|%an|%ae|%ad|%s'; // hash|author name|author email|date|subject
        $output = $this->executeGitCommand(GitOperation::LOG, [
            "--pretty=format:$format",
            "--date=iso",
            "--max-count=$limit"
        ]);

        $commits = [];
        foreach (explode("\n", $output) as $line) {
            if (empty($line)) continue;
            $parts = explode('|', $line, 5);
            $commits[] = [
                'hash' => $parts[0],
                'author_name' => $parts[1],
                'author_email' => $parts[2],
                'date' => $parts[3],
                'subject' => $parts[4] ?? '',
            ];
        }

        return $commits;
    }
}