# Optional authentication

Authentication is a reusable framework module, not an installed account application. The clean starter has no login/register routes, users model, account views, or account migrations. Frontend selection does not enable authentication automatically.

## Choose your identity provider

Nova\Auth\UserProvider defines four methods:

```php
interface UserProvider
{
    public function findById(int $id): ?array;
    public function findByEmail(string $email): ?array;
    public function create(string $name, string $email, string $passwordHash): array;
    public function rehash(int $id, string $previousHash, string $newHash): void;
}
```

Providers return canonical id, name, email, and password_hash fields. Password hashing and verification remain in Auth; the provider stores hashes and maps your application identity. Auth returns only id/name/email to callers. Custom adapters may use a different schema or identity store. Account keys must currently be positive integers. A provider should translate duplicate-account creation into ValidationException rather than exposing storage details.

Set auth.provider to your implementation or bind UserProvider in an application provider. Leaving it null produces an explicit configuration error when authentication is resolved; ordinary starter routes do not resolve it.

## Optional SQL adapter

DatabaseUserProvider supports table/column mappings in config/auth.php:

```php
'provider' => Nova\Auth\DatabaseUserProvider::class,
'login_path' => '/login',
'attempts_table' => 'auth_attempts',
'storage' => [
    'table' => 'members',
    'id' => 'member_id',
    'name' => 'display_name',
    'email' => 'email',
    'password_hash' => 'password_hash',
    'created_at' => 'created_at', // null if your schema has no timestamp column
],
```

Create the corresponding application-owned schema through migrations. Names must be simple SQL identifiers. The identity table needs an auto-increment integer key, display name, unique normalized email, password hash column of at least 255 characters, and a timestamp if configured. The registration service trims/lowercases emails. The adapter does not create tables automatically.

The persistent limiter separately requires a table matching auth.attempts_table:

```php
$database->schema()->create('auth_attempts', [
    'key_hash' => Nova\Database\Field::string(64)->unique(),
    'attempts' => Nova\Database\Field::integer(),
    'window_started' => Nova\Database\Field::integer(),
]);
$database->schema()->index('auth_attempts', ['window_started']);
```

This limiter remains SQL-backed even if the identity provider uses another store. It is not provisioned until an application chooses to use authentication.

## Protect routes and build your account flow

```php
use Nova\Http\Middleware\{StartSession, VerifyCsrfToken, RequireAuth};

$router->group('/private', [StartSession::class, VerifyCsrfToken::class, RequireAuth::class],
    static function ($router): void {
        $router->get('/reports', [ReportController::class, 'index']);
    });
```

Session middleware runs first. RequireAuth redirects browser guests to auth.login_path and returns JSON 401 for JSON guests. Your application must implement that login route. Cookie-authenticated write routes also need CSRF middleware. Login and registration routes need sessions and CSRF but cannot require an already authenticated account.

Inject Nova\Auth\Auth into application controllers:

```php
$user = $auth->user(); // safe user fields or null
$user = $auth->requireUser();
$created = $auth->register($name, $email, $password, $request->ip());
$success = $auth->attempt($email, $password, $request->ip());
$auth->logout();
```

Controllers validate fields and password confirmation, return their own forms/JSON, and handle ValidationException and RateLimitException. Do not echo, flash, or repopulate passwords. Auth registration signs the new user in. Login/registration clear session contents and rotate the ID and CSRF token. Logout invalidates the session; expose it only as a CSRF-protected POST action. JSON flows should return a new session token after authentication state changes.

## Passwords, expiry, and throttling

Passwords require at least 15 Unicode characters and no more than 72 UTF-8 bytes, without null bytes. PHP bcrypt cost 12 is used, with a dummy verification for unknown accounts and hash upgrades on successful login. The byte bound avoids silent bcrypt truncation. Auth password parameters use SensitiveParameter. See [PHP hashing](https://www.php.net/manual/en/function.password-hash.php) and [OWASP authentication guidance](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html).

AUTH_IDLE_SECONDS defaults to 1800 and AUTH_ABSOLUTE_SECONDS to 43200. Values must be positive. Authentication checks refresh idle activity without extending absolute lifetime. Invalid state and missing accounts invalidate sessions. Enable SESSION_SECURE on HTTPS deployments; arbitrary forwarded client-IP headers are not trusted.

The SQL limiter permits five login attempts per normalized email, thirty per direct client IP, and ten valid registration attempts per IP in a 15-minute window. It persists counters as hashed identities. Successful login clears the account counter, not the IP counter. Handle RateLimitException as 429 with retryAfter. Shared IPs and targeted account throttling have usability tradeoffs. Infrastructure limits and account recovery are application responsibilities.

## Authorization belongs to the application

Authentication does not authorize access to every record. Models have no automatic current-user scopes. Scope private queries to the authenticated owner and reject owner IDs from request input. Define foreign keys, deletion rules, and roles/policies in the application.

## Existing local demo data

Removing public demos does not delete or migrate your local database. Existing notes/accounts remain on disk, but the starter no longer exposes them and generated projects never copy them. The source repository retains the demo exclusively under tests/Fixtures/Demo to test sessions, authentication, ownership, and migrations against isolated databases. Those test fixtures are not a supported scaffold installer.

Email recovery/verification, MFA, breached-password checks, cross-device session revocation, and a general policy engine remain future work. An optional account scaffold can be added later without hard-coding it into every project.
