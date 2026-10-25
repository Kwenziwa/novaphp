<?php

declare(strict_types=1);

namespace Nova\Config;

final class Repository
{
    public function __construct(private array $items = []) {}

    public function all(): array { return $this->items; }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) { return $default; }
            $value = $value[$segment];
        }
        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $target = &$this->items;
        foreach (explode('.', $key) as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) { $target[$segment] = []; }
            $target = &$target[$segment];
        }
        $target = $value;
    }
}
