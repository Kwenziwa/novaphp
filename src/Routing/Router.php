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
    private array $names = [];

    public function __construct(private readonly \Nova\Core\Container $container = new \Nova\Core\Container()) {}

    public function get(string $path, callable|array $handler, array $middleware = [], ?string $name = null): void { $this->add('GET', $path, $handler, $middleware, $name); }
    public function post(string $path, callable|array $handler, array $middleware = [], ?string $name = null): void { $this->add('POST', $path, $handler, $middleware, $name); }
    public function put(string $path, callable|array $handler, array $middleware = [], ?string $name = null): void { $this->add('PUT', $path, $handler, $middleware, $name); }
    public function patch(string $path, callable|array $handler, array $middleware = [], ?string $name = null): void { $this->add('PATCH', $path, $handler, $middleware, $name); }
    public function delete(string $path, callable|array $handler, array $middleware = [], ?string $name = null): void { $this->add('DELETE', $path, $handler, $middleware, $name); }

    public function group(string $prefix, array $middleware, \Closure $routes): void
    {
        if ($prefix !== '' && $prefix[0] !== '/') { throw new RuntimeException('Group prefixes must begin with /.'); }
        $this->groups[] = [rtrim($prefix, '/'), $middleware];
        try { $routes($this); } finally { array_pop($this->groups); }
    }

    public function add(string $method, string $path, callable|array $handler, array $middleware = [], ?string $name = null): void
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
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $path, $matches);
        if (count(array_unique($matches[1])) !== count($matches[1])) { throw new RuntimeException('Route parameter names must be unique.'); }
        if ($name !== null) {
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/D', $name) || isset($this->names[$name])) { throw new RuntimeException('Route names must be valid and unique.'); }
            $this->names[$name] = $path;
        }
        $this->routes[] = [strtoupper($method), $path, $handler, [...$inherited, ...$middleware], $name];
    }

    public function url(string $name, array $parameters = [], array $query = []): string
    {
        $path = $this->names[$name] ?? throw new RuntimeException('Unknown route name: ' . $name);
        $used = [];
        $path = preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', static function (array $match) use ($parameters, &$used): string {
            $key = $match[1];
            if (!array_key_exists($key, $parameters) || !(is_string($parameters[$key]) || is_int($parameters[$key])) || (string) $parameters[$key] === '') {
                throw new RuntimeException('Missing or invalid route parameter: ' . $key);
            }
            $used[] = $key;
            return rawurlencode((string) $parameters[$key]);
        }, $path);
        if (array_diff(array_keys($parameters), $used) !== []) { throw new RuntimeException('Unknown route parameters supplied.'); }
        $suffix = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        return $path . ($suffix === '' ? '' : '?' . $suffix);
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
            return Response::error($request, 'Method Not Allowed', 405, ['Allow' => implode(', ', array_unique($allowed))]);
        }
        return Response::error($request, 'Not Found', 404);
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
