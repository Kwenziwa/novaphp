# Database

## Run the notes demo

```bash
php nova migrate
php nova serve
```

Visit `/notes` to create, list, edit, and delete notes. The browser forms use sessions, CSRF protection, validation, escaped output, and flash messages. The list shows the latest 100 notes. Missing or malformed note IDs return 404. JSON requests to the list/create/update/delete endpoints receive JSON responses; mutations still require a session CSRF token. There is no authentication or ownership yet: this is a shared development demo, so use sample data only.

SQLite defaults to `storage/database.sqlite`. The tracked `storage/` directory must be writable. Relative SQLite paths are resolved from the project root; absolute paths and `:memory:` are also supported. Database files and SQLite sidecars are ignored by Git. Migrations run explicitly, never on HTTP requests or application bootstrap.

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=storage/database.sqlite
```

For an existing MySQL database, use:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nova
DB_USERNAME=nova
DB_PASSWORD='your local password'
```

Create the MySQL database/user separately and enable `pdo_mysql`; NovaPHP does not provision a server or database. Connections use utf8mb4, exception mode, associative fetches, and native prepared statements. SQLite enables foreign keys and a five-second busy timeout. The connection is lazy and shared through the DI container. Existing environment variables override `.env` as before.

## Queries and transactions

Inject `Nova\Database\Connection` into a controller or repository:

```php
$notes = $database->select('SELECT * FROM notes WHERE title = ?', [$title]);
$note = $database->first('SELECT * FROM notes WHERE id = :id', ['id' => $id]);
$affected = $database->execute('UPDATE notes SET title = ? WHERE id = ?', [$title, $id]);
$id = $database->lastInsertId();

$result = $database->transaction(function (Nova\Database\Connection $database) {
    $database->execute('INSERT INTO notes (title, body, created_at) VALUES (?, ?, ?)',
        ['Example', 'A note', gmdate('c')]);
    return $database->lastInsertId();
});
```

`select()` returns rows, `first()` returns a row or null, and `execute()` returns the PDO affected-row count (which can vary by driver for unchanged updates). `lastInsertId()` returns a string. Bindings support strings, numbers, booleans, and null, with positional or named placeholders. Values are bound separately from SQL; table names, column names, and sort directions must come from trusted code or an explicit allowlist. Use unique named placeholders in each statement. See [PDO prepared statements](https://www.php.net/manual/en/pdo.prepare.php).

`transaction()` commits the callback result or rolls back and rethrows on failure. Nested transactions are rejected; savepoints are not implemented. Keep transaction control inside this helper and avoid DDL in application transactions. MySQL transactions require a transactional storage engine such as InnoDB.

`App\Repositories\NoteRepository` contains the demo's SQL, separate from validation and HTTP rendering. There is no ORM or query builder in this phase. `pdo()` remains available for driver-specific operations.
