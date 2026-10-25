<?php
$app = require dirname(__DIR__) . '/bootstrap.php';
$request = Nova\Http\Request::fromGlobals();
$app->handle($request)->send($request->method === 'HEAD');
