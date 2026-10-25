# NovaPHP — Phase 3

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

## Phase 3: middleware and web forms

Visit `/contact` for a working form with validation, CSRF protection, escaped old input, and one-request flash notifications. It does not send or save messages. The original routes and stateless JSON API remain available.

### Middleware and route groups

Implement `Nova\Http\Middleware::handle(Request $request, Closure $next): Response`, or use a closure with the same signature. Class middleware is resolved through the container. Call `$next($request)` to continue, wrap its response for after-request logic, or return a response immediately.

```php
$app->middleware([RequestLogger::class]); // Global: also wraps 404 and 405 responses.

$router->group('/account', [StartSession::class, VerifyCsrfToken::class],
    static function ($router): void {
        $router->get('/profile', [ProfileController::class, 'show']);
        $router->post('/profile', [ProfileController::class, 'update'], [AuditChanges::class]);
    });
```

Import application middleware and `Nova\Http\Middleware\StartSession` / `VerifyCsrfToken` as needed. Middleware enters in this order: global, outer group, inner group, route. Responses unwind in reverse order. Use `try/finally` for cleanup when downstream code may throw. Nested prefixes concatenate; group state is restored even if registration throws. The optional third argument on HTTP route methods is a middleware array; `add()` accepts it as the fourth argument. `routes()` now includes that middleware array as each route's fourth element. Existing three-element destructuring continues to work. Literal route characters such as `.` are matched literally.

### Sessions, flash messages, and CSRF

`AppServiceProvider` registers shared `Session` and `View` services. Apply `StartSession` before `VerifyCsrfToken` on browser form routes, as demonstrated in `routes/web.php`. Session access outside session middleware fails explicitly. Requests without this middleware do not start a session.

```php
$session->put('theme', 'dark');
$session->get('theme', 'light');
$session->forget('theme');
$session->flash('notice', 'Saved');
$session->getFlash('notice');
$session->regenerate(); // Rotate session ID and invalidate the CSRF token.
```

Flash values remain available during the current request and the next session-enabled request, then expire. `StartSession` persists and closes the native PHP session in a `finally` block. Native sessions use strict mode, cookies only, HttpOnly, SameSite=Lax, a session cookie lifetime, and `/` as the cookie path. Set `SESSION_SECURE=true` on HTTPS deployments; false allows the local HTTP demo. TLS terminated at a proxy requires this explicit setting; forwarded headers are not automatically trusted. PHP's configured session storage must be writable. Do not start PHP sessions independently alongside NovaPHP session middleware. `new Session(native: false)` provides isolated in-memory storage for tests.

Use `$session->token()` for a random 32-byte token, rendered as hex in a hidden `_token` form field. CSRF middleware checks a body field or `X-CSRF-Token` header using `hash_equals`; query-string tokens are ignored. GET, HEAD, and OPTIONS pass through; other methods require a matching token and return 419 on failure. JSON callers receive a JSON error. Register protection on all routes that mutate cookie-authenticated state, including any future cookie-authenticated API routes.

These defaults follow the [PHP session security guidance](https://www.php.net/manual/en/session.security.ini.php) and [OWASP synchronizer-token pattern](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html).

### Validation

```php
$data = $request->validate([
    'name' => 'required|string|min:2|max:100',
    'email' => 'required|email|max:254',
    'age' => 'nullable|integer|min:18',
]);
```

Rules may be pipe-separated strings or arrays. Supported rules are `required`, `nullable`, `string`, `email`, `integer`, `boolean`, `array`, `min:N`, and `max:N`. Field names are top-level keys. Optional absent fields are skipped; `nullable` permits null except when required. Required treats null, whitespace-only strings, and empty arrays as empty; zero and false are valid present values. Integer fields use numeric min/max; other strings use byte length and arrays use element count. Boolean accepts true/false, 0/1, and their string forms. Validation preserves types and returns only declared fields. Unknown rules fail explicitly.

Failures throw `Nova\Validation\ValidationException` with field-indexed `errors`. Unhandled failures become 422 responses: JSON for JSON Accept/Content-Type headers, escaped HTML otherwise. Controllers may catch failures to flash errors and selected old input, then redirect with `Response::redirect('/contact')`. See `ContactController`; it retains only bounded name/email fields. Never flash passwords, tokens, or other secrets. Redirects accept local absolute paths and reject external destinations, backslashes, and control characters.

### PHP views and layouts

```php
$html = $view->render('contact', ['title' => 'Contact', /* other view data */], 'layouts.app');
return Response::html($html);
```

View names use dot notation under `resources/views/`. Traversal and symlink escapes outside that directory are rejected. Templates are trusted PHP, executed with isolated data variables and buffered output. Use `<?= $e($value) ?>` for HTML text and quoted attributes; values are **not automatically escaped**. `$e` applies UTF-8 HTML escaping with quote escaping and invalid-byte substitution. It does not make JavaScript, CSS, or arbitrary URLs safe. Layouts receive rendered child markup as `$content`, which the provided layout outputs as trusted HTML. Do not render user-provided PHP templates. The original static welcome page continues to be read as text.

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

```bash
php tests/run.php   # Phase 1–3 regression suite
php tests/http.php  # Real HTTP/session smoke test; starts a temporary local server
```

The regression suite covers routing, DI, providers, config, environment loading, CLI, middleware order and short-circuits, grouped routes, sessions and flash lifecycle, CSRF, validation, escaped views, redirects, and the complete contact form workflow. The HTTP suite checks native cookie attributes, persistent tokens and flash messages, redirects, JSON errors, and stateless API compatibility. It uses isolated temporary session storage and cleans up its server/files. PHP process creation and localhost sockets must be available for the HTTP suite. No test framework is required.

Authentication, persistence, rate limiting, session idle/absolute expiry policies, and a hardened production security model remain future work. The static welcome page is loaded as text, not interpreted as a PHP template. Enable `APP_DEBUG=true` only on a private development machine. Deploy with debug disabled and a proper web server document root pointing at `public/`.
