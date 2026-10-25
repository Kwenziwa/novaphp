<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/assets/')) { return false; }
if (str_starts_with($path, '/docs')) { require dirname(__DIR__, 2) . '/public/index.php'; }
else { require __DIR__ . '/Demo/public/index.php'; }
