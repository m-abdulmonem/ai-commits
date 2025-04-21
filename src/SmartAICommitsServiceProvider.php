<?php

namespace Mabdulmonem\AICommits;

use Illuminate\Support\ServiceProvider;
use Mabdulmonem\AICommits\Console\Commands\SmartCommitCommand;
use Mabdulmonem\AICommits\Core\Contracts\AIInterface;
use Mabdulmonem\AICommits\Core\Contracts\GitInterface;
use Mabdulmonem\AICommits\Core\Contracts\VCSInterface;
use Mabdulmonem\AICommits\Providers\AI\ClaudeService;
use Mabdulmonem\AICommits\Providers\AI\OpenAIService;
use Mabdulmonem\AICommits\Providers\AI\OpenRouterService;
use Mabdulmonem\AICommits\Providers\VCS\BitbucketService;
use Mabdulmonem\AICommits\Providers\VCS\GitHubService;
use Mabdulmonem\AICommits\Providers\VCS\GitLabService;
use Mabdulmonem\AICommits\Services\AIService;
use Mabdulmonem\AICommits\Services\CommitMessageGenerator;
use Mabdulmonem\AICommits\Services\GitService;
use Mabdulmonem\AICommits\Services\VCSService;

class SmartAICommitsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ai-commits.php',
            'ai-commits'
        );

        $this->registerAIServices();
        $this->registerVCSServices();
        $this->registerMainServices();
        $this->registerFacade();
        $this->registerCommands();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishConfig();
        $this->publishAssets();
    }

    /**
     * Register AI service providers.
     */
    protected function registerAIServices(): void
    {
        $this->app->singleton(OpenAIService::class, function () {
            return new OpenAIService(
                config('ai-commits.providers.openai.api_key'),
                config('ai-commits.providers.openai.base_url', 'https://api.openai.com/v1')
            );
        });

        $this->app->singleton(OpenRouterService::class, function () {
            return new OpenRouterService(
                config('ai-commits.providers.openrouter.api_key')
            );
        });

        $this->app->singleton(ClaudeService::class, function () {
            return new ClaudeService(
                config('ai-commits.providers.anthropic.api_key')
            );
        });
    }

    /**
     * Register VCS service providers.
     */
    protected function registerVCSServices(): void
    {
        $this->app->singleton(GitHubService::class, function () {
            return new GitHubService(
                config('ai-commits.providers.github.token'),
                config('ai-commits.providers.github.api_url', 'https://api.github.com')
            );
        });

        $this->app->singleton(GitLabService::class, function () {
            return new GitLabService(
                config('ai-commits.providers.gitlab.token'),
                config('ai-commits.providers.gitlab.api_url', 'https://gitlab.com/api/v4')
            );
        });

        $this->app->singleton(BitbucketService::class, function () {
            return new BitbucketService(
                config('ai-commits.providers.bitbucket.username'),
                config('ai-commits.providers.bitbucket.app_password')
            );
        });
    }

    /**
     * Register main application services.
     */
    protected function registerMainServices(): void
    {
        $this->app->singleton(AIInterface::class, AIService::class);
        $this->app->singleton(GitInterface::class, GitService::class);
        $this->app->singleton(VCSInterface::class, VCSService::class);

        $this->app->singleton(CommitMessageGenerator::class, function ($app) {
            return new CommitMessageGenerator(
                $app->make(AIInterface::class)
            );
        });
    }

    /**
     * Register the package facade.
     */
    protected function registerFacade(): void
    {
        $this->app->singleton('smart-ai-commits', function ($app) {
            return new class ($app) {
                public function __construct($app)
                {
                    $this->generator = $app->make(CommitMessageGenerator::class);
                    $this->git = $app->make(GitInterface::class);
                    $this->vcs = $app->make(VCSInterface::class);
                    $this->ai = $app->make(AIInterface::class);
                }

                // Generator methods
                public function generateCommit(string $diff, ?string $model = null)
                {
                    return $this->generator->generateForHunk(
                        new DiffHunk($diff),
                        config('ai-commits.default_ai_provider'),
                        $model
                    );
                }

                public function generateFromHunks(array $hunks, ?string $model = null)
                {
                    return $this->generator->generateForHunks(
                        $hunks,
                        config('ai-commits.default_ai_provider'),
                        $model
                    );
                }

                // Git methods
                public function commit(string $message)
                {
                    return $this->git->commit($message);
                }

                // VCS methods
                public function createRepository(string $name, array $options = [])
                {
                    return $this->vcs->createRepository(
                        config('ai-commits.default_vcs_provider'),
                        $name,
                        $options
                    );
                }
            };
        });
    }

    /**
     * Register package commands.
     */
    protected function registerCommands(): void
    {
        $this->commands([
            SmartCommitCommand::class,
        ]);
    }

    /**
     * Publish configuration file.
     */
    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/ai-commits.php' => config_path('ai-commits.php'),
        ], 'ai-commits-config');
    }

    /**
     * Publish package assets.
     */
    protected function publishAssets(): void
    {
        $this->publishes([
            __DIR__ . '/../resources/stubs/commit.stub' => base_path('stubs/ai-commit.stub'),
        ], 'ai-commits-stubs');
    }
}