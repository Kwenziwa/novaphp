<?php

declare(strict_types=1);

namespace App\Repositories;

use Nova\Database\Connection;
use App\Models\Note;

final class NoteRepository
{
    public function __construct(private readonly Connection $database) {}

    public function all(): array
    {
        return array_map(fn (Note $note): array => $note->toArray(),
            Note::using($this->database)->orderBy('id', 'desc')->take(100)->all());
    }

    public function find(int $id): ?array
    {
        return Note::using($this->database)->find($id)?->toArray();
    }

    public function create(array $data): int
    {
        return Note::using($this->database)->create($data)->id;
    }

    public function update(int $id, array $data): void
    {
        Note::using($this->database)->find($id)?->fill($data)->save();
    }

    public function delete(int $id): int
    {
        return (int) (Note::using($this->database)->find($id)?->delete() ?? false);
    }
}
