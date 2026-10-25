<?php

declare(strict_types=1);

namespace Nova\Http;

use Closure;
use Nova\Core\Container;
use RuntimeException;

final class Pipeline
{
    public static function run(Request $request, array $middleware, Closure $destination, Container $container): Response
    {
        $next = $destination;
        foreach (array_reverse($middleware) as $entry) {
            $downstream = $next;
            $next = static function (Request $request) use ($entry, $downstream, $container): Response {
                $handler = is_string($entry) ? $container->make($entry) : $entry;
                if ($handler instanceof Middleware) { return $handler->handle($request, $downstream); }
                if ($handler instanceof Closure) { return $handler($request, $downstream); }
                throw new RuntimeException('Middleware must be a class implementing Middleware or a Closure.');
            };
        }
        return $next($request);
    }
}
