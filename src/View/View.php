<?php

declare(strict_types=1);

namespace Nova\View;

use RuntimeException;
use Throwable;

final class View
{
    public function __construct(private readonly string $directory) {}

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function render(string $name, array $data = [], ?string $layout = null): string
    {
        $content = $this->evaluate($name, $data);
        return $layout === null ? $content : $this->evaluate($layout, [...$data, 'content' => $content]);
    }

    private function evaluate(string $name, array $data): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/D', $name)) { throw new RuntimeException('Invalid view name.'); }
        $root = realpath($this->directory);
        $path = realpath($this->directory . '/' . str_replace('.', '/', $name) . '.php');
        if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('View not found: ' . $name);
        }
        $level = ob_get_level();
        ob_start();
        try {
            (static function (string $__path, array $__data): void {
                // Variables are isolated from renderer internals. Templates are trusted PHP.
                extract($__data, EXTR_SKIP);
                $e = [View::class, 'escape'];
                require $__path;
            })($path, $data);
            return (string) ob_get_clean();
        } catch (Throwable $error) {
            while (ob_get_level() > $level) { ob_end_clean(); }
            throw $error;
        }
    }
}
