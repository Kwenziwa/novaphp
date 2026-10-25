<?php

declare(strict_types=1);

namespace Nova\Core;

abstract class ServiceProvider
{
    public function __construct(protected readonly Application $app) {}
    public function register(): void {}
    public function boot(): void {}
}
