<?php

declare(strict_types=1);

namespace Nova\Auth;

final class RateLimitException extends \RuntimeException
{
    public function __construct(public readonly int $retryAfter = 900)
    {
        parent::__construct('Too many attempts. Please try again later.');
    }
}
