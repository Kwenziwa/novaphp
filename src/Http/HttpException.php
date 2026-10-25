<?php

declare(strict_types=1);

namespace Nova\Http;

final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        if ($status < 400 || $status > 599) { throw new \InvalidArgumentException('HTTP error status must be between 400 and 599.'); }
        parent::__construct($message);
    }
}
