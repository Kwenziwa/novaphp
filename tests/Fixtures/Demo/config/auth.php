<?php

declare(strict_types=1);

$seconds = static function (string $name, int $default): int {
    $value = getenv($name);
    if ($value === false || $value === '') { return $default; }
    $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($parsed === false) { throw new RuntimeException($name . ' must be a positive integer.'); }
    return $parsed;
};

return [
    'provider' => Nova\Auth\DatabaseUserProvider::class,
    'idle_seconds' => $seconds('AUTH_IDLE_SECONDS', 1800),
    'absolute_seconds' => $seconds('AUTH_ABSOLUTE_SECONDS', 43200),
];
