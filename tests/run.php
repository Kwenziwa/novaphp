<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/bootstrap/app.php';
use Nova\Http\Request;

$cases = [
    ['GET', '/', 200, 'NovaPHP'],
    ['GET', '/hello/Ada', 200, 'Hello, Ada'],
    ['GET', '/api/health', 200, '"status":"ok"'],
    ['POST', '/api/echo', 201, '"received"'],
    ['GET', '/missing', 404, '404 Not Found'],
    ['POST', '/api/health', 405, '405 Method Not Allowed'],
];
foreach ($cases as [$method, $path, $expectedStatus, $expectedText]) {
    $response = $app->handle(new Request($method, $path));
    if ($response->status !== $expectedStatus || !str_contains($response->body, $expectedText)) {
        fwrite(STDERR, "FAIL {$method} {$path}\n");
        exit(1);
    }
    echo "PASS {$method} {$path}\n";
}
require __DIR__ . "/phase2.php";
require __DIR__ . "/phase3.php";
require __DIR__ . "/phase4.php";
require __DIR__ . "/schema.php";
require __DIR__ . "/docs.php";
require __DIR__ . "/records.php";
require __DIR__ . "/auth.php";
echo "All tests passed.\n";
