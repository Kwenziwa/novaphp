<?php

// Router script for PHP's built-in development server only.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$root = realpath(__DIR__);
$file = realpath(__DIR__ . $path);
if ($path !== '/' && $file && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
require __DIR__ . '/index.php';
