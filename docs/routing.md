# Routing and middleware

Define browser routes in routes/web.php and JSON API routes in routes/api.php. Both files receive the application router.

## Basic routes

```php
$router->get('/hello/{name}', fn (string $name) => 'Hello, ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
$router->post('/posts', [PostController::class, 'store']);
```

Supported methods are get, post, put, patch, and delete. Named route parameters map to handler argument names. Controllers are constructed through the DI container. Request arguments are injected automatically; other arguments resolve as named route parameters, defaults, then service dependencies. Handlers return Response, an array (JSON), or a string (HTML). Missing routes return 404 and wrong methods return 405. HEAD requests match GET routes and the front controller suppresses the response body.

## Requests and responses

```php
$name = $request->input('name', 'Guest');
$query = $request->query('page', 1);
$token = $request->body('_token');
$accept = $request->header('accept');
$data = $request->all();

return Nova\Http\Response::json(['name' => $name]);
```

Request::fromGlobals parses query, form, and JSON input. input reads body then query; body reads body only. Header names are case-insensitive when using fromGlobals. Response provides html, text, json, and local-path redirect helpers.

## Middleware and route groups

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
