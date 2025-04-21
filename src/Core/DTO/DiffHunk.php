<?php

namespace Mabdulmonem\AICommits\Core\DTO;

use Mabdulmonem\AICommits\Core\Exceptions\InvalidDiffException;

final class DiffHunk
{
    /**
     * @param string $filePath The file path being modified
     * @param string $oldPath Original file path (for renames)
     * @param int $oldStart Line number in original file
     * @param int $oldLines Number of lines in original file
     * @param int $newStart Line number in new file
     * @param int $newLines Number of lines in new file
     * @param string $header The hunk header from the diff
     * @param string $content The actual diff content
     * @param string $language Detected file language
     * @param float $complexity Calculated complexity score (0-1)
     */
    public function __construct(
        public readonly string $filePath,
        public readonly string $oldPath,
        public readonly int $oldStart,
        public readonly int $oldLines,
        public readonly int $newStart,
        public readonly int $newLines,
        public readonly string $header,
        public readonly string $content,
        public readonly string $language = 'text',
        public readonly float $complexity = 0.0
    ) {
        $this->validate();
    }

    /**
     * Create from raw git diff hunk string
     *
     * @throws InvalidDiffException
     */
    public static function fromDiffString(string $diff): self
    {
        $lines = explode("\n", $diff);
        $header = array_shift($lines);

        if (!preg_match('/^diff --git a\/(.+?) b\/(.+?)$/', $header, $matches)) {
            throw new InvalidDiffException("Invalid diff header format");
        }

        $oldPath = $matches[1];
        $filePath = $matches[2];

        $hunkHeader = array_shift($lines);
        if (!preg_match('/^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@/', $hunkHeader, $hunkMatches)) {
            throw new InvalidDiffException("Invalid hunk header format");
        }

        return new self(
            filePath: $filePath,
            oldPath: $oldPath,
            oldStart: (int) $hunkMatches[1],
            oldLines: isset($hunkMatches[2]) ? (int) $hunkMatches[2] : 1,
            newStart: (int) $hunkMatches[3],
            newLines: isset($hunkMatches[4]) ? (int) $hunkMatches[4] : 1,
            header: $hunkHeader,
            content: implode("\n", $lines),
            language: self::detectLanguage($filePath),
            complexity: self::calculateComplexity($lines)
        );
    }

    /**
     * Get the full diff including headers
     */
    public function getFullDiff(): string
    {
        return "diff --git a/{$this->oldPath} b/{$this->filePath}\n"
            . "{$this->header}\n"
            . $this->content;
    }

    /**
     * Get only the changed lines (without +/- markers)
     */
    public function getChangedContent(): string
    {
        $lines = [];
        foreach (explode("\n", $this->content) as $line) {
            if (str_starts_with($line, '+') && !str_starts_with($line, '++')) {
                $lines[] = substr($line, 1);
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Check if this hunk represents a file rename
     */
    public function isRename(): bool
    {
        return $this->oldPath !== $this->filePath;
    }

    /**
     * Check if this hunk represents a new file
     */
    public function isNewFile(): bool
    {
        return $this->oldStart === 0 && $this->oldLines === 0;
    }

    /**
     * Check if this hunk represents a deleted file
     */
    public function isDeletedFile(): bool
    {
        return $this->newStart === 0 && $this->newLines === 0;
    }

    /**
     * Validate the diff hunk data
     *
     * @throws InvalidDiffException
     */
    private function validate(): void
    {
        if (empty($this->filePath)) {
            throw new InvalidDiffException("File path cannot be empty");
        }

        if ($this->oldStart < 0 || $this->newStart < 0) {
            throw new InvalidDiffException("Line numbers cannot be negative");
        }

        if (empty($this->content)) {
            throw new InvalidDiffException("Diff content cannot be empty");
        }
    }

    /**
     * Detect file language from extension
     */
    private static function detectLanguage(string $filePath): string
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        return match ($extension) {
            'php' => 'php',
            'js', 'ts' => 'javascript',
            'py' => 'python',
            'java' => 'java',
            'go' => 'go',
            'rb' => 'ruby',
            'rs' => 'rust',
            'cpp', 'c', 'h' => 'c++',
            'swift' => 'swift',
            'kt', 'kts' => 'kotlin',
            default => 'text'
        };
    }

    /**
     * Calculate diff complexity score (0-1)
     */
    private static function calculateComplexity(array $lines): float
    {
        $added = 0;
        $complex = 0;

        foreach ($lines as $line) {
            if (!str_starts_with($line, '+')) {
                continue;
            }

            $added++;
            $cleanLine = trim(substr($line, 1));

            // Simple complexity heuristics
            if (
                str_contains($cleanLine, 'if') ||
                str_contains($cleanLine, 'for') ||
                str_contains($cleanLine, 'while') ||
                str_contains($cleanLine, '=>') ||
                str_contains($cleanLine, 'return') ||
                preg_match('/[{}();]/', $cleanLine)
            ) {
                $complex++;
            }
        }

        if ($added === 0) {
            return 0.0;
        }

        return min(1.0, $complex / $added);
    }
}