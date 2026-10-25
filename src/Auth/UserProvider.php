<?php

declare(strict_types=1);

namespace Nova\Auth;

interface UserProvider
{
    /** Return canonical id/name/email/password_hash fields, or null. */
    public function findById(int $id): ?array;
    public function findByEmail(string $email): ?array;
    public function create(string $name, string $email, #[\SensitiveParameter] string $passwordHash): array;
    public function rehash(int $id, #[\SensitiveParameter] string $previousHash, #[\SensitiveParameter] string $newHash): void;
}
