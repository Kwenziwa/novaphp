<?php

declare(strict_types=1);

use Nova\Database\{Commands, Connection, Field, Migrator, Schema};

function schemaReject(Closure $operation, string $label): void
{
    try { $operation(); } catch (InvalidArgumentException) { check(true, $label); return; }
    throw new RuntimeException('Expected schema rejection: ' . $label);
}

$db = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
$schema = $db->schema();
$literal = "x'); DROP TABLE posts; -- \\ quote'";
$schema->create('posts', [
    'id' => Field::id(),
    'title' => Field::string(150),
    'slug' => Field::string(100)->unique(),
    'body' => Field::text()->optional(),
    'active' => Field::boolean()->defaultsTo(false),
    'views' => Field::integer()->defaultsTo(0),
    'price' => Field::decimal(10, 2)->defaultsTo(12.5),
    'published_at' => Field::datetime()->optional()->defaultsTo(null),
    'literal' => Field::string()->defaultsTo($literal),
]);
$db->execute('INSERT INTO posts (title, slug) VALUES (?, ?)', ['First post', 'first']);
$first = $db->first('SELECT * FROM posts');
check((int) $first['id'] === 1 && $first['title'] === 'First post', 'schema creates usable auto-increment table');
check($first['body'] === null && $first['published_at'] === null, 'schema optional fields and null defaults');
check((int) $first['active'] === 0 && (int) $first['views'] === 0 && (float) $first['price'] === 12.5, 'schema typed scalar defaults');
check($first['literal'] === $literal, 'DDL string defaults are quoted as literal data');
fails(fn () => $db->execute('INSERT INTO posts (title, slug) VALUES (?, ?)', ['Duplicate', 'first']), 'UNIQUE constraint');
fails(fn () => $db->execute('INSERT INTO posts (slug) VALUES (?)', ['missing-title']), 'NOT NULL constraint');

$schema->add('posts', ['summary' => Field::string()->optional(), 'rating' => Field::integer()->defaultsTo(1)]);
$row = $db->first('SELECT summary, rating FROM posts');
check($row['summary'] === null && (int) $row['rating'] === 1, 'schema adds fields to populated tables');
$schema->index('posts', ['title', 'rating'], unique: true, name: 'posts_title_rating');
fails(fn () => $db->execute('INSERT INTO posts (title, slug) VALUES (?, ?)', ['First post', 'second']), 'UNIQUE constraint');
$schema->dropIndex('posts', 'posts_title_rating');
$db->execute('INSERT INTO posts (title, slug) VALUES (?, ?)', ['First post', 'second']);
check(count($db->select('SELECT * FROM posts')) === 2, 'dropping unique index allows repeated values');
$schema->remove('posts', 'summary');
check(!in_array('summary', array_column($db->select('PRAGMA table_info(posts)'), 'name'), true), 'schema removes fields');
$schema->rename('posts', 'articles');
check(count($db->select('SELECT * FROM articles')) === 2, 'schema rename preserves data');
$schema->drop('articles');
$schema->dropIfExists('articles');
check($db->first("SELECT name FROM sqlite_master WHERE name = 'articles'") === null, 'schema drop and dropIfExists');

$base = Field::string();
$optional = $base->optional();
check(!isset($base->definition()['nullable']) && $optional->definition()['nullable'], 'field modifiers do not mutate reusable definitions');
schemaReject(fn () => Field::string(0), 'schema rejects invalid string lengths');
schemaReject(fn () => Field::decimal(2, 3), 'schema rejects invalid decimal scale');
schemaReject(fn () => Field::id()->optional(), 'schema rejects ID modifiers');
schemaReject(fn () => Field::integer()->defaultsTo(INF), 'schema rejects non-finite defaults');
schemaReject(fn () => Field::string()->defaultsTo("bad\0value"), 'schema rejects null bytes in SQL literals');
schemaReject(fn () => $schema->create('bad; DROP TABLE x', ['id' => Field::id()]), 'schema rejects unsafe table identifiers');
schemaReject(fn () => $schema->create('empty', []), 'schema rejects empty tables');
schemaReject(fn () => $schema->create('invalid', ['id', Field::id()]), 'schema requires named Field descriptions');
schemaReject(fn () => $schema->create('invalid', ['Name' => Field::text(), 'name' => Field::text()]), 'schema rejects duplicate column names');
schemaReject(fn () => $schema->create('invalid', ['id' => Field::id(), 'other' => Field::id()]), 'schema rejects multiple IDs');
schemaReject(fn () => $schema->create('invalid', ['value' => Field::text()->defaultsTo(null)]), 'null default requires an optional field');
$schema->create('safe', ['id' => Field::id()]);
schemaReject(fn () => $schema->add('safe', ['first' => Field::text()->optional(), 'second' => Field::id()]), 'add validates all descriptions before executing DDL');
check(count($db->select('PRAGMA table_info(safe)')) === 1, 'invalid add does not partially add earlier fields');
schemaReject(fn () => $schema->add('safe', ['value' => Field::string()]), 'added required columns need defaults');
schemaReject(fn () => $schema->index('safe', []), 'empty index rejected');
schemaReject(fn () => $schema->drop('safe; --'), 'unsafe drop identifier rejected');

