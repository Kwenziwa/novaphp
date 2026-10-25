<?php

declare(strict_types=1);

namespace Nova\Auth;

use Nova\Config\Repository;
use Nova\Database\{Connection, SqlIdentifier};
use Nova\Validation\ValidationException;
use PDOException;
use RuntimeException;

/** Optional SQL adapter. Applications own the schema and can replace this adapter. */
final class DatabaseUserProvider implements UserProvider
{
    public function __construct(private readonly Connection $database, private readonly Repository $config) {}

    private function table(): string
    {
        return SqlIdentifier::quote($this->database, $this->config->get('auth.storage.table', 'users'));
    }

    private function column(string $key): string
    {
        return SqlIdentifier::quote($this->database, $this->config->get('auth.storage.' . $key, $key));
    }

    private function select(): string
    {
        return implode(', ', array_map(fn (string $key): string => $this->column($key) . ' AS '
            . SqlIdentifier::quote($this->database, $key), ['id', 'name', 'email', 'password_hash']));
    }

    public function findById(int $id): ?array
    {
        return $this->database->first('SELECT ' . $this->select() . ' FROM ' . $this->table() . ' WHERE ' . $this->column('id') . ' = ?', [$id]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->database->first('SELECT ' . $this->select() . ' FROM ' . $this->table() . ' WHERE ' . $this->column('email') . ' = ?', [$email]);
    }

    public function create(string $name, string $email, #[\SensitiveParameter] string $passwordHash): array
    {
        $columns = [$this->column('name'), $this->column('email'), $this->column('password_hash')];
        $values = [$name, $email, $passwordHash];
        $created = $this->config->get('auth.storage.created_at', 'created_at');
        if ($created !== null) { $columns[] = SqlIdentifier::quote($this->database, $created); $values[] = gmdate('Y-m-d H:i:s'); }
        try {
            $this->database->execute('INSERT INTO ' . $this->table() . ' (' . implode(', ', $columns) . ') VALUES ('
                . implode(', ', array_fill(0, count($values), '?')) . ')', $values);
        } catch (PDOException $error) {
            if (in_array((string) $error->getCode(), ['23000', '23505'], true)) {
                throw new ValidationException(['email' => ['Unable to register with these details.']]);
            }
            throw $error;
        }
        return $this->findById((int) $this->database->lastInsertId()) ?? throw new RuntimeException('Created account could not be loaded.');
    }

    public function rehash(int $id, #[\SensitiveParameter] string $previousHash, #[\SensitiveParameter] string $newHash): void
    {
        $hash = $this->column('password_hash');
        $this->database->execute('UPDATE ' . $this->table() . ' SET ' . $hash . ' = ? WHERE ' . $this->column('id')
            . ' = ? AND ' . $hash . ' = ?', [$newHash, $id, $previousHash]);
    }
}
