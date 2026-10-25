# Extension points

NovaPHP provides core services and explicit application extension points. Applications own their models, tables, routes, templates, and identity flows.

## Bootstrap and service providers

bootstrap/app.php creates Application, registers CoreServiceProvider, registers configured application providers, boots them, and loads browser/API routes. Framework services are lazy; booting a clean starter does not open a database or start a session. AppServiceProvider is an empty application hook.

```php
public function register(): void
{
    $this->app->container->singleton(Mailer::class, fn () => new SmtpMailer());
}
```

Add provider classes to config/app.php. All configured application providers register before boot methods run. Applications can override core bindings in register. CoreServiceProvider supplies Connection, Migrator, Session, View, and the configurable UserProvider binding. When constructing Application directly, register core and application providers explicitly if needed.

## Middleware

Implement Nova\Http\Middleware or use a closure accepting Request and the next handler. Register global middleware with Application::middleware or per group/route. Use try/finally for cleanup. See [routing](/docs/routing).

## Identity adapters

Implement Nova\Auth\UserProvider or configure DatabaseUserProvider. Set auth.provider to your implementation, or bind UserProvider in an application provider. The starter leaves it null and has no identity tables. See [authentication](/docs/authentication).

## Documentation

Markdown guides are read as text and rendered with escaping. App\Documentation\Library is the allowlisted catalog of pages. Register new guide entries there and add their Markdown files under docs. Raw HTML in Markdown is escaped. The renderer supports a documented subset, not full CommonMark.

## Project blueprints

stubs/base is the clean application blueprint. stubs/common is shared frontend PHP integration; each frontend has its own dependency lock and entry files. The creator copies templates deliberately rather than cloning the current application. See [project creation](/docs/project-creation).
