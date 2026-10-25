<?php

declare(strict_types=1);

namespace Nova\Core;


final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->container->bind(\Nova\Auth\UserProvider::class, function ($container) {
            $provider = $this->app->config->get('auth.provider');
            if (!is_string($provider) || !is_subclass_of($provider, \Nova\Auth\UserProvider::class)) {
                throw new \RuntimeException('Configure auth.provider with a UserProvider implementation before using authentication.');
            }
            return $container->make($provider);
        });
        $this->app->container->singleton(\Nova\Database\Connection::class,
            fn () => new \Nova\Database\Connection($this->app->config->get('database', [])));
        $this->app->container->singleton(\Nova\Database\Migrator::class,
            fn ($container) => new \Nova\Database\Migrator($container->make(\Nova\Database\Connection::class),
                $this->app->basePath . '/database/migrations'));
        $this->app->container->singleton(\Nova\Session\Session::class,
            fn () => new \Nova\Session\Session(secure: (bool) $this->app->config->get('session.secure', false)));
        $this->app->container->singleton(\Nova\View\View::class,
            fn () => new \Nova\View\View($this->app->basePath . '/resources/views'));
    }
}
