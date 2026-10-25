# Forms and views

## Sessions, flash messages, and CSRF

`AppServiceProvider` registers shared `Session` and `View` services. Apply `StartSession` before `VerifyCsrfToken` on browser form routes, as demonstrated in `routes/web.php`. Session access outside session middleware fails explicitly. Requests without this middleware do not start a session.

```php
$session->put('theme', 'dark');
$session->get('theme', 'light');
$session->forget('theme');
$session->flash('notice', 'Saved');
$session->getFlash('notice');
$session->regenerate(); // Rotate session ID and invalidate the CSRF token.
```

Flash values remain available during the current request and the next session-enabled request, then expire. `StartSession` persists and closes the native PHP session in a `finally` block. Authentication uses idle/absolute expiry and session invalidation on login/logout; see [authentication](/docs/authentication). Native sessions use strict mode, cookies only, HttpOnly, SameSite=Lax, a session cookie lifetime, and `/` as the cookie path. Set `SESSION_SECURE=true` on HTTPS deployments; false allows the local HTTP demo. TLS terminated at a proxy requires this explicit setting; forwarded headers are not automatically trusted. PHP's configured session storage must be writable. Do not start PHP sessions independently alongside NovaPHP session middleware. `new Session(native: false)` provides isolated in-memory storage for tests.

Use `$session->token()` for a random 32-byte token, rendered as hex in a hidden `_token` form field. CSRF middleware checks a body field or `X-CSRF-Token` header using `hash_equals`; query-string tokens are ignored. GET, HEAD, and OPTIONS pass through; other methods require a matching token and return 419 on failure. JSON callers receive a JSON error. Register protection on all routes that mutate cookie-authenticated state, including any future cookie-authenticated API routes.

These defaults follow the [PHP session security guidance](https://www.php.net/manual/en/session.security.ini.php) and [OWASP synchronizer-token pattern](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html).

## Validation

```php
$data = $request->validate([
    'name' => 'required|string|min:2|max:100',
    'email' => 'required|email|max:254',
    'age' => 'nullable|integer|min:18',
]);
```

Rules may be pipe-separated strings or arrays. Supported rules are `required`, `nullable`, `string`, `email`, `integer`, `boolean`, `array`, `min:N`, and `max:N`. Field names are top-level keys. Optional absent fields are skipped; `nullable` permits null except when required. Required treats null, whitespace-only strings, and empty arrays as empty; zero and false are valid present values. Integer fields use numeric min/max; other strings use byte length and arrays use element count. Boolean accepts true/false, 0/1, and their string forms. Validation preserves types and returns only declared fields. Unknown rules fail explicitly.

Failures throw `Nova\Validation\ValidationException` with field-indexed `errors`. Unhandled failures become 422 responses: JSON for JSON Accept/Content-Type headers, escaped HTML otherwise. Controllers may catch failures to flash errors and selected old input, then redirect with `Response::redirect('/contact')`. See `ContactController`; it retains only bounded name/email fields. Never flash passwords, tokens, or other secrets. Redirects accept local absolute paths and reject external destinations, backslashes, and control characters.

## PHP views and layouts

```php
$html = $view->render('contact', ['title' => 'Contact', /* other view data */], 'layouts.app');
return Response::html($html);
```

View names use dot notation under `resources/views/`. Traversal and symlink escapes outside that directory are rejected. Templates are trusted PHP, executed with isolated data variables and buffered output. Use `<?= $e($value) ?>` for HTML text and quoted attributes; values are **not automatically escaped**. `$e` applies UTF-8 HTML escaping with quote escaping and invalid-byte substitution. It does not make JavaScript, CSS, or arbitrary URLs safe. Layouts receive rendered child markup as `$content`, which the provided layout outputs as trusted HTML. Do not render user-provided PHP templates. The original static welcome page continues to be read as text.
