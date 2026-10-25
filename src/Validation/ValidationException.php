<?php

declare(strict_types=1);

namespace Nova\Validation;

final class ValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Validation failed.');
    }
}
