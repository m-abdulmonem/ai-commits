<?php

namespace Mabdulmonem\AICommits\Core\Exceptions;

class InvalidRepositoryDataException extends \RuntimeException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Invalid repository data: $message", $code, $previous);
    }
}