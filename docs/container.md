# Container and providers

# Phase 2: dependency container

The application exposes `$app->container`. Bind interfaces to classes or closure factories, share a singleton, or register an existing instance:

```php
$container = $app->container;
$container->bind(Mailer::class, SmtpMailer::class);
$container->singleton(Cache::class, fn ($container) => new MemoryCache());
$container->instance('app.label', 'NovaPHP');
$mailer = $container->make(Mailer::class);
```

Unbound concrete classes are resolved automatically through their constructors. Class and interface dependencies are recursively resolved; scalar parameters need a default or an explicit factory. Variadic constructor parameters receive no arguments. Union/intersection constructor parameters require a default or factory. Unbound interfaces, non-instantiable classes, and required unresolved parameters produce descriptive errors. Circular dependencies report their resolution chain. Failed resolutions clear the resolution stack so later resolutions can succeed.

Bindings are transient; singletons resolve once per container. Rebinding clears an existing cached instance. `instance()` can hold any value, including `null`. The application, container, router, and config repository are registered as core instances.

Controllers are constructed through the container. Handler arguments resolve in this order: the current `Request`, a named route parameter, a default value, then a class dependency. Existing closure handlers, routes, responses, and standalone `new Router()` usage continue to work.

# Service providers and bootstrap

`bootstrap/app.php` creates the application, registers every provider in `config/app.php`, boots providers, then loads the existing web and API routes. All configured providers register before any boot method runs. Registration and boot are idempotent; a provider registered after boot is registered and booted immediately.

Extend `Nova\Core\ServiceProvider`. Use `$this->app->container` in `register()` for bindings and `boot()` for setup that depends on other registered services. The starter `App\Providers\AppServiceProvider` is already configured:

```php
namespace App\Providers;

use Nova\Core\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // $this->app->container->singleton(Mailer::class, SmtpMailer::class);
    }

    public function boot(): void
    {
        // All configured service bindings are now available.
    }
}
```

When constructing `Application` directly, call `register()` and `boot()` explicitly if providers are needed; the standard bootstrap handles this for HTTP and CLI.
