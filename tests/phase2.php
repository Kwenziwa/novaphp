<?php

declare(strict_types=1);

use Nova\Core\Application;
use Nova\Core\Container;
use Nova\Core\ServiceProvider;
use Nova\Config\Env;
use Nova\Config\Repository;
use Nova\Http\Request;
use Nova\Routing\Router;

function check(bool $condition, string $label): void
{
    if (!$condition) { throw new RuntimeException('FAIL ' . $label); }
    echo 'PASS ' . $label . "\n";
}
function fails(Closure $callback, string $message): void
{
    try { $callback(); } catch (RuntimeException $error) {
        check(str_contains($error->getMessage(), $message), $message);
        return;
    }
    throw new RuntimeException('Expected error: ' . $message);
}
interface Phase2Contract {}
final class Phase2Service implements Phase2Contract {}
final class Phase2Consumer
{
    public function __construct(public Phase2Contract $service, public string $label = 'default') {}
}
final class Phase2CycleA { public function __construct(Phase2CycleB $b) {} }
final class Phase2CycleB { public function __construct(Phase2CycleA $a) {} }
final class Phase2Scalar { public function __construct(string $required) {} }
final class Phase2Controller
{
    public function __construct(private Phase2Consumer $consumer) {}
    public function show(Request $request, string $name, Repository $config): array
    {
        return ['name' => $name, 'app' => $config->get('app.name'), 'path' => $request->path,
            'label' => $this->consumer->label];
    }
}
final class Phase2Provider extends ServiceProvider
{
    public static array $events = [];
    public function register(): void
    {
        self::$events[] = 'register';
        $this->app->container->singleton(Phase2Contract::class, Phase2Service::class);
    }
    public function boot(): void
    {
        self::$events[] = 'boot';
        $this->app->container->make(Phase2Contract::class);
    }
}
final class Phase2SecondProvider extends ServiceProvider
{
    public function register(): void { Phase2Provider::$events[] = 'second-register'; }
    public function boot(): void { Phase2Provider::$events[] = 'second-boot'; }
}

$c = new Container();
$c->bind(Phase2Contract::class, Phase2Service::class);
check($c->make(Phase2Consumer::class)->label === 'default', 'constructor autowiring and scalar defaults');
check($c->make(Phase2Contract::class) !== $c->make(Phase2Contract::class), 'transient binding');
$c->singleton(Phase2Contract::class, fn (Container $container) => new Phase2Service());
check($c->make(Phase2Contract::class) === $c->make(Phase2Contract::class), 'singleton factory');
$c->bind(Phase2Contract::class, Phase2Service::class);
check($c->make(Phase2Contract::class) !== $c->make(Phase2Contract::class), 'rebinding clears singleton');
$instance = new Phase2Service();
$c->instance(Phase2Contract::class, $instance);
check($c->make(Phase2Consumer::class)->service === $instance, 'instance binding');
$c->instance('nullable', null);
check($c->make('nullable') === null, 'null instance');
fails(fn () => $c->make(Phase2CycleA::class), 'Circular dependency:');
fails(fn () => $c->make(Phase2Scalar::class), 'Cannot resolve');
fails(fn () => $c->make('MissingPhase2Class'), 'No binding');
check($c->make(Phase2Service::class) instanceof Phase2Service, 'resolution stack recovers after failures');
$c->bind('recursive', fn (Container $c) => $c->make('recursive'));
fails(fn () => $c->make('recursive'), 'Circular dependency:');

$config = new Repository(['app' => ['null' => null, 'debug' => false]]);
check($config->get('app.null', 'fallback') === null, 'config preserves null');
check($config->get('app.missing', 'fallback') === 'fallback', 'config default');
$config->set('database.host', 'localhost');
check($config->get('database.host') === 'localhost', 'nested config set');

$temp = tempnam(sys_get_temp_dir(), 'nova-env-');
try {
    putenv('NOVA_TEST_EXISTING=external');
    file_put_contents($temp, "NOVA_TEST_EXISTING=local\nNOVA_TEST_LITERAL='\$(echo unsafe)'\nNOVA_TEST_HASH=abc#def # comment\nNOVA_TEST_EMPTY=\n");
    Env::load($temp);
    check(getenv('NOVA_TEST_EXISTING') === 'external', 'environment precedence');
    check(getenv('NOVA_TEST_LITERAL') === '$(echo unsafe)', 'environment values remain literal');
    check(getenv('NOVA_TEST_HASH') === 'abc#def' && getenv('NOVA_TEST_EMPTY') === '', 'environment comments and empty values');
    file_put_contents($temp, "NOVA_TEST_PARTIAL=must-not-load\nBROKEN\n");
    fails(fn () => Env::load($temp), 'Invalid environment entry at line 2');
    check(getenv('NOVA_TEST_PARTIAL') === false, 'invalid environment files apply no partial changes');
    file_put_contents($temp, "NOVA_TEST_QUOTE='unterminated\n");
    fails(fn () => Env::load($temp), 'Invalid quoted environment value');
} finally {
    unlink($temp);
    foreach (['EXISTING', 'LITERAL', 'HASH', 'EMPTY', 'PARTIAL', 'QUOTE'] as $suffix) {
        putenv('NOVA_TEST_' . $suffix);
        unset($_ENV['NOVA_TEST_' . $suffix]);
    }
}

$application = new Application(dirname(__DIR__));
$application->register(Phase2Provider::class);
$application->register(Phase2SecondProvider::class);
$application->register(Phase2Provider::class);
$application->boot();
$application->boot();
check(Phase2Provider::$events === ['register', 'second-register', 'boot', 'second-boot'], 'providers register before boot and run once');
check($application->container->make(Application::class) === $application, 'application core instances');
$application->router->get('/phase2/{name}', [Phase2Controller::class, 'show']);
$response = $application->handle(new Request('GET', '/phase2/Ada'));
check($response->status === 200 && json_decode($response->body, true) === [
    'name' => 'Ada', 'app' => 'NovaPHP', 'path' => '/phase2/Ada', 'label' => 'default',
], 'controller and handler injection preserve route parameters');
$router = new Router();
$router->get('/legacy', fn (Request $request) => 'legacy');
check($router->dispatch(new Request('GET', '/legacy'))->body === 'legacy', 'standalone router compatibility');

$router->get('/optional', fn (?Phase2Contract $service = null) => $service === null ? 'default' : 'injected');
check($router->dispatch(new Request('GET', '/optional'))->body === 'default', 'Phase 1 optional handler defaults');

$cli = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/nova');
exec($cli . ' config:show app.name', $output, $status);
check($status === 0 && implode('', $output) === '"NovaPHP"', 'CLI config inspection');
exec($cli . ' make:controller ../EscapeController 2>&1', $ignored, $status);
check($status === 1, 'CLI rejects controller path traversal');
$name = 'NovaTest' . bin2hex(random_bytes(4)) . 'Controller';
$path = dirname(__DIR__) . '/app/Controllers/' . $name . '.php';
try {
    exec($cli . ' make:controller ' . escapeshellarg($name), $created, $status);
    check($status === 0 && is_file($path), 'CLI creates controller');
    require $path;
    $class = 'App\\Controllers\\' . $name;
    check((new $class())->index() === 'Hello from ' . $name, 'generated controller is executable');
    $before = file_get_contents($path);
    exec($cli . ' make:controller ' . escapeshellarg($name) . ' 2>&1', $ignored, $status);
    check($status === 1 && file_get_contents($path) === $before, 'CLI refuses overwrite');
} finally {
    if (is_file($path)) { unlink($path); }
}
