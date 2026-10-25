# Getting started

NovaPHP is an independent, educational PHP framework. It uses small, explicit services and has no third-party runtime packages. It is in early development and is not recommended for production use.

## Requirements

- PHP 8.2 or newer
- PDO and pdo_sqlite for the database and notes demo
- pdo_mysql when using MySQL

Non-database routes do not open a database connection. Composer is optional: the built-in autoloader supports App and Nova namespaces.

## Start the application

```bash
php nova serve
```

Open [the welcome page](http://127.0.0.1:8000) or [the documentation](/docs). This guide is rendered from Markdown files in the docs directory, so you can edit the files and reload your browser.

## Try the included features

- [Migrations](/docs/migrations): field maps and raw SQL, with reversible changes.
- [Database](/docs/database): SQLite, MySQL configuration, and transactions.
- [Routing and middleware](/docs/routing): requests, controllers, and grouped routes.
- [Forms and views](/docs/forms): sessions, validation, CSRF, and HTML escaping.

To try the shared notes demo, initialize its database and visit /notes:

```bash
php nova migrate
php nova serve
```

The notes demo has no authentication or ownership. Use sample data only. The /contact form demonstrates validation and flash messages without sending or saving messages.

## Run the tests

```bash
php tests/run.php
php tests/http.php
```

The HTTP suite starts a temporary local server and uses isolated SQLite and session storage. See [the project reference](/docs/reference) for command descriptions and limits.
