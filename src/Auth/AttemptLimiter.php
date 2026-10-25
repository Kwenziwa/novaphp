<?php

declare(strict_types=1);

namespace Nova\Auth;

use Nova\Database\Connection;

final class AttemptLimiter
{
    public function __construct(private readonly Connection $database) {}

    /** Persistent fixed-window limits; identities are stored as hashes, never raw emails/IPs. */
    public function consume(array $limits): void
    {
        $allowed = $this->database->transaction(function (Connection $db) use ($limits): bool {
            $now = time();
            $db->execute('DELETE FROM auth_attempts WHERE window_started < ?', [$now - 1800]);
            foreach ($limits as $identity => $limit) {
                $key = hash('sha256', $identity);
                $sql = $db->driver() === 'mysql'
                    ? 'INSERT INTO auth_attempts (key_hash, attempts, window_started) VALUES (?, 0, ?) ON DUPLICATE KEY UPDATE key_hash = key_hash'
                    : 'INSERT INTO auth_attempts (key_hash, attempts, window_started) VALUES (?, 0, ?) ON CONFLICT(key_hash) DO NOTHING';
                $db->execute($sql, [$key, $now]);
                $row = $db->first('SELECT attempts, window_started FROM auth_attempts WHERE key_hash = ?'
                    . ($db->driver() === 'mysql' ? ' FOR UPDATE' : ''), [$key]);
                $attempts = (int) $row['window_started'] <= $now - 900 ? 0 : (int) $row['attempts'];
                if ($attempts >= $limit) { return false; }
                $start = $attempts === 0 ? $now : (int) $row['window_started'];
                $db->execute('UPDATE auth_attempts SET attempts = ?, window_started = ? WHERE key_hash = ?', [$attempts + 1, $start, $key]);
            }
            return true;
        });
        if (!$allowed) { throw new RateLimitException(); }
    }

    public function clear(string $identity): void
    {
        $this->database->execute('DELETE FROM auth_attempts WHERE key_hash = ?', [hash('sha256', $identity)]);
    }
}
