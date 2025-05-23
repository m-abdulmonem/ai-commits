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
        return is_dir('.git') and $this->runCommand('git rev-parse --git-dir', false) != null;
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
        try {
            $patchContent = $this->buildCompletePatch($hunk);
            $this->applyPatch($patchContent, $hunk->filePath);
        } catch (GitException $e) {
            $this->fallbackStage($hunk);
        }
    }

    private function buildCompletePatch(DiffHunk $hunk): string
    {
        $filePath = $hunk->filePath;
        $diffHeader = "diff --git a/{$filePath} b/{$filePath}\n" .
            "index 0000000..1111111 100644\n";

        return $diffHeader . $hunk->getValidPatch();
    }

    private function applyPatch(string $patchContent, string $filePath): void
    {
        // Save patch for debugging
        $patchFile = storage_path('last_patch_' . md5($filePath) . '.diff');
        file_put_contents($patchFile, $patchContent);

        $process = new Process([
            'git',
            'apply',
            '--cached',
            '--unidiff-zero',
            '--whitespace=nowarn',
            $patchFile
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new GitException(
                GitOperation::APPLY,
                "Patch application failed: " . $process->getErrorOutput(),
                $process->getExitCode()
            );
        }
    }

    private function fallbackStage(DiffHunk $hunk): void
    {
        $filePath = $hunk->filePath;

        // Method 1: Use git add -p with automatic response
        $this->tryGitAddPatch($filePath);

        // If still failing, use the nuclear option
        $this->stageEntireFileAndReset($filePath);
    }

    private function tryGitAddPatch(string $filePath): void
    {
        $process = new Process([
            'git',
            'add',
            '-p',
            $filePath
        ]);

        // Automatic responses: 'y' for our hunk, 'n' for others
        $process->setInput("y\nq\n");
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new GitException(
                GitOperation::APPLY,
                "Interactive staging failed: " . $process->getErrorOutput(),
                $process->getExitCode()
            );
        }
    }

    private function stageEntireFileAndReset(string $filePath): void
    {
        // Stage entire file
        $this->executeGitCommand(GitOperation::ADD, [$filePath]);

        // Then unstage everything
        $this->executeGitCommand(GitOperation::RESET, ['-p', $filePath]);

        // Finally stage just our changes
        $this->tryGitAddPatch($filePath);
    }
    // public function stageHunk(DiffHunk $hunk): void
    // {
    //     $patchFile = tempnam(sys_get_temp_dir(), 'hunk_');
    //     file_put_contents($patchFile, $hunk->getFullDiff());

    //     try {
    //         $this->executeGitCommand(GitOperation::APPLY, ['--cached', '--whitespace=fix', $patchFile]);
    //     } finally {
    //         unlink($patchFile);
    //     }
    // }

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
        $this->executeGitCommand(GitOperation::COMMIT, ['-m', str_replace('"', '', escapeshellarg($message))]);
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
            // Try to fetch the upstream branch for the given branch
            $result = $this->executeGitCommand(GitOperation::REV_PARSE, ["--abbrev-ref", "--symbolic-full-name", "{$branch}@{u}"]);
            return !empty($result); // If the result is not empty, an upstream is set
        } catch (GitException) {
            return false; // No upstream is set for the branch
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
    // private function executeGitCommand(GitOperation $operation, array $args = []): string
    // {
    //     $command = array_merge(['git', $operation->value], $args);
    //     $process = new Process($command);

    //     try {
    //         $process->mustRun();
    //         return trim($process->getOutput());
    //     } catch (ProcessFailedException $e) {
    //         throw GitException::commandFailed(
    //             $operation,
    //             $process->getCommandLine(),
    //             $process->getExitCode(),
    //             $process->getErrorOutput()
    //         );
    //     }
    // }
    private function executeGitCommand(GitOperation $operation, array $args = []): string
    {
        $command = array_merge(['git', $operation->value], $args);
        $process = new Process($command);
        $process->setTimeout(60); // Set a timeout (seconds)
        try {
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            return trim($process->getOutput());
        } catch (ProcessFailedException $e) {
            throw GitException::commandFailed(
                $operation,
                $process->getCommandLine(),
                $process->getExitCode(),
                $process->getErrorOutput() ?: $process->getOutput()
            );
        } catch (\Throwable $e) {
            // Handle other unexpected issues (e.g., process not starting)
            throw new \RuntimeException("Unexpected error during Git command: {$e->getMessage()}");
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
        $diff = str_replace('\n', "\n", $diff);
        $rawHunks = preg_split('/^diff --git/m', $diff, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($rawHunks as $rawHunk) {
            try {
                $hunks[] = DiffHunk::fromDiffString("diff --git " . ltrim($rawHunk));
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
