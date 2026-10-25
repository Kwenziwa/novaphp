<?php

declare(strict_types=1);

use Nova\Http\{Pipeline, Request, Response};
use Nova\Http\Middleware\{StartSession, VerifyCsrfToken};
use Nova\Core\{Application, Container};
use Nova\Routing\Router;
use Nova\Session\Session;
use Nova\Validation\{Validator, ValidationException};
use Nova\View\View;

$events = [];
$layer = static function (string $label) use (&$events): Closure {
    return static function (Request $r, Closure $next) use ($label, &$events): Response {
        $events[] = "$label-before";
        $response = $next($r);
        $events[] = "$label-after";
        return $response;
    };
};
$application = new Application(dirname(__DIR__));
$application->middleware([$layer('global')]);
$application->router->group('/admin', [$layer('group')], function (Router $r) use ($layer): void {
    $r->group('/users', [], fn (Router $r) => $r->get('/{id}', fn (string $id) => $id, [$layer('route')]));
});
check($application->handle(new Request('GET', '/admin/users/42'))->body === '42', 'nested group prefix and parameter');
check($events === ['global-before', 'group-before', 'route-before', 'route-after', 'group-after', 'global-after'], 'middleware wraps in registration order');
$application->router->get('/blocked', fn () => 'should not run', [fn () => Response::text('blocked', 403)]);
check($application->handle(new Request('GET', '/blocked'))->status === 403, 'middleware short circuit');
try { $application->router->group('/leaked', [], fn () => throw new RuntimeException('stop')); } catch (RuntimeException) {}
$application->router->get('/literal.json', fn () => 'literal');
check($application->handle(new Request('GET', '/literalXjson'))->status === 404, 'literal route metacharacters are escaped');
check($application->handle(new Request('GET', '/literal.json'))->status === 200, 'group state restored after exception');
$events = [];
check($application->handle(new Request('GET', '/none'))->status === 404 && $events === ['global-before', 'global-after'], 'global middleware wraps 404');
check($application->handle(new Request('POST', '/literal.json'))->status === 405, '405 preserved');

$session = new Session(native: false);
$session->start();
$session->put('persist', 'yes');
$session->flash('notice', 'once');
$token = $session->token();
check(strlen($token) === 64 && $session->token() === $token, 'random stable CSRF token');
$session->close();
$session->start();
check($session->get('persist') === 'yes' && $session->getFlash('notice') === 'once', 'session and flash survive next request');
$session->close();
$session->start();
check($session->getFlash('notice') === null, 'flash expires after one following request');
$session->regenerate();
check($session->token() !== $token, 'session regeneration rotates CSRF token');
$session->close();

$c = new Container();
$c->instance(Session::class, $session);
$stack = [StartSession::class, VerifyCsrfToken::class];
$terminal = fn (Request $r) => Response::text('accepted');
$session->start();
$token = $session->token();
$session->close();
foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
    check(Pipeline::run(new Request($method, '/form'), $stack, $terminal, $c)->status === 419, "CSRF blocks $method without token");
}
check(Pipeline::run(new Request('POST', '/form', body: ['_token' => $token]), $stack, $terminal, $c)->status === 200, 'CSRF accepts form token');
check(Pipeline::run(new Request('POST', '/form', headers: ['x-csrf-token' => $token]), $stack, $terminal, $c)->status === 200, 'CSRF accepts header token');
check(Pipeline::run(new Request('POST', '/form', query: ['_token' => $token]), $stack, $terminal, $c)->status === 419, 'CSRF ignores query token');
check(Pipeline::run(new Request('POST', '/form', body: ['_token' => []]), $stack, $terminal, $c)->status === 419, 'CSRF rejects array token');
check(Pipeline::run(new Request('HEAD', '/form'), $stack, $terminal, $c)->status === 200, 'CSRF allows safe methods');
try { Pipeline::run(new Request('GET', '/'), [StartSession::class], fn () => throw new RuntimeException('downstream'), $c); } catch (RuntimeException) {}
fails(fn () => $session->get('persist'), 'Use StartSession');

