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
    // Optional: DatabaseUserProvider::class or your own UserProvider implementation.
    'provider' => null,
    'login_path' => '/login',
    'attempts_table' => 'auth_attempts',
    'storage' => ['table' => 'users', 'id' => 'id', 'name' => 'name', 'email' => 'email', 'password_hash' => 'password_hash', 'created_at' => 'created_at'],
    'idle_seconds' => $seconds('AUTH_IDLE_SECONDS', 1800),
    'absolute_seconds' => $seconds('AUTH_ABSOLUTE_SECONDS', 43200),
];
