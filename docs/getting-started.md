# Getting started

NovaPHP is a small, independent PHP 8.2+ framework. The starter is intentionally application-neutral: it has a welcome page, browser documentation, and /api/health, but no account, notes, or contact application and no required application tables.

## Choose a project

```bash
php nova new my-app
```

Select Empty or Vue, React, Svelte, Solid, Preact, or Alpine. Read [project creation](/docs/project-creation) for the blueprint structure and non-interactive options. Frontend projects need Node 22.12+ or 24+ for development/builds; empty projects do not.

## Run PHP

```bash
cd my-app
php nova serve
```

Open http://127.0.0.1:8000 and /docs. Composer is optional: the built-in autoloader maps App to app and Nova to src. PDO and a driver extension are needed only for database features.

## Add your application

- [Controllers](/docs/controllers) handle requests and responses.
- [Models](/docs/models) query and persist records with Nova Records.
- [Migrations](/docs/migrations) define your tables using field maps or SQL.
- [Routing](/docs/routing) groups routes, middleware, and named URLs.
- [Authentication](/docs/authentication) explains optional identity providers and application-owned account flows.
- [Extension points](/docs/extensions) shows providers, middleware, and adapters.

## Test

```bash
php tests/run.php
```

Generated projects have an independent smoke test. The NovaPHP source repository additionally has regression, native HTTP/session, fixture-authentication, frontend-build, and MySQL tests. See [the reference](/docs/reference).
