<?php

declare(strict_types=1);

namespace App\Providers;

use Nova\Core\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->container->singleton(\Nova\Session\Session::class,
            fn () => new \Nova\Session\Session(secure: (bool) $this->app->config->get('session.secure', false)));
        $this->app->container->singleton(\Nova\View\View::class,
            fn () => new \Nova\View\View($this->app->basePath . '/resources/views'));
    }
}
