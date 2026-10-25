<?php

declare(strict_types=1);

$composer = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
} else {
    spl_autoload_register(static function (string $class): void {
        foreach (['App\\' => 'app/', 'Nova\\' => 'src/'] as $prefix => $directory) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }
            $relative = substr($class, strlen($prefix));
            $file = dirname(__DIR__) . '/' . $directory . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    });
}
