<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use Nova\Routing\Router;

return static function (Router $router): void {
    $router->group('', [\Nova\Http\Middleware\StartSession::class, \Nova\Http\Middleware\VerifyCsrfToken::class],
        static function (Router $router): void {
            $router->get('/csrf-token', [\App\Controllers\AuthController::class, 'csrf']);
            $router->get('/login', [\App\Controllers\AuthController::class, 'loginForm']);
            $router->post('/login', [\App\Controllers\AuthController::class, 'login']);
            $router->get('/register', [\App\Controllers\AuthController::class, 'registerForm']);
            $router->post('/register', [\App\Controllers\AuthController::class, 'register']);
            $router->group('', [\Nova\Http\Middleware\RequireAuth::class], static function (Router $router): void {
                $router->get('/notes', [\App\Controllers\NoteController::class, 'index']);
                $router->post('/notes', [\App\Controllers\NoteController::class, 'store']);
                $router->get('/notes/{id}/edit', [\App\Controllers\NoteController::class, 'edit']);
                $router->post('/notes/{id}', [\App\Controllers\NoteController::class, 'update']);
                $router->post('/notes/{id}/delete', [\App\Controllers\NoteController::class, 'delete']);
                $router->post('/logout', [\App\Controllers\AuthController::class, 'logout']);
                $router->get('/account', [\App\Controllers\AuthController::class, 'account']);
            });
            $router->get('/contact', [\App\Controllers\ContactController::class, 'show']);
            $router->post('/contact', [\App\Controllers\ContactController::class, 'store']);
        });
    $router->get('/docs', [\App\Controllers\DocumentationController::class, 'index']);
    $router->get('/docs/{page}', [\App\Controllers\DocumentationController::class, 'show']);
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/hello/{name}', static fn (string $name): string =>
        '<h1>Hello, ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '!</h1>');
};
