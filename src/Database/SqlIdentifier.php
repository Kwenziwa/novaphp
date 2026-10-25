<?php

declare(strict_types=1);

namespace Nova\Database;

use InvalidArgumentException;

final class SqlIdentifier
{
    public static function quote(Connection $database, string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/D', $name)) {
            throw new InvalidArgumentException('SQL identifiers must be simple names of at most 63 characters.');
        }
        return $database->driver() === 'mysql' ? '`' . $name . '`' : '"' . $name . '"';
    }
}
