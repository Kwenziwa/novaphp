<?php

declare(strict_types=1);

namespace Nova\Database;

use Closure;
use RuntimeException;

final class Migrator
{
    public function __construct(private readonly Connection $database, private readonly string $directory) {}

    private function files(): array
    {
        $files = [];
        foreach (glob($this->directory . '/*.php') ?: [] as $file) { $files[pathinfo($file, PATHINFO_FILENAME)] = $file; }
        ksort($files, SORT_STRING);
        return $files;
    }

    private function history(): array
    {
        $this->database->execute('CREATE TABLE IF NOT EXISTS nova_migrations (migration VARCHAR(255) PRIMARY KEY, batch INTEGER NOT NULL, applied_at VARCHAR(32) NOT NULL)');
        return $this->database->select('SELECT migration, batch, applied_at FROM nova_migrations ORDER BY migration');
    }

    private function locked(Closure $callback): mixed
    {
        if (!is_dir($this->directory)) { throw new RuntimeException('Migration directory does not exist.'); }
        $lock = fopen($this->directory . '/.nova-migrate.lock', 'c');
        if ($lock === false) { throw new RuntimeException('Cannot open migration lock.'); }
        try {
            if (!flock($lock, LOCK_EX)) { throw new RuntimeException('Cannot lock migrations.'); }
            return $callback();
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private function load(string $file): Migration
    {
        $migration = require $file;
        if (!$migration instanceof Migration) { throw new RuntimeException('Migration must return a Migration instance: ' . basename($file)); }
        return $migration;
    }

    private function apply(Closure $operation): void
    {
        // MySQL DDL implicitly commits; never claim transactional DDL on that driver.
        if ($this->database->driver() === 'sqlite') { $this->database->transaction($operation); }
        else { $operation($this->database); }
    }

    public function status(): array
    {
        return $this->locked(function (): array {
            $applied = array_column($this->history(), null, 'migration');
            $names = array_unique([...array_keys($this->files()), ...array_keys($applied)]);
            sort($names, SORT_STRING);
            $status = [];
            foreach ($names as $name) {
                $status[] = ['migration' => $name, 'status' => isset($applied[$name]) ? 'applied' : 'pending',
                    'batch' => isset($applied[$name]) ? (int) $applied[$name]['batch'] : null,
                    'file_exists' => is_file($this->directory . '/' . $name . '.php')];
            }
            return $status;
        });
    }

    public function migrate(): array
    {
        return $this->locked(function (): array {
            $history = $this->history();
            $applied = array_column($history, 'migration');
            $batch = $history === [] ? 1 : max(array_column($history, 'batch')) + 1;
            $completed = [];
            foreach ($this->files() as $name => $file) {
                if (in_array($name, $applied, true)) { continue; }
                $migration = $this->load($file);
                $this->apply(function (Connection $db) use ($migration, $name, $batch): void {
                    $migration->up($db);
                    $db->execute('INSERT INTO nova_migrations (migration, batch, applied_at) VALUES (?, ?, ?)', [$name, $batch, gmdate('c')]);
                });
                $completed[] = $name;
            }
            return $completed;
        });
    }

    public function rollback(): array
    {
        return $this->locked(function (): array {
            $history = $this->history();
            if ($history === []) { return []; }
            $batch = max(array_column($history, 'batch'));
            $latest = array_reverse(array_filter($history, fn (array $row): bool => (int) $row['batch'] === (int) $batch));
            $files = $this->files();
            $migrations = [];
            foreach ($latest as $row) {
                $name = $row['migration'];
                if (!isset($files[$name])) { throw new RuntimeException('Missing migration file: ' . $name); }
                $migrations[$name] = $this->load($files[$name]);
            }
            $completed = [];
            foreach ($migrations as $name => $migration) {
                $this->apply(function (Connection $db) use ($migration, $name): void {
                    $migration->down($db);
                    $db->execute('DELETE FROM nova_migrations WHERE migration = ?', [$name]);
                });
                $completed[] = $name;
            }
            return $completed;
        });
    }
}
