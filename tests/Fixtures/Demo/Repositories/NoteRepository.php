<?php

declare(strict_types=1);

namespace Tests\Fixtures\Demo\Repositories;

use Nova\Database\Connection;
use Tests\Fixtures\Demo\Models\Note;
use Nova\Auth\Auth;
use Nova\Database\RecordQuery;

final class NoteRepository
{
    public function __construct(private readonly Connection $database, private readonly Auth $auth) {}

    private function owned(): RecordQuery
    {
        return Note::using($this->database)->where('user_id', $this->auth->requireUser()['id']);
    }

    public function all(): array
    {
        return array_map(fn (Note $note): array => $note->toArray(),
            $this->owned()->orderBy('id', 'desc')->take(100)->all());
    }

    public function find(int $id): ?array
    {
        return $this->owned()->find($id)?->toArray();
    }

    public function create(array $data): int
    {
        return Note::using($this->database)->create([...array_intersect_key($data, array_flip(['title', 'body'])), 'user_id' => $this->auth->requireUser()['id']])->id;
    }

    public function update(int $id, array $data): void
    {
        $this->owned()->find($id)?->fill(array_intersect_key($data, array_flip(['title', 'body'])))->save();
    }

    public function delete(int $id): int
    {
        return (int) ($this->owned()->find($id)?->delete() ?? false);
    }
}
