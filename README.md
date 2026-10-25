# NovaPHP

An independent PHP 8.2+ framework with routing, dependency injection, service providers, configuration, middleware, sessions, CSRF, validation, PHP views, PDO, migrations, Nova Records ORM, and optional authentication adapters. No third-party PHP runtime packages are required. NovaPHP is early-development software.

## Create a project

```bash
php nova new my-app
```

Choose **Empty**, **Vue**, **React**, **Svelte**, **Solid**, **Preact**, or **Alpine**. For automation:

```bash
php nova new my-app --stack=react
cd my-app
php nova serve
```

An empty project requires no Node tooling or application database tables. Frontend projects include an independently configurable blueprint under `stubs/` and locked dependencies:

```bash
npm ci
npm run dev
# or npm run build to serve the compiled frontend through PHP
```

Use Node 22.12+ or 24+. Run PHP and Vite in separate terminals. Vite serves http://127.0.0.1:5173/build/ and proxies `/api` to PHP at port 8000; set `NOVA_BACKEND_URL` to use another backend address. Builds land in `public/build` and are served by the generated PHP welcome controller.

Creation refuses existing destinations. It copies clean base templates, framework source, docs, and the selected frontend. It never copies local `.env`, Git state, dependencies, database files, or test demos. Dependencies are installed explicitly by the user. This is a local project creator; publication of a global installer or Composer package is a separate release task.

## Documentation

```bash
php nova serve
```

Open [the documentation](http://127.0.0.1:8000/docs). Editable sources live under `docs/`; `/docs/project-creation` covers the seven project choices. The guide also covers controllers, models, migrations, routing, configuration, extension points, and optional authentication.

## A clean framework starter

Only the welcome, docs, and health routes are installed. Models and migrations are application-owned. Notes, contact forms, account controllers, and their schemas now live under `tests/Fixtures/Demo` solely to test the framework; they are not public routes or generated project content. Existing local SQLite databases have not been deleted or reset.

CoreServiceProvider registers framework services. AppServiceProvider is the application's extension point. Authentication requires a configured UserProvider and application-owned login routes/storage; no users table or account UI is assumed by the starter. The optional DatabaseUserProvider supports configurable tables and columns.

## Useful commands

```bash
php nova route:list
php nova make:controller PostController
php nova make:model Post
php nova make:migration create_posts
php nova make:migration create_posts --sql
php nova migrate
php nova migrate:status
php nova migrate:rollback
php nova config:show app.name
```

Database features need PDO plus `pdo_sqlite` or `pdo_mysql`. Migration schema helpers and raw parameterized SQL can be mixed. Model queries use explicit connections and writable-field allowlists. Named routes generate encoded URLs; Nova Records provides pagination metadata. The docs describe the APIs and limits.

## Verify framework changes

```bash
php tests/run.php
php tests/http.php
```

The source repository has a framework regression suite and an isolated demo fixture for native-cookie and authentication tests. Generated projects instead receive a small application smoke test. Frontend CI builds all six stacks with clean `npm ci` installs. PHP CI covers supported versions, SQLite, and a dedicated MySQL service; local MySQL verification requires the explicit test configuration described in the reference.

This remains an educational framework. Email recovery/verification, MFA, advanced authorization policies and ORM features, and a complete production security model are future work. Keep debug disabled in deployments and point the web server document root at `public/`.
