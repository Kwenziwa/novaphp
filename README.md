# NovaPHP — Phase 2

An independent educational PHP framework with no runtime dependencies beyond PHP 8.2+.

🚧 NovaPHP is in early development and is not recommended for production use.

## Run

```bash
php nova serve
```

Visit http://127.0.0.1:8000. Composer is optional: a built-in PSR-4 fallback loads `Nova\` from `src/` and `App\` from `app/`. If you use Composer, run `composer dump-autoload`.

```bash
php nova route:list
php nova make:controller PostController
php nova config:show app.name
php tests/run.php
curl http://127.0.0.1:8000/api/health
curl -X POST http://127.0.0.1:8000/api/echo \
  -H 'Content-Type: application/json' -d '{"message":"Hello"}'
```

`make:controller` creates `app/Controllers/PostController.php` with an `index()` method. It accepts a simple PascalCase name ending in `Controller`, rejects paths, and refuses to overwrite an existing file. Add a route yourself:

```php
$router->get('/posts', [App\Controllers\PostController::class, 'index']);
```

## Phase 2: dependency container

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

## Service providers and bootstrap

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

## Configuration and environment

PHP files in `config/` must return arrays. Each filename becomes its top-level key. The repository supports dot notation, defaults, mutation, and complete inspection:

```php
$app->config->get('app.debug', false);
$app->config->set('services.mail.host', 'localhost');
$app->config->all();
```

Copy `.env.example` to `.env` if needed. `.env` is loaded before config files and is ignored by Git. Existing process environment, `$_ENV`, and `$_SERVER` values take precedence. The loader supports blank lines, comments, optional `export`, empty values, and single/double quoted literal values. Whitespace followed by `#` starts a comment in an unquoted value. Quotes do not support escape sequences. There is no variable interpolation, command substitution, or code evaluation. Malformed entries fail with a line number before any values from the file are applied. Missing `.env` files are allowed.

Config files read environment values with `getenv()`. Keep parsing explicit: `app.debug` uses `FILTER_VALIDATE_BOOLEAN`, so `APP_DEBUG=false` stays false.

```bash
php nova config:show             # all config
php nova config:show app.debug   # one dot-notation key; missing keys print null
```

Config inspection prints the requested values without redaction; run it locally and avoid sharing output containing credentials.

## Preserved Phase 1 features

- Optional Composer autoloading and built-in namespace fallback
- Front controller and PHP development server router
- Query, form, and JSON request parsing; HTML, text, and JSON responses
- GET/POST/PUT/PATCH/DELETE routes with named parameters
- Class controllers, closures, and automatic `Request` injection
- 404, 405, and exception handling with a safe production error response
- `serve`, `route:list`, and standalone tests

## Verification and limits

`php tests/run.php` covers the original HTTP routes plus container lifetimes, auto-wiring, circular failures and recovery, config access, environment parsing and precedence, provider ordering, controller injection, and CLI generation/overwrite safeguards. No test framework is required.

Middleware, authentication, CSRF protection, persistence, a template engine, and a hardened security model remain future work. The static welcome page is loaded as text, not interpreted as a PHP template. Enable `APP_DEBUG=true` only on a private development machine. Deploy with debug disabled and a proper web server document root pointing at `public/`.
