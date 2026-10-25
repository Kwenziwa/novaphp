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

    public function get(string $path, callable|array $handler): void { $this->add('GET', $path, $handler); }
    public function post(string $path, callable|array $handler): void { $this->add('POST', $path, $handler); }
    public function put(string $path, callable|array $handler): void { $this->add('PUT', $path, $handler); }
    public function patch(string $path, callable|array $handler): void { $this->add('PATCH', $path, $handler); }
    public function delete(string $path, callable|array $handler): void { $this->add('DELETE', $path, $handler); }

    public function add(string $method, string $path, callable|array $handler): void
    {
        if ($path === '' || $path[0] !== '/') {
            throw new RuntimeException('Route paths must begin with /.');
        }
        $this->routes[] = [strtoupper($method), $path, $handler];
    }

    public function routes(): array { return $this->routes; }

    public function dispatch(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as [$method, $path, $handler]) {
            $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
                static fn (array $m): string => '(?P<' . $m[1] . '>[^/]+)', $path);
            $pattern = '~^' . str_replace('~', '\~', $pattern) . '/?$~D';
            if ($path === '/') { $pattern = '~^/$~D'; }
            if (!preg_match($pattern, $request->path, $matches)) { continue; }
            if ($method !== $request->method && !($request->method === 'HEAD' && $method === 'GET')) {
                $allowed[] = $method;
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $result = $this->invoke($handler, $request, $params);
            if ($result instanceof Response) { return $result; }
            if (is_array($result)) { return Response::json($result); }
            if (is_string($result)) { return Response::html($result); }
            throw new RuntimeException('Route handlers must return Response, array, or string.');
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
            $handler = [new $handler[0](), $handler[1]];
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
            } else {
                throw new RuntimeException('Cannot resolve handler parameter $' . $parameter->getName());
            }
        }
        return $handler(...$arguments);
    }
}
