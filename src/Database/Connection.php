<?php

declare(strict_types=1);

namespace Nova\Database;

use Closure;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

final class Connection
{
    private ?PDO $connection = null;

    public function __construct(private readonly array $config) {}

    public function driver(): string
    {
        return $this->config['driver'] ?? 'sqlite';
    }

    public function pdo(): PDO
    {
        if ($this->connection !== null) { return $this->connection; }
        $driver = $this->driver();
        if (!in_array($driver, ['sqlite', 'mysql'], true)) { throw new RuntimeException('Supported database drivers: sqlite, mysql.'); }
        if (!in_array($driver, PDO::getAvailableDrivers(), true)) { throw new RuntimeException("Install the pdo_$driver PHP extension."); }
        if ($driver === 'sqlite') {
            $dsn = 'sqlite:' . ($this->config['database'] ?? ':memory:');
        } else {
            foreach (['host', 'port', 'database'] as $key) {
                if (preg_match('/[;\x00-\x1f]/', (string) ($this->config[$key] ?? ''))) {
                    throw new RuntimeException('Invalid MySQL connection configuration.');
                }
            }
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $this->config['host'] ?? '127.0.0.1', $this->config['port'] ?? 3306, $this->config['database'] ?? 'nova');
        }
        $pdo = new PDO($dsn, $this->config['username'] ?? null, $this->config['password'] ?? null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false]);
        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
        }
        return $this->connection = $pdo;
    }

    private function statement(string $sql, array $bindings): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        foreach ($bindings as $key => $value) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                is_string($value), is_float($value) => PDO::PARAM_STR,
                default => throw new RuntimeException('SQL bindings must be scalar values or null.'),
            };
            $parameter = is_int($key) ? $key + 1 : ':' . ltrim($key, ':');
            $statement->bindValue($parameter, $value, $type);
        }
        $statement->execute();
        return $statement;
    }

    public function select(string $sql, array $bindings = []): array
    {
        return $this->statement($sql, $bindings)->fetchAll();
    }

    public function first(string $sql, array $bindings = []): ?array
    {
        return $this->statement($sql, $bindings)->fetch() ?: null;
    }

    public function execute(string $sql, array $bindings = []): int
    {
        return $this->statement($sql, $bindings)->rowCount();
    }

    public function schema(): Schema { return new Schema($this); }

    public function lastInsertId(): string { return $this->pdo()->lastInsertId(); }

    public function transaction(Closure $callback): mixed
    {
        $pdo = $this->pdo();
        if ($pdo->inTransaction()) { throw new RuntimeException('Nested transactions are not supported.'); }
        $pdo->beginTransaction();
        try {
            $result = $callback($this);
            $pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        }
    }
}
