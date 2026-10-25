# Migrations

## Migration commands

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

## NovaPHP field maps

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

## Changing tables and adding indexes

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

## Raw queries remain available

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

Raw SQL is executed as written and must match your database dialect. Use bindings for data values. You can create a table with `schema()->create()` and then insert data with `execute()` in the same migration; use the complete field-map example above and add the bound insert in its `up()` method. Documentation code blocks are never executed by the browser preview. The starter installs no application migrations. Existing raw SQL migrations continue to work.

Pending files apply in filename order. `nova_migrations` tracks applied names, batches, and UTC timestamps. Repeating `migrate` skips applied files; each invocation with new migrations receives the next batch number. `migrate:rollback` reverses the latest batch in reverse filename order. It can delete tables/data through the migration's `down()` method. Status shows applied/pending migrations, batches, and missing files; it initializes the history table if needed. Do not edit or rename applied migrations—add a new migration instead.

A local file lock serializes migration commands using the same migration directory. It is not a distributed lock across servers or separate checkouts; coordinate deployments accordingly. The migration directory must be writable for the lock file. No automatic retry or destructive reset command is provided.

Each SQLite migration and its history update run in one transaction. A failure leaves earlier successful migrations applied and the failed migration pending. Rollbacks are also transactional per migration, rather than per batch. Missing files are detected before rolling back a batch. MySQL DDL can commit implicitly, so failed MySQL migrations may leave partial schema changes requiring manual repair; inspect status and schema before retrying. See [PDO transactions and implicit commits](https://www.php.net/manual/en/pdo.transactions.php).

SQLite regression and HTTP tests run against isolated databases. MySQL configuration and SQL support are included, but this phase has not been verified against a live MySQL server.
