<?php

// Destructive integration test. Requires an explicitly designated EMPTY *_test database.
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
use Nova\Database\{Connection, Migrator};
use Nova\Auth\{Auth, AttemptLimiter, DatabaseUserProvider};
use Nova\Config\Repository;
use Nova\Session\Session;
use Tests\Fixtures\Demo\Models\Note;

$name = getenv('NOVA_MYSQL_DATABASE') ?: '';
if (getenv('NOVA_MYSQL_TEST') !== '1' || !preg_match('/^[A-Za-z0-9_]+_test$/D', $name)) {
    throw new RuntimeException('Set NOVA_MYSQL_TEST=1 and an empty NOVA_MYSQL_DATABASE ending in _test.');
}
$db = new Connection(['driver' => 'mysql', 'database' => $name,
    'host' => getenv('NOVA_MYSQL_HOST') ?: '127.0.0.1', 'port' => getenv('NOVA_MYSQL_PORT') ?: 3306,
    'username' => getenv('NOVA_MYSQL_USERNAME') ?: 'root', 'password' => getenv('NOVA_MYSQL_PASSWORD') ?: '']);
if ($db->select('SHOW TABLES') !== []) { throw new RuntimeException('MySQL test database must be empty; refusing to touch existing tables.'); }
$runner = new Migrator($db, __DIR__ . '/Fixtures/Demo/database/migrations');
try {
    $runner->migrate();
    $session = new Session(native: false); $session->start();
    $config = new Repository();
    $auth = new Auth(new DatabaseUserProvider($db, $config), $session, $config, new AttemptLimiter($db));
    $user = $auth->register('MySQL fixture', 'mysql@example.test', 'a mysql fixture passphrase', 'test');
    $note = Note::using($db)->create(['title' => "Bound ' value", 'body' => 'MySQL', 'user_id' => $user['id']]);
    if (Note::using($db)->where('title', "Bound ' value")->take(1)->first()?->id !== $note->id) { throw new RuntimeException('Native MySQL binds/limit failed.'); }
    $note->title = 'Changed'; $note->save();
    if (Note::using($db)->paginate(1)->total !== 1) { throw new RuntimeException('MySQL pagination failed.'); }
    try { $db->transaction(function () use ($note): void { $note->delete(); throw new RuntimeException('rollback'); }); } catch (RuntimeException $error) { if ($error->getMessage() !== 'rollback') { throw $error; } }
    if (Note::using($db)->find($note->id) === null) { throw new RuntimeException('MySQL transaction rollback failed.'); }
    $runner->rollback();
    echo "MySQL migration, authentication, ORM, transaction and rollback tests passed.\n";
} finally {
    // Only these tables can have been created after the empty-database guard above.
    $db->execute('DROP TABLE IF EXISTS notes');
    $db->execute('DROP TABLE IF EXISTS auth_attempts');
    $db->execute('DROP TABLE IF EXISTS users');
    $db->execute('DROP TABLE IF EXISTS nova_migrations');
}
