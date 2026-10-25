<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$request = Nova\Http\Request::fromGlobals();
$app->handle($request)->send($request->method === 'HEAD');