$mysql = (new Connection(['driver' => 'mysql']))->schema();
$sql = $mysql->createSql('items', ['id' => Field::id(), 'label' => Field::string(120), 'enabled' => Field::boolean()->defaultsTo(false), 'amount' => Field::decimal(12, 2)]);
check(str_contains($sql, '`id` BIGINT PRIMARY KEY AUTO_INCREMENT') && str_contains($sql, 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4') && str_contains($sql, '`enabled` BOOLEAN NOT NULL DEFAULT 0'), 'schema compiles MySQL auto-increment engine and boolean defaults without connecting');

// Generate and execute both styles through the same migrator, with raw SQL seeding a schema-created table.
$directory = sys_get_temp_dir() . '/nova-schema-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
try {
    $migrationDb = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
    $runner = new Migrator($migrationDb, $directory);
    $commands = new Commands($runner, $directory);
    ob_start();
    try {
        $commands->run('make:migration', 'create_schema_posts');
        $commands->run('make:migration', 'create_sql_posts', sql: true);
    } finally { ob_end_clean(); }
    $files = glob($directory . '/*.php');
    check(count($files) === 2, 'generator creates schema and SQL starters');
    foreach ($files as $file) {
        $source = file_get_contents($file);
        if (str_contains($file, '_create_schema_posts.php')) {
            check(str_contains($source, "schema()->create('schema_posts'") && str_contains($source, 'Field::id()'), 'schema generator infers table from create name');
        } else {
            check(str_contains($source, 'CREATE TABLE sql_posts') && !str_contains($source, 'Field::'), 'SQL generator produces a raw SQL migration');
        }
    }
    check(count($runner->migrate()) === 2, 'generated schema and raw migrations both execute');
    $migrationDb->execute('INSERT INTO schema_posts DEFAULT VALUES');
    check((int) $migrationDb->first('SELECT id FROM schema_posts')['id'] === 1, 'schema-created table accepts raw SQL queries');
    check(count($runner->rollback()) === 2 && $runner->migrate() !== [], 'both generated styles roll back and reapply');
    $example = require dirname(__DIR__) . '/examples/migrations/create_posts.php';
    $example->up($migrationDb);
    check($migrationDb->first('SELECT title FROM posts')['title'] === 'Welcome', 'example combines schema creation and bound SQL seeding');
    $example->down($migrationDb);
    check($migrationDb->first("SELECT name FROM sqlite_master WHERE name = 'posts'") === null, 'example reverses its schema');
} finally {
    foreach (glob($directory . '/*') ?: [] as $file) { unlink($file); }
    if (is_file($directory . '/.nova-migrate.lock')) { unlink($directory . '/.nova-migrate.lock'); }
    rmdir($directory);
}

$cli = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/nova');
exec($cli . ' make:migration create_cli_schema_test --typo 2>&1', $ignored, $status);
check($status === 1, 'CLI rejects unknown migration generator flags');
$output = [];
exec($cli . ' make:migration create_cli_schema_test --sql', $output, $status);
$generated = dirname(__DIR__) . '/' . substr($output[0] ?? '', strlen('Created '));
check($status === 0 && is_file($generated), 'CLI accepts SQL generator option');
try {
    $migration = require $generated;
    $cliDb = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
    $migration->up($cliDb);
    check($cliDb->first("SELECT name FROM sqlite_master WHERE name = 'cli_schema_test'") !== null, 'CLI SQL starter creates inferred table');
    $migration->down($cliDb);
} finally { if (is_file($generated)) { unlink($generated); } }
