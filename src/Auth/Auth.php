<?php

declare(strict_types=1);

namespace Nova\Auth;

use Nova\Config\Repository;
use Nova\Database\Connection;
use Nova\Session\Session;
use Nova\Validation\{ValidationException, Validator};
use PDOException;
use RuntimeException;

final class Auth
{
    private const DUMMY_HASH = '$2y$12$Pc2trIOBpWTIyV5iFzqvB.FYIotymj/nl0pzZdyQ8HZDRvRED8pd.';

    public function __construct(private readonly Connection $database, private readonly Session $session,
        private readonly Repository $config, private readonly AttemptLimiter $limiter) {}

    public function user(): ?array
    {
        $state = $this->session->get('_auth');
        if ($state === null) { return null; }
        $now = time();
        $idle = (int) $this->config->get('auth.idle_seconds', 1800);
        $absolute = (int) $this->config->get('auth.absolute_seconds', 43200);
        if ($idle < 1 || $absolute < 1) { throw new RuntimeException('Authentication expiry settings must be positive.'); }
        if (!is_array($state) || !is_int($state['id'] ?? null) || !is_int($state['issued'] ?? null)
            || !is_int($state['seen'] ?? null) || $state['id'] < 1 || $state['issued'] > $now || $state['seen'] > $now
            || $state['seen'] < $state['issued'] || $now - $state['seen'] >= $idle || $now - $state['issued'] >= $absolute) {
            $this->logout();
            return null;
        }
        $user = $this->database->first('SELECT id, name, email FROM users WHERE id = ?', [$state['id']]);
        if ($user === null) { $this->logout(); return null; }
        $user['id'] = (int) $user['id'];
        $state['seen'] = $now;
        $this->session->put('_auth', $state);
        return $user;
    }

    public function requireUser(): array
    {
        return $this->user() ?? throw new RuntimeException('Authentication required.');
    }

    private function establish(int $id): array
    {
        $this->session->invalidate();
        $this->session->put('_auth', ['id' => $id, 'issued' => time(), 'seen' => time()]);
        return $this->requireUser();
    }

    public function register(string $name, string $email, #[\SensitiveParameter] string $password, string $ip): array
    {
        $this->limiter->consume(['register:ip:' . $ip => 10]);
        $email = strtolower(trim($email));
        $name = trim($name);
        (new Validator())->validate(['name' => $name, 'email' => $email], ['name' => 'required|string|max:100', 'email' => 'required|email|max:254']);
        $length = preg_match_all('/./us', $password);
        if ($length === false || $length < 15 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new ValidationException(['password' => ['Use a password of at least 15 characters and at most 72 bytes, without null bytes.']]);
        }
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        try {
            $this->database->execute('INSERT INTO users (name, email, password_hash, created_at) VALUES (?, ?, ?, ?)',
                [$name, $email, $hash, gmdate('Y-m-d H:i:s')]);
        } catch (PDOException $error) {
            if (in_array((string) $error->getCode(), ['23000', '23505'], true)) {
                throw new ValidationException(['email' => ['Unable to register with these details.']]);
            }
            throw $error;
        }
        return $this->establish((int) $this->database->lastInsertId());
    }

    public function attempt(string $email, #[\SensitiveParameter] string $password, string $ip): bool
    {
        $email = strtolower(trim($email));
        $this->limiter->consume(['login:account:' . $email => 5, 'login:ip:' . $ip => 30]);
        $user = strlen($email) <= 254 ? $this->database->first('SELECT id, password_hash FROM users WHERE email = ?', [$email]) : null;
        $validInput = strlen($password) <= 72 && !str_contains($password, "\0");
        $verified = password_verify($validInput ? $password : '', $user['password_hash'] ?? self::DUMMY_HASH);
        if (!$validInput || $user === null || !$verified) { return false; }
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $this->database->execute('UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ?',
                [password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $user['id'], $user['password_hash']]);
        }
        $this->limiter->clear('login:account:' . $email);
        $this->establish((int) $user['id']);
        return true;
    }

    public function logout(): void { $this->session->invalidate(); }
}
