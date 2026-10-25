<?php

declare(strict_types=1);

namespace Nova\Http;

final class Response
{
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        public readonly array $headers = ['Content-Type' => 'text/html; charset=UTF-8'],
    ) {}

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public static function text(string $text, int $status = 200): self
    {
        return new self($text, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function send(bool $headOnly = false): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if (!$headOnly) {
            echo $this->body;
        }
    }
}
