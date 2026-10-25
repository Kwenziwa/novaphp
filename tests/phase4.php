<?php

declare(strict_types=1);

use Nova\Database\{Connection, Migrator};
use Nova\Http\Request;
use Nova\Session\Session;
use App\Repositories\NoteRepository;

$db = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
check($db->pdo() === $db->pdo(), 'database shares lazy PDO connection');
$db->execute('CREATE TABLE bindings (id INTEGER PRIMARY KEY, text_value TEXT, nullable TEXT, enabled INTEGER)');
$attack = "'); DROP TABLE bindings; --";
$db->execute('INSERT INTO bindings VALUES (?, ?, ?, ?)', [1, $attack, null, true]);
$row = $db->first('SELECT * FROM bindings WHERE id = :id', ['id' => 1]);
check($row['text_value'] === $attack && $row['nullable'] === null && (int) $row['enabled'] === 1, 'prepared bindings preserve injection text null and boolean');
check($db->first('SELECT * FROM bindings WHERE id = ?', [999]) === null, 'database first returns null when absent');
check($db->transaction(function (Connection $db): string {
    $db->execute('INSERT INTO bindings (id) VALUES (?)', [2]);
    return 'committed';
}) === 'committed', 'transaction commits and returns callback result');
try {
    $db->transaction(function (Connection $db): void {
        $db->execute('INSERT INTO bindings (id) VALUES (?)', [3]);
        throw new RuntimeException('rollback');
    });
} catch (RuntimeException $error) { check($error->getMessage() === 'rollback', 'transaction preserves original failure'); }
check($db->first('SELECT * FROM bindings WHERE id = 3') === null, 'transaction rolls back writes');
fails(fn () => $db->transaction(fn (Connection $db) => $db->transaction(fn () => null)), 'Nested transactions');
check(!$db->pdo()->inTransaction(), 'failed nested transaction rolls back outer transaction');
fails(fn () => (new Connection(['driver' => 'unsupported']))->pdo(), 'Supported database drivers');

$directory = sys_get_temp_dir() . '/nova-migrations-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
$fileA = $directory . '/001_first.php';
$fileB = $directory . '/002_second.php';
$fixture = static function (string $table): string {
    return '<?php return new class implements \\Nova\\Database\\Migration {'
        . ' public function up(\\Nova\\Database\\Connection $db): void { $db->execute("CREATE TABLE ' . $table . ' (id INTEGER)"); }'
        . ' public function down(\\Nova\\Database\\Connection $db): void { $db->execute("DROP TABLE ' . $table . '"); }};';
};
try {
    file_put_contents($fileA, $fixture('first_table'));
    $migrator = new Migrator($db, $directory);
    check($migrator->status()[0]['status'] === 'pending', 'migration status shows pending');
    check($migrator->migrate() === ['001_first'] && $migrator->migrate() === [], 'migrations apply exactly once');
    file_put_contents($fileB, $fixture('second_table'));
    check($migrator->migrate() === ['002_second'] && $migrator->status()[1]['batch'] === 2, 'later migrations receive next batch');
    check($migrator->rollback() === ['002_second'] && $migrator->status()[0]['status'] === 'applied', 'rollback affects only latest batch');
    check($migrator->rollback() === ['001_first'] && $migrator->rollback() === [], 'rollback reaches empty history');
    check($migrator->migrate() === ['001_first', '002_second'], 'migrations reapply in sorted order');
    unlink($fileB);
    check($migrator->status()[1]['file_exists'] === false, 'status identifies missing applied migration');
    fails(fn () => $migrator->rollback(), 'Missing migration file');
    check(count($db->select('SELECT * FROM nova_migrations')) === 2, 'missing file prevents partial batch rollback');
    file_put_contents($fileB, $fixture('second_table'));
    check($migrator->rollback() === ['002_second', '001_first'], 'batch rolls back in reverse order');
    file_put_contents($fileB, '<?php return new class implements \\Nova\\Database\\Migration { public function up(\\Nova\\Database\\Connection $db): void { $db->execute("CREATE TABLE failed_table (id INTEGER)"); throw new \\RuntimeException("failed migration"); } public function down(\\Nova\\Database\\Connection $db): void {} };');
    fails(fn () => $migrator->migrate(), 'failed migration');
    check($db->first("SELECT name FROM sqlite_master WHERE name = 'failed_table'") === null, 'failed SQLite migration rolls back schema');
    check(count($db->select('SELECT * FROM nova_migrations')) === 1, 'failed migration not recorded while earlier migrations remain applied');
    file_put_contents($fileB, $fixture('second_table'));
    check($migrator->migrate() === ['002_second'], 'migration failure can be retried');
} finally {
    foreach (glob($directory . '/*') ?: [] as $file) { unlink($file); }
    if (is_file($directory . '/.nova-migrate.lock')) { unlink($directory . '/.nova-migrate.lock'); }
    rmdir($directory);
}

