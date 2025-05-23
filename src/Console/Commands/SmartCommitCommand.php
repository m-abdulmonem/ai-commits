<?php

namespace Mabdulmonem\AICommits\Console\Commands;

use Illuminate\Console\Command;
use Mabdulmonem\AICommits\Core\Contracts\GitInterface;
use Mabdulmonem\AICommits\Core\Contracts\VCSInterface;
use Mabdulmonem\AICommits\Core\DTO\CommitMessage;
use Mabdulmonem\AICommits\Core\DTO\DiffHunk;
use Mabdulmonem\AICommits\Core\Enums\AIProvider;
use Mabdulmonem\AICommits\Core\Enums\VCSProvider;
use Mabdulmonem\AICommits\Core\Exceptions\GitException;
use Mabdulmonem\AICommits\Core\Exceptions\VCSException;
use Mabdulmonem\AICommits\Services\CommitMessageGenerator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SmartCommitCommand extends Command
{
    protected $name = 'commit';
    protected $description = 'Generate AI-powered commit messages based on your changes';

    public function __construct(
        private GitInterface $git,
        private CommitMessageGenerator $generator,
        private VCSInterface $vcs
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDefinition([
            new InputOption('push', 'p', InputOption::VALUE_NONE, 'Push changes after committing'),
            new InputOption('model', 'm', InputOption::VALUE_OPTIONAL, 'Specify AI model to use'),
            new InputOption('provider', 'pr', InputOption::VALUE_OPTIONAL, 'Specify AI provider (openai/openrouter/anthropic/local)', 'openrouter'),
            new InputOption('vcs', 'g', InputOption::VALUE_OPTIONAL, 'Specify VCS provider (github/gitlab/bitbucket)', 'github'),
            new InputOption('dry-run', 'd', InputOption::VALUE_NONE, 'Show what would happen without executing'),
            new InputOption('no-ai', 'na', InputOption::VALUE_NONE, 'Skip AI and use simple messages'),
            new InputOption('auto', 'y', InputOption::VALUE_NONE, 'Auto-accept AI-generated messages without confirmation'),
            new InputOption('all', 'a', InputOption::VALUE_NONE, 'Commit all changes together with a single message'),
        ]);
    }

    public function handle()
    {
        try {
            $this->ensureGitRepository();

            if ($this->option('no-ai')) {
                return $this->commitWithoutAI();
            }

            return $this->commitWithAI();
        } catch (GitException $e) {
            $this->error("Git Error: {$e->getMessage()}");
            return 1;
        } catch (VCSException $e) {
            $this->error("VCS Error: {$e->getMessage()}");
            return 1;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return 1;
        }
    }

    protected function ensureGitRepository(): void
    {
        if (!$this->git->isRepository()) {
            $this->initializeRepository();
        }
    }

    protected function initializeRepository(): void
    {
        $this->info('Initializing new Git repository...');
        $this->git->init();

        if ($this->confirm('Would you like to set up a remote repository?')) {
            $this->setupRemoteRepository();
        }
    }

    protected function setupRemoteRepository(): void
    {
        $provider = $this->resolveVCSProvider();

        $choice = $this->choice('How would you like to set up the remote?', [
            'create' => 'Create new repository',
            'connect' => 'Connect existing repository',
            'skip' => 'Skip for now'
        ], 'create');

        switch ($choice) {
            case 'create':
      $options = [];
                $repoName = $this->ask('Repository name', basename(getcwd()));

                if ($provider->value === VCSProvider::GITHUB->value) {
                    // 🔄 Get orgs from GitHub
                    $orgs = (new GitHubService)->listOrganizations();
                    $orgNames = collect($orgs)->pluck('login')->all();

                    // ➕ Add option for personal account
                    array_unshift($orgNames, 'Personal account');

                    // 🎯 Let user select
                    $selectedOrg = $this->choice('Select organization', $orgNames, 0);

                    if ($selectedOrg !== 'Personal account') {
                        $options['organization'] = $selectedOrg;
                    }

                    $description = $this->ask('Repository description', 'Created by AI Commits');
                    $private = $this->confirm('Is this repository private?', false);
                    $options['description'] = $description;
                    $options['private'] = $private;
                }

                $repo = $this->vcs->createRepository($provider, $repoName, $options);
                $this->git->addRemote('origin', $repo->cloneUrl);
                $this->info("Created repository: {$repo->url}");

                break;

            case 'connect':
                $repoUrl = $this->ask('Enter repository URL');
                $this->git->addRemote('origin', $repoUrl);
                $this->info('Remote repository added');
                break;
        }
    }

    protected function commitWithAI(): int
    {
        $hunks = $this->git->getDiffHunks();
        $untrackedFiles = $this->git->getUntrackedFiles();

        $provider = $this->resolveAIProvider();
        $model = $this->option('model');


        if ($this->option('all')) {
            return $this->commitAllChanges($provider, $model);
            // Push if requested
            if ($this->option('push')) {
                $this->pushChanges();
            }
            return 0;
        }

        if (empty($hunks) && empty($untrackedFiles)) {
            $this->info('No changes detected.');
            return 0;
        }


        // Commit untracked files first
        if (!empty($untrackedFiles)) {
            $this->commitNewFiles($untrackedFiles, $provider, $model);
        }

        // Commit changes per hunk
        foreach ($hunks as $i => $hunk) {
            $this->commitHunk($hunk, $i + 1, $provider, $model);
        }

        // Push if requested
        if ($this->option('push')) {
            $this->pushChanges();
        }

        return 0;
    }

    protected function commitAllChanges(AIProvider $provider, ?string $model): int
    {
        $this->info("\n<fg=blue>Processing all changes together:</>");
        $this->git->stageAll();

        $hunks = $this->git->getDiffHunks();
        $untrackedFiles = $this->git->getUntrackedFiles();

        $message = $this->generator->generateForAllChanges($hunks, $untrackedFiles, $provider, $model);
        $this->displayCommitMessage($message);

        if (!$this->option('dry-run')) {
            $this->git->commit($message->toString());
            $this->info('✅ Committed all changes');
        }

        if ($this->option('push')) {
            $this->pushChanges();
        }

        return 0;
    }

    protected function commitNewFiles(array $files, AIProvider $provider, ?string $model): void
    {
        $this->info("\n<fg=blue>Staging new files:</>");
        $this->git->stageAll();

        $message = $this->generator->generateForNewFiles($files, $provider, $model);
        $this->displayCommitMessage($message);

        if (!$this->option('dry-run')) {
            $this->git->commit($message->toString());
            $this->info('✅ Committed new files');
        }
    }

    protected function commitHunk(DiffHunk $hunk, int $index, AIProvider $provider, ?string $model): void
    {
        $this->info("\n<fg=blue>Processing change set #{$index}:</> {$hunk->filePath}");

        $message = $this->generator->generateForHunk($hunk, $provider, $model);
        $this->displayCommitMessage($message);

        if ($this->option('dry-run')) {
            return;
        }

        $this->git->stageHunk($hunk);
        $this->git->commit($message->toString());
        $this->info("✅ Committed change set #{$index}");
    }

    protected function commitWithoutAI(): int
    {
        $message = $this->option('dry-run')
            ? 'chore: update files [dry run]'
            : $this->ask('Enter commit message', 'chore: update files');

        if ($this->option('dry-run')) {
            $this->info("[Dry run] Would commit with message: {$message}");
            return 0;
        }

        $this->git->stageAll();
        $this->git->commit($message);

        if ($this->option('push')) {
            $this->pushChanges();
        }

        return 0;
    }

    protected function pushChanges(): void
    {
        $branch = $this->git->getCurrentBranch();
        $this->info("\nPushing changes to {$branch}...");

        if ($this->option('dry-run')) {
            $this->info("[Dry run] Would push to {$branch}");
            return;
        }

        $this->git->push($branch, !$this->git->hasUpstream($branch));
        $this->info('✅ Pushed changes');
    }

    protected function displayCommitMessage(CommitMessage $message): void
    {
        $this->info("\n<fg=green>Commit message:</>");
        $this->line($message->toString());

        if ($this->option('dry-run') || $this->option('auto')) {
            return;
        }

        if (!$this->confirm('Use this message?', true)) {
            $customMessage = $this->ask('Enter your commit message');
            $message = CommitMessage::fromString($customMessage);
        }
    }

    protected function resolveAIProvider(): AIProvider
    {
        if ($provider = $this->option('provider')) {
            return AIProvider::tryFrom($provider) ?? AIProvider::default();
        }

        return AIProvider::from(
            $this->choice(
                'Select AI provider:',
                array_map(fn(AIProvider $p) => $p->value, AIProvider::cases()),
                AIProvider::default()->value
            )
        );
    }

    protected function resolveVCSProvider(): VCSProvider
    {
        if ($provider = $this->option('vcs')) {
            return VCSProvider::tryFrom($provider) ?? VCSProvider::default();
        }

        return VCSProvider::from(
            $this->choice(
                'Select VCS provider:',
                array_map(fn(VCSProvider $p) => $p->value, VCSProvider::cases()),
                VCSProvider::default()->value
            )
        );
    }
}
