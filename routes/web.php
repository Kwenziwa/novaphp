<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use Nova\Routing\Router;

return static function (Router $router): void {
    $router->group('', [\Nova\Http\Middleware\StartSession::class, \Nova\Http\Middleware\VerifyCsrfToken::class],
        static function (Router $router): void {
            $router->get('/notes', [\App\Controllers\NoteController::class, 'index']);
            $router->post('/notes', [\App\Controllers\NoteController::class, 'store']);
            $router->get('/notes/{id}/edit', [\App\Controllers\NoteController::class, 'edit']);
            $router->post('/notes/{id}', [\App\Controllers\NoteController::class, 'update']);
            $router->post('/notes/{id}/delete', [\App\Controllers\NoteController::class, 'delete']);
            $router->get('/contact', [\App\Controllers\ContactController::class, 'show']);
            $router->post('/contact', [\App\Controllers\ContactController::class, 'store']);
        });
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/hello/{name}', static fn (string $name): string =>
        '<h1>Hello, ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '!</h1>');
};
