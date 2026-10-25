<?php

declare(strict_types=1);

namespace Nova\Database;

use InvalidArgumentException;

/** Immutable field descriptions; array keys supply column names. */
final class Field
{
    private function __construct(private array $definition) {}

    public static function id(): self { return new self(['type' => 'id']); }
    public static function text(): self { return new self(['type' => 'text']); }
    public static function integer(): self { return new self(['type' => 'integer']); }
    public static function boolean(): self { return new self(['type' => 'boolean']); }
    public static function datetime(): self { return new self(['type' => 'datetime']); }

    public static function string(int $length = 255): self
    {
        if ($length < 1 || $length > 16383) { throw new InvalidArgumentException('String length must be between 1 and 16383.'); }
        return new self(['type' => 'string', 'length' => $length]);
    }

    public static function decimal(int $precision = 10, int $scale = 2): self
    {
        if ($precision < 1 || $precision > 65 || $scale < 0 || $scale > min(30, $precision)) {
            throw new InvalidArgumentException('Invalid decimal precision or scale.');
        }
        return new self(['type' => 'decimal', 'precision' => $precision, 'scale' => $scale]);
    }

    private function with(string $key, mixed $value): self
    {
        if ($this->definition['type'] === 'id') { throw new InvalidArgumentException('ID fields cannot have modifiers.'); }
        $field = clone $this;
        $field->definition[$key] = $value;
        return $field;
    }

    public function optional(): self { return $this->with('nullable', true); }
    public function unique(): self { return $this->with('unique', true); }

    public function defaultsTo(string|int|float|bool|null $value): self
    {
        if (is_string($value) && str_contains($value, "\0")) { throw new InvalidArgumentException('String defaults cannot contain null bytes.'); }
        if (is_float($value) && !is_finite($value)) { throw new InvalidArgumentException('Defaults must be finite.'); }
        return $this->with('default', $value);
    }

    public function definition(): array { return $this->definition; }
}
