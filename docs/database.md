# Database

Database services are lazy. The clean starter has no application migrations and runs without creating a database. Add a model and migration when your application needs persistence.

## Configure PDO

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=storage/database.sqlite
```

SQLite paths may be absolute, relative to the project root, or :memory:. The storage directory must be writable. Database files and sidecars are ignored by Git and are never copied into generated projects. Enable pdo_sqlite, or configure MySQL with pdo_mysql:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_app
DB_USERNAME=my_app
DB_PASSWORD='your local password'
```

Create your MySQL database/user separately. Connections use exceptions, associative rows, and native prepares. SQLite enables foreign keys and a busy timeout. MySQL uses utf8mb4. Do not share config:show output containing credentials.

## Queries

Inject Nova\Database\Connection into a controller or repository:

```php
$rows = $database->select('SELECT * FROM posts WHERE title = ?', [$title]);
$row = $database->first('SELECT * FROM posts WHERE id = :id', ['id' => $id]);
$changed = $database->execute('UPDATE posts SET title = ? WHERE id = ?', [$title, $id]);
$id = $database->lastInsertId();
```

select returns rows, first returns one row or null, execute returns the PDO affected-row count, and lastInsertId returns a string. Bindings accept scalar values or null. Use unique named placeholders within a statement. Table/column names and sort directions must come from trusted code or an allowlist, not value placeholders.

## Transactions

```php
$result = $database->transaction(function (Nova\Database\Connection $database) {
    $database->execute('INSERT INTO posts (title) VALUES (?)', ['Example']);
    return $database->lastInsertId();
});
```

The callback result is returned on commit; failures roll back and rethrow. Nested transactions are rejected. Avoid DDL in application transactions: MySQL schema statements can commit implicitly. SQLite migrations are transactional per migration; MySQL migration failures can leave partial schema changes. See [migrations](/docs/migrations) and [PDO transaction guidance](https://www.php.net/manual/en/pdo.transactions.php).

Use [Nova Records](/docs/models) for model queries or pdo() for driver-specific operations.
