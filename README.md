# NovaPHP — Phase 4

An independent educational PHP framework for PHP 8.2+, with no third-party runtime packages. Database features require PDO and `pdo_sqlite` (default) or `pdo_mysql`. Existing non-database routes do not require a database connection.

🚧 NovaPHP is in early development and is not recommended for production use.

## Browser documentation

Run `php nova serve` and open [the documentation](http://127.0.0.1:8000/docs). The guide covers setup, controllers, Nova Records models, migrations and schema fields, database queries, routing, forms, services, configuration, and CLI commands.

Documentation source lives in `docs/*.md`. Edit a file and reload its browser page to preview it. The allowlisted catalog in `app/Documentation/Library.php` controls navigation. No frontend build step or Markdown package is needed.

## Run

```bash
php nova serve
```

Visit http://127.0.0.1:8000. Composer is optional: a built-in PSR-4 fallback loads `Nova\` from `src/` and `App\` from `app/`. If you use Composer, run `composer dump-autoload`.

```bash
php nova route:list
php nova make:controller PostController
php nova make:model Post
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

## Nova Records: models and ORM

NovaPHP's ORM uses explicit connections and named model fields:

```php
use App\Models\Note;

$notes = Note::using($database)->where('title', 'LIKE', '%Nova%')->orderBy('id', 'desc')->take(20)->all();
$note = Note::using($database)->create(['title' => 'Hello Nova', 'body' => 'A saved record']);
$note->title = 'Updated';
$note->save();
$note->delete();
```

Generate a model with `php nova make:model Post [--table=posts]`, then declare its `WRITABLE` fields. Nova Records supports immutable filtered queries, row persistence, casts, hidden attributes, opt-in timestamps, and explicit belongs-to/has-many relations. The notes demo uses it while retaining existing HTTP behavior. Read [models](/docs/models) and [controllers](/docs/controllers) in the browser documentation for complete examples and limitations.

## Phase 4: database access and migrations

### Run the notes demo

```bash
php nova migrate
php nova serve
```

Visit `/notes` to create, list, edit, and delete notes. The browser forms use sessions, CSRF protection, validation, escaped output, and flash messages. The list shows the latest 100 notes. Missing or malformed note IDs return 404. JSON requests to the list/create/update/delete endpoints receive JSON responses; mutations still require a session CSRF token. There is no authentication or ownership yet: this is a shared development demo, so use sample data only.

SQLite defaults to `storage/database.sqlite`. The tracked `storage/` directory must be writable. Relative SQLite paths are resolved from the project root; absolute paths and `:memory:` are also supported. Database files and SQLite sidecars are ignored by Git. Migrations run explicitly, never on HTTP requests or application bootstrap.

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=storage/database.sqlite
```

For an existing MySQL database, use:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nova
DB_USERNAME=nova
DB_PASSWORD='your local password'
```

Create the MySQL database/user separately and enable `pdo_mysql`; NovaPHP does not provision a server or database. Connections use utf8mb4, exception mode, associative fetches, and native prepared statements. SQLite enables foreign keys and a five-second busy timeout. The connection is lazy and shared through the DI container. Existing environment variables override `.env` as before.

### Queries and transactions

Inject `Nova\Database\Connection` into a controller or repository:

```php
$notes = $database->select('SELECT * FROM notes WHERE title = ?', [$title]);
$note = $database->first('SELECT * FROM notes WHERE id = :id', ['id' => $id]);
$affected = $database->execute('UPDATE notes SET title = ? WHERE id = ?', [$title, $id]);
$id = $database->lastInsertId();

$result = $database->transaction(function (Nova\Database\Connection $database) {
    $database->execute('INSERT INTO notes (title, body, created_at) VALUES (?, ?, ?)',
        ['Example', 'A note', gmdate('c')]);
    return $database->lastInsertId();
});
```

`select()` returns rows, `first()` returns a row or null, and `execute()` returns the PDO affected-row count (which can vary by driver for unchanged updates). `lastInsertId()` returns a string. Bindings support strings, numbers, booleans, and null, with positional or named placeholders. Values are bound separately from SQL; table names, column names, and sort directions must come from trusted code or an explicit allowlist. Use unique named placeholders in each statement. See [PDO prepared statements](https://www.php.net/manual/en/pdo.prepare.php).

`transaction()` commits the callback result or rolls back and rethrows on failure. Nested transactions are rejected; savepoints are not implemented. Keep transaction control inside this helper and avoid DDL in application transactions. MySQL transactions require a transactional storage engine such as InnoDB.

`App\Repositories\NoteRepository` delegates persistence to the `App\Models\Note` model through Nova Records, separate from validation and HTTP rendering. Raw SQL and `pdo()` remain available. See [the models guide](/docs/models) for the ORM API.

### Migration commands

```bash
php nova make:migration create_widgets
php nova migrate:status
php nova migrate
php nova migrate:rollback
```

NovaPHP migrations support two styles: a named list of `Field` descriptions, and raw SQL. Both keep the existing `Migration` interface and run through the same runner. Use either style or mix them in one migration.

```bash
php nova make:migration create_posts        # NovaPHP schema style
php nova make:migration create_posts --sql  # Raw SQL style
```

`create_posts` infers the table name `posts` and generates working `up()` and `down()` methods with an auto-increment ID. Add your other fields before running it. Other names, such as `add_summary_to_posts`, generate commented examples for you to complete. Migration names must be snake_case and at most 100 characters; inferred table names must be at most 63 characters. Files are timestamped under `database/migrations/` and are trusted PHP code.

### NovaPHP field maps

The array key is the column name; the value describes its type and options. No blueprint callback or static facade is needed:

```php
use Nova\Database\{Connection, Field, Migration};

return new class implements Migration {
    public function up(Connection $database): void
    {
        $database->schema()->create('posts', [
            'id'        => Field::id(),
            'title'     => Field::string(150),
            'slug'      => Field::string(150)->unique(),
            'body'      => Field::text()->optional(),
            'published' => Field::boolean()->defaultsTo(false),
        ]);
    }

    public function down(Connection $database): void
    {
        $database->schema()->drop('posts');
    }
};
```

Supported fields:

| Field | Purpose |
| --- | --- |
| `Field::id()` | One auto-increment primary key per table |
| `Field::string(150)` | VARCHAR, default length 255 |
| `Field::text()` | Longer text |
| `Field::integer()` | Signed BIGINT on MySQL, INTEGER on SQLite |
| `Field::boolean()` | BOOLEAN on MySQL, INTEGER on SQLite |
| `Field::decimal(10, 2)` | Decimal precision and scale |
| `Field::datetime()` | DATETIME on MySQL, TEXT on SQLite |

Fields are required (`NOT NULL`) unless you call `optional()`. `defaultsTo(...)` accepts a literal string, number, boolean, or null; a null default requires `optional()`. SQL expressions such as `CURRENT_TIMESTAMP` are not evaluated by this method: use raw SQL for expression defaults. ID fields do not accept modifiers. Field descriptions are immutable, so one description can be reused safely. `unique()` adds a single-column unique constraint.

The schema API validates simple table/column/index names, quotes identifiers for the driver, and quotes string defaults with the active PDO driver. It does not impose identical database behavior: SQLite does not enforce VARCHAR length or decimal precision like MySQL, and boolean values still need application validation. Use `YYYY-MM-DD HH:MM:SS` datetime strings for portable DATETIME inserts. MySQL TEXT defaults and unique TEXT fields are not offered as portable schema features; use a bounded string or driver-specific SQL instead. MySQL schema-created tables use InnoDB and utf8mb4.

### Changing tables and adding indexes

```php
$schema = $database->schema();
$schema->add('posts', [
    'summary' => Field::string(200)->optional(),
    'views'   => Field::integer()->defaultsTo(0),
]);
$schema->index('posts', ['title', 'published'], name: 'posts_title_published');
$schema->index('posts', ['slug'], unique: true, name: 'posts_slug_unique');

// Write the appropriate reverse operations in down():
$schema->dropIndex('posts', 'posts_title_published');
$schema->remove('posts', 'summary');
$schema->rename('posts', 'articles');
$schema->dropIfExists('articles');
```

Added fields must be optional or have a non-null default so existing rows remain valid. They cannot be IDs or have an inline unique constraint; use `index(..., unique: true)` after adding the field. Name indexes explicitly when you need to drop them later. Removing columns requires SQLite 3.35+ and can fail when a column participates in a constraint or index; remove dependent objects first or write a table-rebuild migration. Helpers execute SQL immediately; multi-statement changes use the runner's existing per-migration transaction behavior. See [SQLite ALTER TABLE restrictions](https://sqlite.org/lang_altertable.html).

`createSql('posts', $fields)` returns CREATE TABLE SQL without executing it; string defaults may open a connection for driver-aware quoting. Foreign keys, column-type changes, and database-specific features can be written as raw SQL in this initial schema API.

### Raw queries remain available

```php
public function up(Connection $database): void
{
    $database->execute('CREATE TABLE labels (id INTEGER PRIMARY KEY, name VARCHAR(80) NOT NULL)');
    $database->execute('INSERT INTO labels (id, name) VALUES (?, ?)', [1, 'General']);
}

public function down(Connection $database): void
{
    $database->execute('DROP TABLE labels');
}
```

Raw SQL is executed as written and must match your database dialect. Use bindings for data values. You can create a table with `schema()->create()` and then insert data with `execute()` in the same migration; use the complete field-map example above and add the bound insert in its `up()` method. Documentation code blocks are never executed by the browser preview. The original notes migration remains unchanged, and existing raw SQL migrations continue to work.

Pending files apply in filename order. `nova_migrations` tracks applied names, batches, and UTC timestamps. Repeating `migrate` skips applied files; each invocation with new migrations receives the next batch number. `migrate:rollback` reverses the latest batch in reverse filename order. It can delete tables/data through the migration's `down()` method. Status shows applied/pending migrations, batches, and missing files; it initializes the history table if needed. Do not edit or rename applied migrations—add a new migration instead.

A local file lock serializes migration commands using the same migration directory. It is not a distributed lock across servers or separate checkouts; coordinate deployments accordingly. The migration directory must be writable for the lock file. No automatic retry or destructive reset command is provided.

Each SQLite migration and its history update run in one transaction. A failure leaves earlier successful migrations applied and the failed migration pending. Rollbacks are also transactional per migration, rather than per batch. Missing files are detected before rolling back a batch. MySQL DDL can commit implicitly, so failed MySQL migrations may leave partial schema changes requiring manual repair; inspect status and schema before retrying. See [PDO transactions and implicit commits](https://www.php.net/manual/en/pdo.transactions.php).

SQLite regression and HTTP tests run against isolated databases. MySQL configuration and SQL support are included, but this phase has not been verified against a live MySQL server.

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
php tests/run.php   # Phase 1–4 regression suite
php tests/http.php  # Real HTTP/session smoke test; starts a temporary local server
```

The regression suite covers routing, DI, providers, config, environment loading, CLI, middleware order and short-circuits, grouped routes, sessions and flash lifecycle, CSRF, validation, escaped views, redirects, the contact form workflow, SQL parameter binding, transaction failures, migration batches and retries, CLI migrations, notes CRUD, field-map schema constraints and defaults, and both generated migration styles. The HTTP suite checks native cookie attributes, persistent tokens and flash messages, redirects, JSON errors, stateless API compatibility, and notes CRUD persistence across requests. It uses isolated temporary SQLite and session storage and cleans up its server/files. PHP process creation and localhost sockets must be available for the HTTP suite. No test framework is required.

Authentication and ownership, advanced ORM features, rate limiting, session idle/absolute expiry policies, and a hardened production security model remain future work. The static welcome page is loaded as text, not interpreted as a PHP template. Enable `APP_DEBUG=true` only on a private development machine. Deploy with debug disabled and a proper web server document root pointing at `public/`.
