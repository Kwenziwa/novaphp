<?php

declare(strict_types=1);

namespace Nova\Routing;

use Nova\Http\Request;
use Nova\Http\Response;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

final class Router
{
    private array $routes = [];
    private array $groups = [];

    public function __construct(private readonly \Nova\Core\Container $container = new \Nova\Core\Container()) {}

    public function get(string $path, callable|array $handler, array $middleware = []): void { $this->add('GET', $path, $handler, $middleware); }
    public function post(string $path, callable|array $handler, array $middleware = []): void { $this->add('POST', $path, $handler, $middleware); }
    public function put(string $path, callable|array $handler, array $middleware = []): void { $this->add('PUT', $path, $handler, $middleware); }
    public function patch(string $path, callable|array $handler, array $middleware = []): void { $this->add('PATCH', $path, $handler, $middleware); }
    public function delete(string $path, callable|array $handler, array $middleware = []): void { $this->add('DELETE', $path, $handler, $middleware); }

    public function group(string $prefix, array $middleware, \Closure $routes): void
    {
        if ($prefix !== '' && $prefix[0] !== '/') { throw new RuntimeException('Group prefixes must begin with /.'); }
        $this->groups[] = [rtrim($prefix, '/'), $middleware];
        try { $routes($this); } finally { array_pop($this->groups); }
    }

    public function add(string $method, string $path, callable|array $handler, array $middleware = []): void
    {
        if ($path === '' || $path[0] !== '/') {
            throw new RuntimeException('Route paths must begin with /.');
        }
        $prefix = '';
        $inherited = [];
        foreach ($this->groups as [$segment, $stack]) {
            $prefix .= $segment;
            $inherited = [...$inherited, ...$stack];
        }
        $path = $prefix . $path;
        $this->routes[] = [strtoupper($method), $path, $handler, [...$inherited, ...$middleware]];
    }

    public function routes(): array { return $this->routes; }

    public function dispatch(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as [$method, $path, $handler, $middleware]) {
            $parts = preg_split('/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE);
            $pattern = '';
            foreach ($parts as $part) {
                $pattern .= preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $part, $match)
                    ? '(?P<' . $match[1] . '>[^/]+)' : preg_quote($part, '~');
            }
            $pattern = $path === '/' ? '~^/$~D' : '~^' . rtrim($pattern, '/') . '/?$~D';
            if (!preg_match($pattern, $request->path, $matches)) { continue; }
            if ($method !== $request->method && !($request->method === 'HEAD' && $method === 'GET')) {
                $allowed[] = $method;
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return \Nova\Http\Pipeline::run($request, $middleware,
                function (Request $request) use ($handler, $params): Response {
                    $result = $this->invoke($handler, $request, $params);
                    if ($result instanceof Response) { return $result; }
                    if (is_array($result)) { return Response::json($result); }
                    if (is_string($result)) { return Response::html($result); }
                    throw new RuntimeException('Route handlers must return Response, array, or string.');
                }, $this->container);
        }
        if ($allowed !== []) {
            return new Response('405 Method Not Allowed', 405,
                ['Content-Type' => 'text/plain; charset=UTF-8', 'Allow' => implode(', ', array_unique($allowed))]);
        }
        return Response::text('404 Not Found', 404);
    }

    private function invoke(callable|array $handler, Request $request, array $params): mixed
    {
        if (is_array($handler) && is_string($handler[0])) {
            $handler = [$this->container->make($handler[0]), $handler[1]];
        }
        $reflection = is_array($handler)
            ? new ReflectionMethod($handler[0], $handler[1])
            : new ReflectionFunction($handler);
        $arguments = [];
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && $type->getName() === Request::class) {
                $arguments[] = $request;
            } elseif (array_key_exists($parameter->getName(), $params)) {
                $arguments[] = $params[$parameter->getName()];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } elseif ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $arguments[] = $this->container->make($type->getName());
            } else {
                throw new RuntimeException('Cannot resolve handler parameter $' . $parameter->getName());
            }
        }
        return $handler(...$arguments);
    }
}
