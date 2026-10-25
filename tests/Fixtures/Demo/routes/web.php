<?php

declare(strict_types=1);

use Nova\Routing\Router;

return static function (Router $router): void {
    $router->group('', [\Nova\Http\Middleware\StartSession::class, \Nova\Http\Middleware\VerifyCsrfToken::class],
        static function (Router $router): void {
            $router->get('/csrf-token', [\Tests\Fixtures\Demo\Controllers\AuthController::class, 'csrf']);
            $router->get('/login', [\Tests\Fixtures\Demo\Controllers\AuthController::class, 'loginForm']);
            $router->post('/login', [\Tests\Fixtures\Demo\Controllers\AuthController::class, 'login']);
            $router->get('/register', [\Tests\Fixtures\Demo\Controllers\AuthController::class, 'registerForm']);
            $router->post('/register', [\Tests\Fixtures\Demo\Controllers\AuthController::class, 'register']);
            $router->group('', [\Nova\Http\Middleware\RequireAuth::class], static function (Router $router): void {
                $router->get('/notes', [\Tests\Fixtures\Demo\Controllers\NoteController::class, 'index']);
                $router->post('/notes', [\Tests\Fixtures\Demo\Controllers\NoteController::class, 'store']);
                $router->get('/notes/{id}/edit', [\Tests\Fixtures\Demo\Controllers\NoteController::class, 'edit']);
                $router->post('/notes/{id}', [\Tests\Fixtures\Demo\Controllers\NoteController::class, 'update']);
                $router->post('/notes/{id}/delete', [\Tests\Fixtures\Demo\Controllers\NoteController::class, 'delete']);
                $router->post('/logout', [\Tests\Fixtures\Demo\Controllers\AuthController::class, 'logout']);
                $router->get('/account', [\Tests\Fixtures\Demo\Controllers\AuthController::class, 'account']);
            });
            $router->get('/contact', [\Tests\Fixtures\Demo\Controllers\ContactController::class, 'show']);
            $router->post('/contact', [\Tests\Fixtures\Demo\Controllers\ContactController::class, 'store']);
        });
    $router->get('/', static fn () => 'NovaPHP fixture');
    $router->get('/hello/{name}', static fn (string $name): string =>
        '<h1>Hello, ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '!</h1>');
};
