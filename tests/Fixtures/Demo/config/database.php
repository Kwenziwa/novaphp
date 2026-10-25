<?php

declare(strict_types=1);

$driver = getenv('DB_CONNECTION') ?: 'sqlite';
$database = getenv('DB_DATABASE');
if ($driver === 'sqlite' && $database !== false && $database !== '' && $database !== ':memory:' && !str_starts_with($database, '/')) {
    $database = dirname(__DIR__) . '/' . $database;
}
return [
    'driver' => $driver,
    'database' => $database !== false && $database !== '' ? $database : ($driver === 'sqlite' ? dirname(__DIR__) . '/storage/database.sqlite' : 'nova'),
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'username' => getenv('DB_USERNAME') !== false ? getenv('DB_USERNAME') : null,
    'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : null,
];
