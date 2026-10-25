<?php

declare(strict_types=1);

namespace Nova\Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

final class Container
{
    private array $bindings = [];
    private array $instances = [];
    private array $resolving = [];

    public function bind(string $id, string|Closure|null $concrete = null): void
    {
        $this->bindings[$id] = [$concrete ?? $id, false];
        unset($this->instances[$id]);
    }

    public function singleton(string $id, string|Closure|null $concrete = null): void
    {
        $this->bindings[$id] = [$concrete ?? $id, true];
        unset($this->instances[$id]);
    }

    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function make(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) { return $this->instances[$id]; }
        if (in_array($id, $this->resolving, true)) {
            throw new RuntimeException('Circular dependency: ' . implode(' -> ', [...$this->resolving, $id]));
        }
        $this->resolving[] = $id;
        try {
            [$concrete, $shared] = $this->bindings[$id] ?? [$id, false];
            if ($concrete instanceof Closure) {
                $object = $concrete($this);
            } elseif ($concrete !== $id) {
                $object = $this->make($concrete);
            } else {
                if (!class_exists($concrete)) { throw new RuntimeException('No binding for ' . $id); }
                $class = new ReflectionClass($concrete);
                if (!$class->isInstantiable()) { throw new RuntimeException('Cannot instantiate ' . $id); }
                $arguments = [];
                foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
                    if ($parameter->isVariadic()) { continue; }
                    $type = $parameter->getType();
                    if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                        $name = $type->getName();
                        if ($name === 'self') { $name = $parameter->getDeclaringClass()->getName(); }
                        if ($name === 'parent') { $name = $parameter->getDeclaringClass()->getParentClass()->getName(); }
                        $arguments[] = $this->make($name);
                    } elseif ($parameter->isDefaultValueAvailable()) {
                        $arguments[] = $parameter->getDefaultValue();
                    } else {
                        throw new RuntimeException('Cannot resolve ' . $id . '::$' . $parameter->getName());
                    }
                }
                $object = $class->newInstanceArgs($arguments);
            }
            if ($shared) { $this->instances[$id] = $object; }
            return $object;
        } finally {
            array_pop($this->resolving);
        }
    }
}
