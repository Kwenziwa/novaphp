<?php

declare(strict_types=1);

use App\Controllers\{DocumentationController, HomeController};
use Nova\Routing\Router;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/docs', [DocumentationController::class, 'index']);
    $router->get('/docs/{page}', [DocumentationController::class, 'show']);
};
