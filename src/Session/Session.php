<?php

declare(strict_types=1);

namespace Nova\Session;

use RuntimeException;

final class Session
{
    private array $data = [];
    private bool $started = false;

    public function __construct(private readonly bool $native = true, private readonly bool $secure = false) {}

    public function start(): void
    {
        if ($this->started) { return; }
        if ($this->native) {
            if (session_status() === PHP_SESSION_ACTIVE) { throw new RuntimeException('Session already managed outside NovaPHP.'); }
            if (headers_sent()) { throw new RuntimeException('Sessions must start before output.'); }
            session_name('nova_session');
            if (!session_start(['use_strict_mode' => 1, 'use_only_cookies' => 1, 'use_cookies' => 1,
                'use_trans_sid' => 0, 'cookie_httponly' => true, 'cookie_secure' => $this->secure,
                'cookie_samesite' => 'Lax', 'cookie_path' => '/', 'cookie_lifetime' => 0])) {
                throw new RuntimeException('Unable to start session.');
            }
            $this->data = $_SESSION;
        }
        $this->data['_flash_old'] = $this->data['_flash_new'] ?? [];
        $this->data['_flash_new'] = [];
        $this->started = true;
    }

    private function requireStarted(): void
    {
        if (!$this->started) { throw new RuntimeException('Use StartSession middleware before accessing the session.'); }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->requireStarted();
        return $this->data['values'][$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->requireStarted();
        $this->data['values'][$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->requireStarted();
        unset($this->data['values'][$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $this->requireStarted();
        $this->data['_flash_new'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->requireStarted();
        return $this->data['_flash_new'][$key] ?? $this->data['_flash_old'][$key] ?? $default;
    }

    public function token(): string
    {
        $token = $this->get('_csrf');
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->put('_csrf', $token);
        }
        return $token;
    }

    public function regenerate(): void
    {
        $this->requireStarted();
        if ($this->native && !session_regenerate_id(true)) { throw new RuntimeException('Unable to regenerate session.'); }
        $this->forget('_csrf');
    }

    public function close(): void
    {
        if (!$this->started) { return; }
        if ($this->native) {
            $_SESSION = $this->data;
            session_write_close();
            $this->data = [];
        }
        $this->started = false;
    }
}
