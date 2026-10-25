# Authentication and ownership

NovaPHP includes account registration, password login, POST logout, protected routes, and per-user notes. Apply the new migration before using account features:

```bash
php nova migrate
php nova serve
```

Open /register to create an account, /login to sign in, /account to view the current account, and /notes to manage your own notes. No default account or password is created. The documentation, contact form, and existing stateless API routes remain public.

## What changes for existing notes

The additive migration creates users and auth_attempts tables and adds a nullable user_id foreign key and owner index to notes. Existing notes are preserved with a null owner and are hidden from normal user queries. They are not given to the first registered user, and there is no public claim endpoint.

If legacy notes need an owner, an administrator must verify the correct account and assign individual notes deliberately through trusted database code. For example, with verified IDs:

```php
$database->execute('UPDATE notes SET user_id = ? WHERE id = ? AND user_id IS NULL', [$verifiedUserId, $verifiedNoteId]);
```

Do not use request input for an administrative ownership assignment. Rolling back the authentication migration removes user accounts, throttle counters, the foreign key, and ownership column while preserving note contents; use backups and review migration down methods before rollback. SQLite rollback requires SQLite 3.35+ for column removal. MySQL DDL retains the existing implicit-commit limitations.

## Protect a route

```php
use Nova\Http\Middleware\{StartSession, VerifyCsrfToken, RequireAuth};

$router->group('/private', [StartSession::class, VerifyCsrfToken::class, RequireAuth::class],
    static function ($router): void {
        $router->get('/reports', [ReportController::class, 'index']);
    });
```

Session middleware must run first; CSRF verification protects cookie-authenticated write requests; RequireAuth then checks the current account and expiry. Browser guests are redirected to /login. JSON guests receive 401. Protected responses use Cache-Control: no-store. RequireAuth protects login status, not permission to every record—ownership checks are separate.

## Access the current user

Inject Nova\Auth\Auth into controllers or repositories:

```php
$user = $auth->user();        // id, name, email, or null
$user = $auth->requireUser(); // throws if unauthenticated; use behind RequireAuth
```

The session stores the user ID and issue/activity timestamps. Each check reloads the safe account fields; deleted accounts lose access. Password hashes are never returned by these methods. App\Models\User hides password_hash in arrays and JSON and does not allow it in WRITABLE. Registration and password verification go through Auth rather than a generic User model create.

Successful registration signs the user in. Login and registration clear the previous session contents, regenerate its ID, and rotate the CSRF token. Logout clears all session contents, regenerates its ID, and leaves a fresh anonymous session. Logout requires POST plus a valid CSRF token; there is no GET logout route.

## Passwords and validation

Registration requires a name, a valid email, a password, and matching password_confirmation. Email is trimmed and lowercased and is unique at the database level. Passwords require at least 15 Unicode characters and at most 72 UTF-8 bytes, without null bytes. There are no composition rules. The byte maximum avoids bcrypt's silent truncation; sufficiently long multibyte passphrases may reach that maximum with fewer than 72 characters.

Passwords are hashed using PHP bcrypt at cost 12 and verified with password_verify. Successful login upgrades hashes with a lower cost. Unknown emails use a dummy hash verification and receive the same credential error as incorrect passwords. Password fields are not repopulated or flashed, and Auth password parameters use PHP's SensitiveParameter attribute. Duplicate registration returns a generic registration failure for the email field. This does not make registration an email-availability privacy guarantee.

See the [PHP password hashing documentation](https://www.php.net/manual/en/function.password-hash.php) and [OWASP authentication guidance](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html).

## JSON clients and CSRF

GET /csrf-token starts an anonymous session and returns csrf_token. Keep the session cookie, and submit that token as a body _token or X-CSRF-Token header. Do not put credentials or CSRF tokens in query strings.

```text
GET  /csrf-token
POST /register   name, email, password, password_confirmation, _token
POST /login      email, password, _token
GET  /account    Accept: application/json
POST /logout     _token
```

Use Content-Type: application/json and Accept: application/json for JSON requests. Successful registration returns 201; successful login returns 200. Both return safe user fields and a new csrf_token. Account JSON also includes the current token. JSON logout returns a new anonymous token. Always replace your token after successful registration, login, or logout. Pre-login form tokens become invalid after authentication; reload forms opened in other tabs when necessary.

Validation returns 422, incorrect credentials 401, missing/invalid CSRF 419, and throttled attempts 429 with a Retry-After header. Passwords are accepted from the body only. Failed HTML forms show escaped errors and retain only bounded name/email values.

## Session expiry and login throttling

```dotenv
AUTH_IDLE_SECONDS=1800
AUTH_ABSOLUTE_SECONDS=43200
SESSION_SECURE=true
```

Authentication expires after 30 minutes without an authentication check or 12 hours after login by default. Activity refreshes the idle timer but never extends the absolute lifetime. Expired, malformed, or deleted-user sessions are invalidated. Expiry settings must be positive; there is no remember-me feature. SESSION_SECURE=true is required for cookies on HTTPS deployments; keep false only for local HTTP development. Configure your web server and proxy for HTTPS; arbitrary forwarded IP/HTTPS headers are not trusted.

A persistent database limiter allows five login attempts per normalized email and thirty per direct client IP in a 15-minute window. Successful login clears the account counter, while the IP counter remains. Registration allows ten valid registration attempts per IP in the same window. Counters survive cookie changes and process restarts. Identities are stored as SHA-256 hashes, not raw emails/IPs. Expired counters are pruned during new attempts. Shared IPs can share limits, and targeted account throttling can affect a legitimate user's login; tune infrastructure and recovery flows before a production deployment. Retry-After is a conservative 900 seconds.

## Ownership is enforced by the repository

All note list/find/update/delete operations in NoteRepository require an authenticated account and scope lookups to user_id. Creation uses the authenticated ID rather than a submitted owner. Updates accept title/body only. Missing, unowned, and other users' notes return 404 through the controller. The user ID and foreign-key cascade prevent ordinary orphan creation and remove owned notes if a user is deleted through trusted administrative database code.

Nova Records itself is a low-level ORM and does not automatically apply user scopes. Direct Note::using queries can access every row. For web-facing note operations, inject NoteRepository, or explicitly scope and authorize every direct model query. Authentication middleware alone is not record authorization. There is no general roles/permissions policy engine yet.

## Current limits

This is an educational implementation, not a complete production identity system. Email verification, password reset/recovery, MFA, breached-password screening, account-management UI, cross-device session revocation, and general roles/policies are not implemented. A deployed application also needs operational monitoring, HTTPS, backups, appropriate server limits, and a reviewed security model. SQLite authentication and cross-user HTTP flows are tested; MySQL remains unverified against a live server.
