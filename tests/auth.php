<?php

declare(strict_types=1);

use Nova\Auth\{Auth, AttemptLimiter, RateLimitException};
use Nova\Config\Repository;
use Nova\Database\{Connection, Migrator};
use Nova\Http\Request;
use Nova\Session\Session;
use Nova\Validation\ValidationException;
use Tests\Fixtures\Demo\Models\User;
use Tests\Fixtures\Demo\Repositories\NoteRepository;

$authDb = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
$firstMigration = require __DIR__ . '/Fixtures/Demo/database/migrations/2026_10_25_000001_create_notes.php';
$firstMigration->up($authDb);
$authDb->execute('INSERT INTO notes (title, body, created_at) VALUES (?, ?, ?)', ['Legacy', 'Unowned', gmdate('c')]);
$ownershipMigration = require __DIR__ . '/Fixtures/Demo/database/migrations/2026_10_25_000002_add_auth_and_note_ownership.php';
$ownershipMigration->up($authDb);
check($authDb->first('SELECT user_id FROM notes WHERE id = 1')['user_id'] === null, 'ownership migration preserves legacy notes without assigning an owner');
$ownershipMigration->down($authDb);
check($authDb->first('SELECT title FROM notes WHERE id = 1')['title'] === 'Legacy', 'ownership rollback preserves existing note data');
$ownershipMigration->up($authDb);

$authConfig = new Repository(['auth' => ['idle_seconds' => 1800, 'absolute_seconds' => 43200]]);
$aliceSession = new Session(native: false);
$aliceSession->start();
$aliceSession->put('previous-user-data', 'must disappear');
$beforeToken = $aliceSession->token();
$limiter = new AttemptLimiter($authDb);
$aliceAuth = new Auth(new \Nova\Auth\DatabaseUserProvider($authDb, $authConfig), $aliceSession, $authConfig, $limiter);
$alice = $aliceAuth->register('Alice', ' ALICE@EXAMPLE.TEST ', 'an original alice passphrase', 'alice-test');
$row = $authDb->first('SELECT * FROM users WHERE id = ?', [$alice['id']]);
check($alice['email'] === 'alice@example.test' && !isset($alice['password_hash']), 'registration normalizes email and returns only safe user fields');
check($row['password_hash'] !== 'an original alice passphrase' && password_verify('an original alice passphrase', $row['password_hash']), 'registration stores a verified password hash');
check($aliceSession->get('previous-user-data') === null && $aliceSession->token() !== $beforeToken, 'registration clears prior session data and rotates CSRF token');
check(!isset(User::using($authDb)->find($alice['id'])->toArray()['password_hash']), 'user model hides password hashes');
$afterRegistration = $aliceSession->token();
try { $aliceAuth->register('Duplicate', 'alice@example.test', 'an original alice passphrase', 'duplicate-test'); throw new RuntimeException('Expected duplicate rejection'); }
catch (ValidationException $error) { check(isset($error->errors['email']) && $authDb->first('SELECT COUNT(*) AS n FROM users')['n'] === 1, 'duplicate registration is rejected without a second user'); }
foreach (['short', str_repeat('x', 73), "long enough password\0invalid"] as $index => $password) {
    try { $aliceAuth->register('Invalid', 'invalid' . $index . '@example.test', $password, 'invalid-' . $index); throw new RuntimeException('Expected password rejection'); }
    catch (ValidationException $error) { check(isset($error->errors['password']), 'registration rejects invalid password ' . $index); }
}

$bobSession = new Session(native: false);
$bobSession->start();
$bobAuth = new Auth(new \Nova\Auth\DatabaseUserProvider($authDb, $authConfig), $bobSession, $authConfig, new AttemptLimiter($authDb));
$bob = $bobAuth->register('Bob', 'bob@example.test', 'a separate bob passphrase', 'bob-test');
$aliceNotes = new NoteRepository($authDb, $aliceAuth);
$bobNotes = new NoteRepository($authDb, $bobAuth);
$id = $aliceNotes->create(['title' => 'Private', 'body' => 'Alice only', 'user_id' => $bob['id']]);
check($authDb->first('SELECT user_id FROM notes WHERE id = ?', [$id])['user_id'] === $alice['id'], 'note creation ignores a forged owner and assigns the current user');
check(count($aliceNotes->all()) === 1 && $bobNotes->all() === [], 'note lists exclude other owners and unowned legacy notes');
check($bobNotes->find($id) === null && $bobNotes->find(1) === null, 'note lookup hides cross-user and unowned records');
$bobNotes->update($id, ['title' => 'Stolen', 'body' => 'No']);
check($bobNotes->delete($id) === 0 && $aliceNotes->find($id)['title'] === 'Private', 'cross-user update and delete have no effect');
$aliceNotes->update($id, ['title' => 'Still private', 'body' => 'Safe', 'user_id' => $bob['id']]);
check($aliceNotes->find($id)['user_id'] === $alice['id'], 'note updates cannot transfer ownership through input');

$bobNote = $bobNotes->create(['title' => 'Bob only', 'body' => 'Cascade']);
$authDb->execute('DELETE FROM users WHERE id = ?', [$bob['id']]);
check($authDb->first('SELECT id FROM notes WHERE id = ?', [$bobNote]) === null, 'user foreign key cascades deletion only to owned notes');
check($authDb->first('SELECT id FROM notes WHERE id = 1') !== null && $aliceNotes->find($id) !== null, 'user deletion preserves unrelated and legacy notes');
check($bobAuth->user() === null, 'deleting an account invalidates its authenticated session');

