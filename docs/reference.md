# CLI and project reference

## Commands

| Command | Purpose |
| --- | --- |
| php nova new my-app | Choose an empty application or frontend stack |
| php nova new my-app --stack=vue | Generate a selected stack without prompting |
| php nova serve | Start the local PHP development server |
| php nova route:list | List HTTP methods and paths |
| php nova make:controller PostController | Generate an application controller |
| php nova make:model Post | Generate a model; optionally add --table=posts |
| php nova make:migration create_posts | Generate a field-map migration |
| php nova make:migration create_posts --sql | Generate a raw SQL migration |
| php nova migrate | Apply pending application migrations |
| php nova migrate:status | Show migration state |
| php nova migrate:rollback | Reverse the latest migration batch |
| php nova config:show app.name | Inspect configuration; omit key for all values |

## Project files

| Location | Responsibility |
| --- | --- |
| src | Framework services and contracts |
| app/Controllers | Application HTTP controllers |
| app/Models | Application Nova Records models |
| app/Providers | Application service providers |
| bootstrap/app.php | Core/application provider bootstrap and routes |
| config | Application configuration |
| database/migrations | Application-owned schema changes |
| docs | Editable Markdown documentation |
| resources/views | PHP templates |
| public | Web server document root |
| routes | Browser and API route registration |
| stubs | Clean base and frontend blueprints |
| storage | Local database and runtime data |
| tests | Framework regression suite or generated-project smoke test |

## Testing the source repository

```bash
php tests/run.php
php tests/http.php
```

Regression tests use temporary databases/projects. Native HTTP tests use a test-only demo router with isolated session storage, not public starter demo routes. The PHP CI matrix covers 8.2, 8.3, 8.4, and 8.5. Frontend CI generates each stack, runs npm ci, and builds it. MySQL CI runs against a dedicated service database. To run the dedicated MySQL test locally, set NOVA_MYSQL_TEST=1 and NOVA_MYSQL_DATABASE to an empty database ending in _test, with the host/port/username/password variables described in tests/mysql.php. It refuses non-test or non-empty databases.

Generated projects get their own smoke test and no framework test fixtures. Add your application's tests independently. Never point destructive migration/rollback tests at a real application database.

## Documentation and limits

App\Documentation\Library is the allowlisted page catalog. The renderer supports headings, paragraphs, fenced/inline code, bold, flat lists, tables, and HTTP(S)/docs/anchor links; raw HTML is escaped. It is a small documentation subset, not full CommonMark. Static docs need no frontend build.

NovaPHP remains early-development software. Global installer/package publication, optional account scaffolds, advanced ORM features, a general policy system, account recovery, email verification, MFA, and a complete production security model remain future work. Keep debug disabled and use public as the document root when deploying.
