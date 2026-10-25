<?php

declare(strict_types=1);

namespace Nova\Database;

use InvalidArgumentException;
use JsonSerializable;
use RuntimeException;

abstract class Record implements JsonSerializable
{
    protected const TABLE = '';
    protected const KEY = 'id';
    protected const WRITABLE = [];
    protected const CASTS = [];
    protected const HIDDEN = [];
    protected const CREATED_AT = null;
    protected const UPDATED_AT = null;
    protected const DATE_FORMAT = 'Y-m-d H:i:s';

    private array $attributes = [];
    private array $original = [];
    private bool $exists = false;
    private bool $deleted = false;

    final public function __construct(private readonly Connection $database) {}

    final public static function tableName(): string
    {
        if (static::TABLE === '') { throw new RuntimeException('Models must declare their TABLE name.'); }
        return static::TABLE;
    }

    final public static function keyName(): string { return static::KEY; }
    final public static function using(Connection $database): RecordQuery { return new RecordQuery($database, static::class); }

    /** Internal hydration of trusted database rows; use fill() for user input. */
    final public static function hydrate(Connection $database, array $row): static
    {
        $record = new static($database);
        $record->attributes = $row;
        $record->original = $row;
        $record->exists = true;
        return $record;
    }

    private function encode(string $field, mixed $value): mixed
    {
        if ($value === null) { return null; }
        $cast = static::CASTS[$field] ?? null;
        if ($cast === 'json') {
            if (!is_array($value)) { throw new InvalidArgumentException('JSON attributes require an array.'); }
            return json_encode($value, JSON_THROW_ON_ERROR);
        }
        if ($cast === 'bool') {
            if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) { throw new InvalidArgumentException('Invalid boolean attribute: ' . $field); }
            return (int) (bool) $value;
        }
        if ($cast === 'int') {
            if (!(is_int($value) || is_string($value)) || filter_var($value, FILTER_VALIDATE_INT) === false) { throw new InvalidArgumentException('Invalid integer attribute: ' . $field); }
            return (int) $value;
        }
        if ($cast === 'float') {
            if (!is_numeric($value) || !is_finite((float) $value)) { throw new InvalidArgumentException('Invalid numeric attribute: ' . $field); }
            return (float) $value;
        }
        if ($cast === 'string') {
            if (!is_scalar($value)) { throw new InvalidArgumentException('Invalid string attribute: ' . $field); }
            return (string) $value;
        }
        if ($cast !== null) { throw new InvalidArgumentException('Unsupported model cast: ' . $cast); }
        if (!is_scalar($value) || (is_float($value) && !is_finite($value))) { throw new InvalidArgumentException('Attributes require scalar values or explicit JSON casts.'); }
        return $value;
    }

    final public function fill(array $attributes): static
    {
        $prepared = [];
        $reserved = array_map('strtolower', array_filter([static::KEY, static::CREATED_AT, static::UPDATED_AT], fn ($field) => $field !== null));
        foreach ($attributes as $field => $value) {
            if (!is_string($field) || !in_array($field, static::WRITABLE, true)
                || in_array(strtolower($field), $reserved, true)) {
                throw new InvalidArgumentException('Attribute is not writable: ' . $field);
            }
            SqlIdentifier::quote($this->database, $field);
            $prepared[$field] = $this->encode($field, $value);
        }
        $this->attributes = array_replace($this->attributes, $prepared);
        return $this;
    }

    final public function __set(string $field, mixed $value): void { $this->fill([$field => $value]); }

    final public function __get(string $field): mixed
    {
        $value = $this->attributes[$field] ?? null;
        if ($value === null) { return null; }
        return match (static::CASTS[$field] ?? ($field === static::KEY ? 'int' : null)) {
            'int' => (int) $value, 'float' => (float) $value, 'bool' => (bool) $value,
            'string' => (string) $value, 'json' => json_decode($value, true, 512, JSON_THROW_ON_ERROR),
            null => $value,
            default => throw new InvalidArgumentException('Unsupported model cast.'),
        };
    }

    final public function __isset(string $field): bool { return isset($this->attributes[$field]); }
    final public function exists(): bool { return $this->exists; }

    final public function toArray(): array
    {
        $result = [];
        foreach (array_keys($this->attributes) as $field) {
            if (!in_array($field, static::HIDDEN, true)) { $result[$field] = $this->__get($field); }
        }
        return $result;
    }

    final public function jsonSerialize(): array { return $this->toArray(); }

    final public function refresh(): static
    {
        if (!$this->exists || $this->deleted) { throw new RuntimeException('Cannot refresh an unsaved or deleted record.'); }
        $fresh = static::using($this->database)->find($this->attributes[static::KEY]);
        if ($fresh === null) { throw new RuntimeException('Record no longer exists.'); }
        $this->attributes = $fresh->attributes;
        $this->original = $fresh->original;
        return $this;
    }

    final public function save(): static
    {
        if ($this->deleted) { throw new RuntimeException('Cannot save a deleted record.'); }
        $table = SqlIdentifier::quote($this->database, static::tableName());
        $key = SqlIdentifier::quote($this->database, static::KEY);
        if (!$this->exists && static::CREATED_AT !== null) { $this->attributes[static::CREATED_AT] = gmdate(static::DATE_FORMAT); }
        $dirty = array_filter($this->attributes, fn ($value, $field) => !array_key_exists($field, $this->original) || $this->original[$field] !== $value, ARRAY_FILTER_USE_BOTH);
        if ($this->exists && $dirty === []) { return $this; }
        if (static::UPDATED_AT !== null) { $dirty[static::UPDATED_AT] = gmdate(static::DATE_FORMAT); }
        if ($this->exists) {
            unset($dirty[static::KEY]);
            $assignments = array_map(fn (string $field): string => SqlIdentifier::quote($this->database, $field) . ' = ?', array_keys($dirty));
            $this->database->execute('UPDATE ' . $table . ' SET ' . implode(', ', $assignments) . ' WHERE ' . $key . ' = ?', [...array_values($dirty), $this->attributes[static::KEY]]);
        } else {
            $columns = array_map(fn (string $field): string => SqlIdentifier::quote($this->database, $field), array_keys($dirty));
            if ($columns === []) { $sql = 'INSERT INTO ' . $table . ($this->database->driver() === 'mysql' ? ' () VALUES ()' : ' DEFAULT VALUES'); }
            else { $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')'; }
            $this->database->execute($sql, array_values($dirty));
            $this->attributes[static::KEY] = $this->database->lastInsertId();
            $this->exists = true;
        }
        return $this->refresh();
    }

    final public function delete(): bool
    {
        if (!$this->exists || $this->deleted) { throw new RuntimeException('Cannot delete an unsaved or deleted record.'); }
        $affected = $this->database->execute('DELETE FROM ' . SqlIdentifier::quote($this->database, static::tableName())
            . ' WHERE ' . SqlIdentifier::quote($this->database, static::KEY) . ' = ?', [$this->attributes[static::KEY]]);
        $this->exists = false;
        $this->deleted = true;
        return $affected > 0;
    }

    final public function belongsTo(string $model, string $foreignKey, string $ownerKey = 'id'): ?Record
    {
        if (!is_subclass_of($model, self::class)) { throw new InvalidArgumentException('Relations require a Record model.'); }
        $value = $this->__get($foreignKey);
        return $value === null ? null : $model::using($this->database)->where($ownerKey, $value)->first();
    }

    final public function hasMany(string $model, string $foreignKey): RecordQuery
    {
        if (!$this->exists || $this->deleted) { throw new RuntimeException('Relations require a saved record.'); }
        if (!is_subclass_of($model, self::class)) { throw new InvalidArgumentException('Relations require a Record model.'); }
        return $model::using($this->database)->where($foreignKey, $this->__get(static::KEY));
    }
}
