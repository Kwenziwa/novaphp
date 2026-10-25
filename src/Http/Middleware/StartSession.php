<?php

declare(strict_types=1);

namespace Nova\Http\Middleware;

use Closure;
use Nova\Http\{Middleware, Request, Response};
use Nova\Session\Session;

final class StartSession implements Middleware
{
    public function __construct(private readonly Session $session) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->session->start();
        try { return $next($request); } finally { $this->session->close(); }
    }
}
