# CLI and project reference

## Commands

| Command | Purpose |
| --- | --- |
| php nova serve | Start the local development server at 127.0.0.1:8000 |
| php nova route:list | List registered HTTP methods and paths |
| php nova make:model Post | Generate a Nova Records model; optionally specify --table=posts |
| php nova make:controller PostController | Generate a controller without overwriting files |
| php nova config:show app.name | Inspect one config value; omit the key for all values |
| php nova make:migration create_posts | Generate a field-map migration |
| php nova make:migration create_posts --sql | Generate a raw SQL migration |
| php nova migrate | Apply pending migrations |
| php nova migrate:status | Show applied and pending migration status |
| php nova migrate:rollback | Reverse the latest migration batch |

## Project files

| Location | Responsibility |
| --- | --- |
| app/Controllers | HTTP controllers |
| app/Models | Nova Records models |
| app/Repositories | Application persistence services |
| app/Providers | Service registration and boot logic |
| bootstrap/app.php | Application bootstrap |
| config | PHP configuration arrays |
| database/migrations | Reversible schema changes |
| docs | Editable Markdown documentation |
| app/Documentation/Library.php | Browser documentation catalog |
| resources/views | PHP templates and layouts |
| public | Web server document root and static assets |
| routes | Browser and API route registration |
| src | Framework code |
| storage | Local SQLite database and logs |
| tests | Standalone PHP tests |

## Editing the documentation

Edit a file under docs and reload its /docs page to preview the result. Add new pages to the allowlisted catalog in app/Documentation/Library.php. Documents are read as text, not executed as PHP.

The small documentation renderer supports headings with anchor IDs, paragraphs, fenced code blocks, inline code, bold text, flat bullet/numbered lists, tables, and links. It escapes raw HTML and allows HTTP(S), /docs, and anchor links only. It is not a full CommonMark implementation. Keep blank lines around block elements. The docs stylesheet is public/assets/docs.css; it needs no build step, external font, JavaScript, or CDN.

# Verification and limits

```bash
php tests/run.php   # Phase 1–5 regression suite
php tests/http.php  # Real HTTP/session smoke test; starts a temporary local server
```

The regression suite covers routing, DI, providers, config, environment loading, CLI, middleware order and short-circuits, grouped routes, sessions and flash lifecycle, CSRF, validation, escaped views, redirects, the contact form workflow, SQL parameter binding, transaction failures, migration batches and retries, CLI migrations, notes CRUD, field-map schema constraints and defaults, and both generated migration styles. The HTTP suite checks native cookie attributes, persistent tokens and flash messages, redirects, JSON errors, stateless API compatibility, and notes CRUD persistence across requests. It uses isolated temporary SQLite and session storage and cleans up its server/files. PHP process creation and localhost sockets must be available for the HTTP suite. No test framework is required.

Account recovery, email verification, MFA, a general authorization-policy system, advanced ORM features, general-purpose rate limiting, and a hardened production security model remain future work. Authentication-specific expiry and login throttling are included. The static welcome page is loaded as text, not interpreted as a PHP template. Enable `APP_DEBUG=true` only on a private development machine. Deploy with debug disabled and a proper web server document root pointing at `public/`.
