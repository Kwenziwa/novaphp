<?php

declare(strict_types=1);

use Nova\Routing\Router;

return static function (Router $router): void {
    $router->get('/api/health', static fn (): array => ['status' => 'ok']);
};
