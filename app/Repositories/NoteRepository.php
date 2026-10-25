<?php

declare(strict_types=1);

namespace App\Repositories;

use Nova\Database\Connection;

final class NoteRepository
{
    public function __construct(private readonly Connection $database) {}

    public function all(): array
    {
        return $this->database->select('SELECT id, title, body, created_at FROM notes ORDER BY id DESC LIMIT 100');
    }

    public function find(int $id): ?array
    {
        return $this->database->first('SELECT id, title, body, created_at FROM notes WHERE id = ?', [$id]);
    }

    public function create(array $data): int
    {
        $this->database->execute('INSERT INTO notes (title, body, created_at) VALUES (:title, :body, :created_at)',
            ['title' => $data['title'], 'body' => $data['body'], 'created_at' => gmdate('c')]);
        return (int) $this->database->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $this->database->execute('UPDATE notes SET title = ?, body = ? WHERE id = ?', [$data['title'], $data['body'], $id]);
    }

    public function delete(int $id): int
    {
        return $this->database->execute('DELETE FROM notes WHERE id = ?', [$id]);
    }
}
