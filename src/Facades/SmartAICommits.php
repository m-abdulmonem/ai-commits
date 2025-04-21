<?php

namespace Mabdulmonem\AICommits\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Mabdulmonem\AICommits\Core\DTO\CommitMessage generateCommit(string $diff, ?string $model = null)
 * @method static \Mabdulmonem\AICommits\Core\DTO\CommitMessage generateFromHunks(array $hunks, ?string $model = null)
 * @method static \Mabdulmonem\AICommits\Core\DTO\CommitMessage generateForNewFiles(array $filePaths, ?string $model = null)
 * @method static \Mabdulmonem\AICommits\Core\DTO\CommitMessage suggestImprovement(string $currentMessage, ?string $model = null)
 * @method static array getAvailableModels()
 * @method static bool testConnection()
 * @method static void initRepository()
 * @method static void commit(string $message)
 * @method static void push(string $branch, bool $setUpstream = false)
 * @method static void addRemote(string $name, string $url)
 * @method static array getRepositories(int $perPage = 30, int $page = 1)
 * @method static \Mabdulmonem\AICommits\Core\DTO\RepositoryData createRepository(string $name, array $options = [])
 * @method static \Mabdulmonem\AICommits\Core\DTO\RepositoryData getRepository(string $identifier)
 * @method static array createPullRequest(string $repositoryId, array $requestData)
 * 
 * @see \Mabdulmonem\AICommits\Services\CommitMessageGenerator
 * @see \Mabdulmonem\AICommits\Services\GitService
 * @see \Mabdulmonem\AICommits\Services\VCSService
 */
class SmartAICommits extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'ai-commits';
    }

    /**
     * Register the facade and its services
     */
    public static function shouldProxyTo($class)
    {
        return true;
    }
}