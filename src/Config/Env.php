<?php

declare(strict_types=1);

namespace Nova\Config;

use RuntimeException;

final class Env
{
    // Literal values only: no interpolation, shell execution, or PHP evaluation.
    public static function load(string $path): void
    {
        if (!is_file($path)) { return; }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) { throw new RuntimeException('Cannot read environment file.'); }
        $values = [];
        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) { continue; }
            if (!preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $match)) {
                throw new RuntimeException('Invalid environment entry at line ' . ($index + 1));
            }
            $value = trim($match[2]);
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                $end = strpos($value, $quote, 1);
                if ($end === false || !preg_match('/^\s*(?:#.*)?$/', substr($value, $end + 1))) {
                    throw new RuntimeException('Invalid quoted environment value at line ' . ($index + 1));
                }
                $value = substr($value, 1, $end - 1);
            } else {
                $value = rtrim(preg_replace('/\s+#.*$/', '', $value));
            }
            if (str_contains($value, "\0")) { throw new RuntimeException('Invalid environment value at line ' . ($index + 1)); }
            $values[$match[1]] = $value;
        }
        foreach ($values as $key => $value) {
            if (getenv($key) !== false || array_key_exists($key, $_ENV) || array_key_exists($key, $_SERVER)) { continue; }
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}
