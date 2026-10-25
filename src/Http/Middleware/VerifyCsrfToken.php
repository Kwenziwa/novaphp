<?php

declare(strict_types=1);

namespace Nova\Http\Middleware;

use Closure;
use Nova\Http\{Middleware, Request, Response};
use Nova\Session\Session;

final class VerifyCsrfToken implements Middleware
{
    public function __construct(private readonly Session $session) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $token = $request->header('x-csrf-token') ?? $request->body('_token');
            if (!is_string($token) || !hash_equals($this->session->token(), $token)) {
                return $request->expectsJson()
                    ? Response::json(['error' => 'CSRF token mismatch'], 419)
                    : Response::text('419 CSRF token mismatch', 419);
            }
        }
        return $next($request);
    }
}
