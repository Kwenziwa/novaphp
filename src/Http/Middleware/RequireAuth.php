<?php

declare(strict_types=1);

namespace Nova\Http\Middleware;

use Closure;
use Nova\Auth\Auth;
use Nova\Http\{Middleware, Request, Response};

final class RequireAuth implements Middleware
{
    public function __construct(private readonly Auth $auth, private readonly \Nova\Config\Repository $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->auth->user() === null) {
            $response = $request->expectsJson() ? Response::json(['error' => 'Authentication required'], 401) : Response::redirect($this->config->get('auth.login_path', '/login'));
        } else { $response = $next($request); }
        return new Response($response->body, $response->status, [...$response->headers, 'Cache-Control' => 'no-store', 'Vary' => 'Cookie']);
    }
}
