<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use Nova\Routing\Router;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/hello/{name}', static fn (string $name): string =>
        '<h1>Hello, ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '!</h1>');
};
