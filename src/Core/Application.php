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
    public readonly Container $container;
    public readonly \Nova\Config\Repository $config;
    private array $providers = [];
    private bool $booted = false;

    public function __construct(public readonly string $basePath)
    {
        \Nova\Config\Env::load($basePath . '/.env');
        $items = [];
        foreach (glob($basePath . '/config/*.php') ?: [] as $file) {
            $value = require $file;
            if (!is_array($value)) { throw new \RuntimeException('Config files must return arrays.'); }
            $items[pathinfo($file, PATHINFO_FILENAME)] = $value;
        }
        $this->config = new \Nova\Config\Repository($items);
        $this->container = new Container();
        $this->router = new Router($this->container);
        foreach ([self::class => $this, Container::class => $this->container,
            \Nova\Config\Repository::class => $this->config, Router::class => $this->router] as $id => $instance) {
            $this->container->instance($id, $instance);
        }
    }

    public function register(string $provider): void
    {
        if (isset($this->providers[$provider])) { return; }
        if (!is_subclass_of($provider, ServiceProvider::class)) {
            throw new \RuntimeException('Providers must extend ServiceProvider.');
        }
        $instance = new $provider($this);
        $instance->register();
        $this->providers[$provider] = $instance;
        if ($this->booted) { $instance->boot(); }
    }

    public function boot(): void
    {
        if ($this->booted) { return; }
        $this->booted = true;
        foreach ($this->providers as $provider) { $provider->boot(); }
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (Throwable $error) {
            error_log('[NovaPHP] ' . (string) $error);
            if ($this->config->get('app.debug', false)) {
                return Response::text('500 Internal Server Error' . "\n" . (string) $error, 500);
            }
            return Response::text('500 Internal Server Error', 500);
        }
    }
}
