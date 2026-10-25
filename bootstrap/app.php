<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

$app = new Nova\Core\Application(dirname(__DIR__));
$app->register(Nova\Core\CoreServiceProvider::class);
foreach ($app->config->get('app.providers', []) as $provider) { $app->register($provider); }
$app->boot();
(require dirname(__DIR__) . '/routes/web.php')($app->router);
(require dirname(__DIR__) . '/routes/api.php')($app->router);
return $app;