$aliceAuth->logout();
check($aliceAuth->user() === null && $aliceSession->token() !== $afterRegistration, 'logout removes authentication and rotates CSRF');
fails(fn () => $aliceNotes->all(), 'Authentication required');
check(!$aliceAuth->attempt('alice@example.test', 'wrong password', 'login-test'), 'invalid password is rejected');
check(!$aliceAuth->attempt('missing@example.test', 'wrong password', 'login-test'), 'unknown email is rejected');
check($aliceAuth->attempt('ALICE@EXAMPLE.TEST', 'an original alice passphrase', 'login-test'), 'login verifies credentials with case-normalized email');
$authDb->execute('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash('an original alice passphrase', PASSWORD_BCRYPT, ['cost' => 4]), $alice['id']]);
$aliceAuth->logout();
check($aliceAuth->attempt('alice@example.test', 'an original alice passphrase', 'rehash-test'), 'login accepts an existing valid lower-cost hash');
check(password_get_info($authDb->first('SELECT password_hash FROM users WHERE id = ?', [$alice['id']])['password_hash'])['options']['cost'] === 12, 'successful login upgrades password hash cost');
$aliceSession->put('_auth', ['id' => $alice['id'], 'issued' => time() - 1900, 'seen' => time() - 1801]);
check($aliceAuth->user() === null, 'idle sessions expire');
$aliceSession->put('_auth', ['id' => $alice['id'], 'issued' => time() - 43201, 'seen' => time()]);
check($aliceAuth->user() === null, 'absolute session lifetime expires despite recent activity');
$aliceSession->put('_auth', ['id' => $alice['id'], 'issued' => time() + 100, 'seen' => time() + 100]);
check($aliceAuth->user() === null, 'malformed future-dated auth state is rejected');
$aliceSession->put('_auth', ['id' => 9999, 'issued' => time(), 'seen' => time()]);
check($aliceAuth->user() === null, 'deleted or missing users lose authentication');

$limiter->consume(['unit-limit' => 2]);
(new AttemptLimiter($authDb))->consume(['unit-limit' => 2]);
fails(fn () => (new AttemptLimiter($authDb))->consume(['unit-limit' => 2]), 'Too many attempts');
$authDb->execute('UPDATE auth_attempts SET window_started = ? WHERE key_hash = ?', [time() - 901, hash('sha256', 'unit-limit')]);
$limiter->consume(['unit-limit' => 2]);
check((int) $authDb->first('SELECT attempts FROM auth_attempts WHERE key_hash = ?', [hash('sha256', 'unit-limit')])['attempts'] === 1, 'throttle window resets after expiry');

$authApp = require __DIR__ . '/Fixtures/Demo/bootstrap.php';
$authApp->container->instance(Connection::class, $authDb);
$webSession = new Session(native: false);
$authApp->container->instance(Session::class, $webSession);
check($authApp->handle(new Request('GET', '/notes'))->headers['Location'] === '/login', 'guest browser note access redirects to login');
check($authApp->handle(new Request('GET', '/notes', headers: ['accept' => 'application/json']))->status === 401, 'guest JSON note access returns 401');
$csrf = json_decode($authApp->handle(new Request('GET', '/csrf-token'))->body, true)['csrf_token'];
check($authApp->handle(new Request('POST', '/login', body: ['email' => 'alice@example.test', 'password' => 'an original alice passphrase']))->status === 419, 'login requires CSRF');
$badConfirmation = $authApp->handle(new Request('POST', '/register', body: ['_token' => $csrf, 'name' => 'X', 'email' => 'x@example.test', 'password' => 'long enough passphrase', 'password_confirmation' => 'different']));
check($badConfirmation->status === 422 && !str_contains($badConfirmation->body, 'value="long enough passphrase"'), 'registration confirmation errors never repopulate passwords');
$authDb->execute('INSERT INTO auth_attempts (key_hash, attempts, window_started) VALUES (?, ?, ?)', [hash('sha256', 'login:account:limited@example.test'), 5, time()]);
$limited = $authApp->handle(new Request('POST', '/login', body: ['_token' => $csrf, 'email' => 'limited@example.test', 'password' => 'bad'], headers: ['accept' => 'application/json']));
check($limited->status === 429 && $limited->headers['Retry-After'] === '900', 'login throttling returns 429 and retry header');
$authLogin = $authApp->handle(new Request('POST', '/login', body: ['_token' => $csrf, 'email' => 'alice@example.test', 'password' => 'an original alice passphrase'], headers: ['accept' => 'application/json']));
$loginData = json_decode($authLogin->body, true);
check($authLogin->status === 200 && isset($loginData['csrf_token']) && !str_contains($authLogin->body, 'password_hash'), 'JSON login returns safe account and rotated CSRF token');
check($authApp->handle(new Request('GET', '/account'))->status === 200, 'authenticated account page renders');
check($authApp->handle(new Request('POST', '/logout', body: ['_token' => $csrf]))->status === 419, 'old pre-login CSRF token is rejected');
check($authApp->handle(new Request('GET', '/logout'))->status === 405, 'logout is POST only');
$logout = $authApp->handle(new Request('POST', '/logout', body: ['_token' => $loginData['csrf_token']], headers: ['accept' => 'application/json']));
check($logout->status === 200 && $authApp->handle(new Request('GET', '/account', headers: ['accept' => 'application/json']))->status === 401, 'web logout revokes protected account access');