$validator = new Validator();
check($validator->validate(['age' => '18', 'ignored' => 'secret'], ['age' => 'required|integer|min:18|max:99']) === ['age' => '18'], 'validation returns only declared fields');
check($validator->validate(['zero' => 0, 'flag' => false, 'value' => null], ['zero' => 'required|integer', 'flag' => 'required|boolean', 'value' => 'nullable|string']) === ['zero' => 0, 'flag' => false, 'value' => null], 'validation handles zero false and nullable');
try {
    $validator->validate(['email' => [], 'age' => '17'], ['email' => 'required|email', 'age' => 'integer|min:18']);
    throw new RuntimeException('Expected validation failure');
} catch (ValidationException $error) {
    check(isset($error->errors['email'], $error->errors['age']), 'validation aggregates field errors');
}
try { $validator->validate([], ['email' => 'typo']); throw new RuntimeException('Expected invalid rule'); }
catch (InvalidArgumentException) { check(true, 'unknown rules fail explicitly'); }
$application->router->post('/validate', fn (Request $r) => $r->validate(['email' => 'required|email']));
check($application->handle(new Request('POST', '/validate', headers: ['accept' => 'application/json']))->status === 422, 'JSON validation response');
check(str_contains($application->handle(new Request('POST', '/validate'))->body, 'Validation failed'), 'HTML validation response');

$view = new View(__DIR__ . '/Fixtures/Demo/resources/views');
$html = $view->render('contact', ['title' => '<script>x</script>', 'token' => 'token', 'notice' => '<img>', 'errors' => [], 'old' => ['name' => '" onfocus="alert(1)']], 'layouts.app');
check(str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>') && str_contains($html, '&quot; onfocus=&quot;'), 'view escapes text and attribute values');
fails(fn () => $view->render('../config/app'), 'Invalid view name');
fails(fn () => $view->render('missing'), 'View not found');
check(Response::redirect('/contact')->headers['Location'] === '/contact', 'local redirect');
foreach (['https://example.com', '//example.com', "/\\example.com", "/ok\r\nInjected: value"] as $target) {
    try { Response::redirect($target); throw new RuntimeException('Unsafe redirect accepted'); }
    catch (InvalidArgumentException) { check(true, 'unsafe redirect rejected'); }
}

$demo = require __DIR__ . '/Fixtures/Demo/bootstrap.php';
$demo->container->instance(Session::class, new Session(native: false));
$form = $demo->handle(new Request('GET', '/contact'));
preg_match('/name="_token" value="([a-f0-9]+)"/', $form->body, $match);
check($form->status === 200 && isset($match[1]), 'contact demo renders session token');
check($demo->handle(new Request('POST', '/contact'))->status === 419, 'contact demo protected');
$invalid = $demo->handle(new Request('POST', '/contact', body: ['_token' => $match[1], 'name' => '<b>Ada</b>', 'email' => 'bad']));
check($invalid->status === 303, 'invalid HTML form redirects');
$retry = $demo->handle(new Request('GET', '/contact'));
check(str_contains($retry->body, 'email failed') && str_contains($retry->body, '&lt;b&gt;Ada&lt;/b&gt;'), 'form redisplays errors and escaped old input');
$valid = $demo->handle(new Request('POST', '/contact', body: ['_token' => $match[1], 'name' => 'Ada', 'email' => 'ada@example.com']));
check($valid->status === 303 && str_contains($demo->handle(new Request('GET', '/contact'))->body, 'Thanks, Ada'), 'valid form redirects with flash');
check(!str_contains($demo->handle(new Request('GET', '/contact'))->body, 'Thanks, Ada'), 'demo flash expires');
check($demo->handle(new Request('POST', '/api/echo'))->status === 201, 'existing stateless API remains compatible');