$notesDb = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
(new Migrator($notesDb, dirname(__DIR__) . '/database/migrations'))->migrate();
$notesDb->execute('INSERT INTO users (name, email, password_hash, created_at) VALUES (?, ?, ?, ?)', ['Fixture', 'fixture@example.test', 'unused-fixture-hash', gmdate('Y-m-d H:i:s')]);
$fixtureUser = (int) $notesDb->lastInsertId();
$fixtureSession = new Session(native: false);
$fixtureSession->start();
$fixtureSession->put('_auth', ['id' => $fixtureUser, 'issued' => time(), 'seen' => time()]);
$fixtureAuth = new \Nova\Auth\Auth($notesDb, $fixtureSession, new \Nova\Config\Repository(), new \Nova\Auth\AttemptLimiter($notesDb));
$repository = new NoteRepository($notesDb, $fixtureAuth);
$id = $repository->create(['title' => $attack, 'body' => '<script>alert(1)</script>']);
check($repository->find($id)['title'] === $attack && count($repository->all()) === 1, 'notes repository create and read');
$repository->update($id, ['title' => 'Updated', 'body' => 'Body']);
check($repository->find($id)['title'] === 'Updated', 'notes repository update');
check($repository->delete($id) === 1 && $repository->find($id) === null, 'notes repository delete');

$demo = require dirname(__DIR__) . '/bootstrap/app.php';
$demo->container->instance(Connection::class, $notesDb);
$demoSession = new Session(native: false);
$demoSession->start();
$demoSession->put('_auth', ['id' => $fixtureUser, 'issued' => time(), 'seen' => time()]);
$demoSession->close();
$demo->container->instance(Session::class, $demoSession);
$form = $demo->handle(new Request('GET', '/notes'));
preg_match('/name="_token" value="([a-f0-9]+)"/', $form->body, $match);
$token = $match[1];
check($form->status === 200, 'notes index form renders');
check($demo->handle(new Request('POST', '/notes', body: ['title' => 'blocked']))->status === 419, 'notes creation requires CSRF');
$invalid = $demo->handle(new Request('POST', '/notes', body: ['_token' => $token, 'title' => '<img>', 'body' => '']));
check($invalid->status === 422 && str_contains($invalid->body, '&lt;img&gt;') && count($repository->all()) === 0, 'invalid notes show escaped input and do not persist');
$created = $demo->handle(new Request('POST', '/notes', body: ['_token' => $token, 'title' => '<b>Title</b>', 'body' => $attack]));
check($created->status === 303, 'notes create redirects');
$note = $repository->all()[0];
$id = (int) $note['id'];
check(str_contains($demo->handle(new Request('GET', '/notes'))->body, '&lt;b&gt;Title&lt;/b&gt;'), 'stored note HTML is escaped');
check($demo->handle(new Request('GET', "/notes/$id/edit"))->status === 200, 'notes edit form renders');
$updated = $demo->handle(new Request('POST', "/notes/$id", body: ['_token' => $token, 'title' => 'Changed', 'body' => 'New body']));
check($updated->status === 303 && $repository->find($id)['title'] === 'Changed', 'notes update persists');
check($demo->handle(new Request('POST', "/notes/$id/delete"))->status === 419 && $repository->find($id) !== null, 'notes delete requires CSRF');
check($demo->handle(new Request('POST', "/notes/$id/delete", body: ['_token' => $token]))->status === 303 && $repository->find($id) === null, 'notes delete persists');
check($demo->handle(new Request('GET', '/notes/999/edit'))->status === 404, 'missing note returns 404');
check($demo->handle(new Request('POST', '/notes/not-an-id', body: ['_token' => $token]))->status === 404, 'malformed note ID returns 404');
$json = $demo->handle(new Request('POST', '/notes', body: ['_token' => $token], headers: ['accept' => 'application/json']));
check($json->status === 422 && isset(json_decode($json->body, true)['errors']['title']), 'notes JSON validation errors');

// Exercise CLI commands against a temporary file database, never the developer database.
$tempDb = tempnam(sys_get_temp_dir(), 'nova-cli-db-');
$cli = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/nova');
$previous = ['DB_CONNECTION' => getenv('DB_CONNECTION'), 'DB_DATABASE' => getenv('DB_DATABASE')];
try {
    putenv('DB_CONNECTION=sqlite');
    putenv('DB_DATABASE=' . $tempDb);
    $output = [];
    exec($cli . ' migrate', $output, $status);
    check($status === 0 && str_contains(implode('', $output), 'Migrated:'), 'CLI migrate');
    $output = [];
    exec($cli . ' migrate:status', $output, $status);
    check($status === 0 && str_contains(implode('', $output), 'applied'), 'CLI migration status');
    $output = [];
    exec($cli . ' migrate', $output, $status);
    check($status === 0 && implode('', $output) === 'Nothing to do.', 'CLI repeat migrate is a no-op');
    $output = [];
    exec($cli . ' migrate:rollback', $output, $status);
    check($status === 0 && str_contains(implode('', $output), 'Rolled back:'), 'CLI migration rollback');
    exec($cli . ' make:migration ../escape 2>&1', $ignored, $status);
    check($status === 1, 'CLI rejects migration path traversal');
    $output = [];
    exec($cli . ' make:migration nova_test_fixture', $output, $status);
    $generated = dirname(__DIR__) . '/' . substr($output[0] ?? '', strlen('Created '));
    check($status === 0 && is_file($generated), 'CLI migration generator creates file');
    try { check((require $generated) instanceof \Nova\Database\Migration, 'generated migration implements interface'); }
    finally { if (is_file($generated)) { unlink($generated); } }
} finally {
    foreach ($previous as $key => $value) { putenv($value === false ? $key : $key . '=' . $value); }
    unlink($tempDb);
}
