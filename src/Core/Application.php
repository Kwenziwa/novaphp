<?php

declare(strict_types=1);

namespace Nova\Core;

use Nova\Http\Request;
use Nova\Http\Response;
use Nova\Routing\Router;
use Throwable;

final class Application
{
    public readonly Router $router;
    private array $config;

    public function __construct(public readonly string $basePath)
    {
        $this->router = new Router();
        $this->config = require $basePath . '/config/app.php';
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (Throwable $error) {
            error_log('[NovaPHP] ' . (string) $error);
            if ($this->config['debug']) {
                return Response::text('500 Internal Server Error' . "\n" . (string) $error, 500);
            }
            return Response::text('500 Internal Server Error', 500);
        }
    }
}
