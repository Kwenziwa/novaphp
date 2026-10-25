# Create a project

NovaPHP can generate an empty PHP application or a PHP application with a JavaScript frontend. Project creation copies a clean base blueprint plus the chosen frontend; it does not copy the current application's controllers, configuration, database, environment secrets, Git history, vendor directory, or test demos.

## Interactive selection

From a NovaPHP checkout or generated project, run:

```bash
php nova new my-app
```

Choose Empty, Vue, React, Svelte, Solid, Preact, or Alpine. Press Enter for Empty. The destination must not exist, and its parent directory must already exist. Existing directories are never merged or overwritten.

## Explicit selection

```bash
php nova new my-app --stack=empty
php nova new my-vue-app --stack=vue
php nova new my-react-app --stack=react
php nova new my-svelte-app --stack=svelte
php nova new my-solid-app --stack=solid
php nova new my-preact-app --stack=preact
php nova new my-alpine-app --stack=alpine
```

Automation must specify a stack. Use --interactive to explicitly read the selection from standard input when testing an interactive flow. There is no dependency download, Git initialization, database migration, or account creation during generation.

## Empty applications

```bash
cd my-app
php nova serve
php tests/run.php
```

PHP 8.2+ is enough for the starter and browser documentation. PDO with the appropriate driver is needed only when you use database features. Composer is optional. The starter has a welcome page, /docs, and a database-independent /api/health endpoint. Application model and migration directories are empty. Authentication is an optional core service, not an installed application.

## Frontend applications

In separate terminals:

```bash
php nova serve
```

```bash
npm ci
npm run dev
```

Use Node 22.12+ or Node 24+. The blueprints have verified, pinned dependencies and package lockfiles. Vite serves the frontend at http://127.0.0.1:5173/build/ and proxies /api to http://127.0.0.1:8000. If PHP runs on another port, set NOVA_BACKEND_URL when starting Vite:

```bash
NOVA_BACKEND_URL=http://127.0.0.1:8002 npm run dev
```

The source files live at the project root: App.vue/main.js for Vue, App.jsx/main.jsx for React, Solid, and Preact, App.svelte/main.js for Svelte, and main.js for Alpine. Alpine's markup is in main.js; PHP views remain available for server-rendered pages. Each stack has package.json, package-lock.json, vite.config.js, index.html, and a small stylesheet. No account or CRUD application is included.

## Build for PHP hosting

```bash
npm run build
php nova serve
```

Builds go into public/build. The generated HomeController serves the built index at /, while PHP serves /api and /docs. Node is not needed at runtime after building. Deploy with the web server document root set to public. Before the first build, the PHP welcome route displays setup instructions. The initial frontend is a single page; client-side history-routing fallbacks are not installed automatically.

## Blueprint layout

```text
stubs/
  base/       clean PHP application, config, routes, docs, and smoke test
  common/     shared frontend PHP welcome controller and setup view
  vue/        Vue dependencies, Vite config, App.vue, main.js
  react/      React dependencies, Vite config, App.jsx, main.jsx
  svelte/     Svelte dependencies, Vite config, App.svelte, main.js
  solid/      Solid dependencies, Vite config, App.jsx, main.jsx
  preact/     Preact dependencies, Vite config, App.jsx, main.jsx
  alpine/     Alpine dependencies, Vite config, main.js
```

Blueprints are trusted project-generation input. Symlinks are rejected. Local application changes do not change the base blueprint; edit stubs/base or a specific frontend stub deliberately to customize generation. Failed generation removes only its own temporary staging directory. Frontend package updates belong in the matching stub and lockfile and should be verified with a clean install/build.
