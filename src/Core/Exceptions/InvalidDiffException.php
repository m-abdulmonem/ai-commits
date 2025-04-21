<?php

namespace Mabdulmonem\AICommits\Core\Exceptions;

class InvalidDiffException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("Invalid diff hunk: $message");
    }
}