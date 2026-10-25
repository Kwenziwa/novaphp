<?php

declare(strict_types=1);

namespace Nova\Http;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $headers = [],
    ) {}

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        $body = $_POST;
        if (str_contains(strtolower($headers['content-type'] ?? ''), 'application/json')) {
            $decoded = json_decode(file_get_contents('php://input') ?: '', true);
            $body = is_array($decoded) ? $decoded : [];
        }
        return new self($method, $path, $_GET, $body, $headers);
    }

    public function expectsJson(): bool
    {
        return str_contains(strtolower((string) $this->header('accept', '')), 'json')
            || str_contains(strtolower((string) $this->header('content-type', '')), 'json');
    }

    public function validate(array $rules): array
    {
        return (new \Nova\Validation\Validator())->validate($this->all(), $rules);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function body(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function all(): array
    {
        return array_replace($this->query, $this->body);
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }
}
