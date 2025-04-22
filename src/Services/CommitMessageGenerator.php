<?php

namespace Mabdulmonem\AICommits\Services;

use Mabdulmonem\AICommits\Core\Contracts\AIInterface;
use Mabdulmonem\AICommits\Core\DTO\CommitMessage;
use Mabdulmonem\AICommits\Core\DTO\DiffHunk;
use Mabdulmonem\AICommits\Core\Enums\AIProvider;
use Mabdulmonem\AICommits\Core\Enums\CommitType;
use Mabdulmonem\AICommits\Core\Exceptions\AIException;

class CommitMessageGenerator
{
    public function __construct(
        private readonly AIInterface $aiService
    ) {
    }

    /**
     * Generate a commit message for a diff hunk
     *
     * @param DiffHunk $hunk
     * @param AIProvider $provider
     * @param string|null $model
     * @return CommitMessage
     * @throws AIException
     */
    public function generateForHunk(
        DiffHunk $hunk,
        AIProvider $provider,
        ?string $model = null
    ): CommitMessage {
        $message = $this->aiService->generateCommitMessage(
            $hunk->getFullDiff(),
            $provider,
            $model
        );

        // Ensure the message follows our standards
        return $this->validateAndFormatMessage($message, $hunk);
    }
    /**
     * Generate a commit message for all changes (hunks and new files)
     *
     * @param array<DiffHunk> $hunks
     * @param array<string> $untrackedFiles
     * @param AIProvider $provider
     * @param string|null $model
     * @return CommitMessage
     * @throws AIException
     */
    public function generateForAllChanges(
        array $hunks,
        array $untrackedFiles,
        AIProvider $provider,
        ?string $model = null
    ): CommitMessage {
        // Prepare the diff content
        $diffContent = "";

        // Add information about new files if any
        if (!empty($untrackedFiles)) {
            $diffContent .= "New files added:\n" . implode("\n", $untrackedFiles) . "\n\n";
        }

        // Add all diffs
        $diffContent .= implode("\n\n", array_map(
            fn(DiffHunk $h) => $h->getFullDiff(),
            $hunks
        ));

        // Create a more descriptive prompt for the AI
        $prompt = "Generate a comprehensive commit message that covers all these changes:\n\n" .
            "The changes include " . count($untrackedFiles) . " new files and " .
            count($hunks) . " modified files.\n\n" .
            "Here are the details:\n\n" . $diffContent . "\n\n" .
            "Provide a clear, concise commit message that follows Conventional Commits standards " .
            "and summarizes all these changes appropriately. Respond ONLY with the commit message.";

        $message = $this->aiService->generateCommitMessage(
            $prompt,
            $provider,
            $model
        );

        // Validate and format the message
        return $this->validateAndFormatMessage($message);
    }

    /**
     * Generate a commit message for multiple hunks
     *
     * @param array<DiffHunk> $hunks
     * @param AIProvider $provider
     * @param string|null $model
     * @return CommitMessage
     * @throws AIException
     */
    public function generateForHunks(
        array $hunks,
        AIProvider $provider,
        ?string $model = null
    ): CommitMessage {
        $combinedDiff = implode("\n\n", array_map(
            fn(DiffHunk $h) => $h->getFullDiff(),
            $hunks
        ));

        $message = $this->aiService->generateCommitMessage(
            $combinedDiff,
            $provider,
            $model
        );

        return $this->validateAndFormatMessage($message, $hunks[0] ?? null);
    }

    /**
     * Generate a commit message for new files
     *
     * @param array<string> $filePaths
     * @param AIProvider $provider
     * @param string|null $model
     * @return CommitMessage
     * @throws AIException
     */
    public function generateForNewFiles(
        array $filePaths,
        AIProvider $provider,
        ?string $model = null
    ): CommitMessage {
        $fileList = implode("\n", $filePaths);
        $prompt = "Generate a commit message for these new files:\n\n{$fileList}";

        $message = $this->aiService->generateCommitMessage(
            $prompt,
            $provider,
            $model
        );

        // Default to "chore" for new files if type isn't detected
        try {
            return CommitMessage::fromString($message->toString());
        } catch (\InvalidArgumentException) {
            return new CommitMessage(
                CommitType::CHORE,
                "add new files: " . basename($filePaths[0]) . (count($filePaths) > 1 ? " (+" . (count($filePaths) - 1) . " more)" : "")
            );
        }
    }

    /**
     * Validate and format the AI-generated message
     */
    private function validateAndFormatMessage(
        CommitMessage $message,
        ?DiffHunk $hunk = null
    ): CommitMessage {
        $content = $message->toString();

        // Ensure the message isn't empty
        if (empty(trim($content))) {
            return new CommitMessage(
                CommitType::CHORE,
                $hunk
                ? "update " . basename($hunk->filePath)
                : "update files"
            );
        }

        // Ensure the message isn't too long
        $lines = explode("\n", $content);
        if (strlen($lines[0]) > 72) {
            $lines[0] = substr($lines[0], 0, 69) . '...';
            $content = implode("\n", $lines);
        }

        try {
            return CommitMessage::fromString($content);
        } catch (\InvalidArgumentException) {
            // Fallback if parsing fails
            return new CommitMessage(
                CommitType::CHORE,
                $content
            );
        }
    }

    /**
     * Suggest improvements for a commit message
     */
    public function suggestImprovement(
        string $currentMessage,
        AIProvider $provider,
        ?string $model = null
    ): CommitMessage {
        $prompt = "Improve this commit message to follow Conventional Commits:\n\n{$currentMessage}\n\n" .
            "Respond ONLY with the improved message.";

        try {
            $improved = $this->aiService->generateCommitMessage(
                $prompt,
                $provider,
                $model
            );
            return CommitMessage::fromString($improved->toString());
        } catch (\Exception) {
            return CommitMessage::fromString($currentMessage);
        }
    }
}
