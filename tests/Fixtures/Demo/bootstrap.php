<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
$app = new Nova\Core\Application(__DIR__);
$app->register(Nova\Core\CoreServiceProvider::class);
$app->boot();
(require __DIR__ . '/routes/web.php')($app->router);
(require __DIR__ . '/routes/api.php')($app->router);
return $app;
