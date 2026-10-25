<?php

declare(strict_types=1);

use Nova\Auth\{Auth, AttemptLimiter, DatabaseUserProvider, UserProvider};
use Nova\Config\Repository;
use Nova\Database\{Connection, Field};
use Nova\Http\{HttpException, Request};
use Nova\Routing\Router;
use Nova\Session\Session;

$clean = require dirname(__DIR__) . '/bootstrap/app.php';
check($clean->handle(new Request('GET', '/'))->status === 200, 'clean starter runs without application migrations');
foreach (['/notes', '/contact', '/register', '/login', '/account'] as $path) {
    check($clean->handle(new Request('GET', $path))->status === 404, 'starter excludes demo route ' . $path);
}
check(glob(dirname(__DIR__) . '/database/migrations/*.php') === [], 'starter ships no application migrations');
fails(fn () => $clean->container->make(UserProvider::class), 'Configure auth.provider');

$router = new Router();
$router->group('/projects', [], fn (Router $router) => $router->get('/{id}', fn (string $id) => $id, name: 'projects.show'));
check($router->url('projects.show', ['id' => 'a/b'], ['page' => 2]) === '/projects/a%2Fb?page=2', 'named routes encode parameters and query strings');
fails(fn () => $router->url('projects.show'), 'Missing or invalid');
fails(fn () => $router->url('absent'), 'Unknown route');
fails(fn () => $router->get('/other', fn () => 'x', name: 'projects.show'), 'unique');
check(json_decode($router->dispatch(new Request('GET', '/absent', headers: ['accept' => 'application/json']))->body, true)['error'] === 'Not Found', 'JSON missing routes have a structured error');
$clean->router->get('/forbidden', fn () => throw new HttpException(403, 'Forbidden'));
check($clean->handle(new Request('GET', '/forbidden', headers: ['accept' => 'application/json']))->status === 403, 'HTTP exceptions become explicit responses');

$page = TestArticle::using($isolated)->paginate(1, 1);
check($page->total === 1 && $page->items[0]->title === 'Other connection' && $page->jsonSerialize()['meta']['last_page'] === 1, 'record pagination returns model items and metadata');
check(TestArticle::using($isolated)->paginate(1, 2)->items === [], 'out of range pages are empty');
schemaReject(fn () => TestArticle::using($isolated)->paginate(0), 'invalid pagination is rejected');

// A custom user store requires no users table, while the authentication contract stays unchanged.
final class MemoryIdentityProvider implements UserProvider
{
    public array $rows = [];
    public function findById(int $id): ?array { return $this->rows[$id] ?? null; }
    public function findByEmail(string $email): ?array { foreach ($this->rows as $row) { if ($row['email'] === $email) { return $row; } } return null; }
    public function create(string $name, string $email, #[SensitiveParameter] string $passwordHash): array
    {
        $id = count($this->rows) + 1;
        return $this->rows[$id] = ['id' => $id, 'name' => $name, 'email' => $email, 'password_hash' => $passwordHash, 'private' => 'never expose'];
    }
    public function rehash(int $id, #[SensitiveParameter] string $previousHash, #[SensitiveParameter] string $newHash): void { $this->rows[$id]['password_hash'] = $newHash; }
}
$identityDb = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
$identityDb->schema()->create('auth_attempts', ['key_hash' => Field::string(64)->unique(), 'attempts' => Field::integer(), 'window_started' => Field::integer()]);
$identitySession = new Session(native: false);
$identitySession->start();
$memory = new MemoryIdentityProvider();
$identityAuth = new Auth($memory, $identitySession, new Repository(), new AttemptLimiter($identityDb));
$user = $identityAuth->register('Independent', 'adapter@example.test', 'an independent passphrase', 'adapter');
check($user['email'] === 'adapter@example.test' && !isset($user['private']) && !isset($user['password_hash']), 'authentication supports a custom identity provider and sanitizes its output');
$identityAuth->logout();
check($identityAuth->attempt('adapter@example.test', 'an independent passphrase', 'adapter'), 'custom-provider authentication verifies passwords');

$storage = new Repository(['auth' => ['storage' => ['table' => 'members', 'id' => 'member_id', 'name' => 'display_name', 'email' => 'address', 'password_hash' => 'secret_hash', 'created_at' => null]]]);
$identityDb->schema()->create('members', ['member_id' => Field::id(), 'display_name' => Field::string(), 'address' => Field::string()->unique(), 'secret_hash' => Field::string()]);
$adapter = new DatabaseUserProvider($identityDb, $storage);
$member = $adapter->create('Member', 'member@example.test', password_hash('another adapter passphrase', PASSWORD_BCRYPT, ['cost' => 4]));
check($adapter->findByEmail('member@example.test')['id'] === $member['id'], 'SQL identity adapter maps custom table and column names');
