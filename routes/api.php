<?php

declare(strict_types=1);

use Nova\Http\Request;
use Nova\Http\Response;
use Nova\Routing\Router;

return static function (Router $router): void {
    $router->get('/api/health', static fn (): array => ['status' => 'ok', 'framework' => 'NovaPHP']);
    $router->post('/api/echo', static fn (Request $request): Response =>
        Response::json(['received' => $request->all()], 201));
};
