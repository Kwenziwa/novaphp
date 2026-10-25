<?php

declare(strict_types=1);

namespace Nova\Database;

use InvalidArgumentException;

/** Immutable queries; each filter returns a new query on the same connection. */
final class RecordQuery
{
    private array $conditions = [];
    private array $bindings = [];
    private array $orders = [];
    private ?int $limit = null;
    private int $offset = 0;

    /** @param class-string<Record> $model */
    public function __construct(private readonly Connection $database, private readonly string $model)
    {
        if (!is_subclass_of($model, Record::class)) { throw new InvalidArgumentException('Queries require a Record model.'); }
        SqlIdentifier::quote($database, $model::tableName());
    }

    public function where(string $field, mixed $operatorOrValue, mixed $value = null): self
    {
        if (func_num_args() === 3 && !is_string($operatorOrValue)) { throw new InvalidArgumentException('Query operators must be strings.'); }
        $operator = func_num_args() === 2 ? '=' : strtoupper((string) $operatorOrValue);
        $value = func_num_args() === 2 ? $operatorOrValue : $value;
        if (!in_array($operator, ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'], true)) {
            throw new InvalidArgumentException('Unsupported query operator.');
        }
        $column = SqlIdentifier::quote($this->database, $field);
        $query = clone $this;
        if ($value === null) {
            if (!in_array($operator, ['=', '!=', '<>'], true)) { throw new InvalidArgumentException('Null comparisons require = or !=.'); }
            $query->conditions[] = $column . ($operator === '=' ? ' IS NULL' : ' IS NOT NULL');
        } else {
            $this->checkValue($value);
            $query->conditions[] = $column . ' ' . $operator . ' ?';
            $query->bindings[] = $value;
        }
        return $query;
    }

    private function checkValue(mixed $value): void
    {
        if (!is_scalar($value) && $value !== null) { throw new InvalidArgumentException('Query values must be scalar or null.'); }
        if (is_float($value) && !is_finite($value)) { throw new InvalidArgumentException('Query values must be finite.'); }
    }

    public function whereNull(string $field): self { return $this->where($field, null); }

    public function whereIn(string $field, array $values): self
    {
        $column = SqlIdentifier::quote($this->database, $field);
        foreach ($values as $value) { $this->checkValue($value); }
        $query = clone $this;
        $query->conditions[] = $values === [] ? '1 = 0' : $column . ' IN (' . implode(', ', array_fill(0, count($values), '?')) . ')';
        $query->bindings = [...$query->bindings, ...array_values($values)];
        return $query;
    }

    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) { throw new InvalidArgumentException('Sort direction must be asc or desc.'); }
        $query = clone $this;
        $query->orders[] = SqlIdentifier::quote($this->database, $field) . ' ' . $direction;
        return $query;
    }

    public function take(int $limit): self
    {
        if ($limit < 1 || $limit > 10000) { throw new InvalidArgumentException('Query limit must be between 1 and 10000.'); }
        $query = clone $this;
        $query->limit = $limit;
        return $query;
    }

    public function skip(int $offset): self
    {
        if ($offset < 0) { throw new InvalidArgumentException('Query offset cannot be negative.'); }
        $query = clone $this;
        $query->offset = $offset;
        return $query;
    }

    private function filterSql(): string
    {
        return $this->conditions === [] ? '' : ' WHERE ' . implode(' AND ', $this->conditions);
    }

    public function all(): array
    {
        $sql = 'SELECT * FROM ' . SqlIdentifier::quote($this->database, $this->model::tableName()) . $this->filterSql();
        if ($this->orders !== []) { $sql .= ' ORDER BY ' . implode(', ', $this->orders); }
        $bindings = $this->bindings;
        if ($this->limit !== null) { $sql .= ' LIMIT ?'; $bindings[] = $this->limit; }
        elseif ($this->offset > 0) { $sql .= $this->database->driver() === 'mysql' ? ' LIMIT 18446744073709551615' : ' LIMIT -1'; }
        if ($this->offset > 0) { $sql .= ' OFFSET ?'; $bindings[] = $this->offset; }
        return array_map(fn (array $row): Record => $this->model::hydrate($this->database, $row), $this->database->select($sql, $bindings));
    }

    public function first(): ?Record { return $this->take(1)->all()[0] ?? null; }

    public function find(int|string $id): ?Record { return $this->where($this->model::keyName(), $id)->first(); }

    /** Count matching records, ignoring ordering, take, and skip. */
    public function count(): int
    {
        $row = $this->database->first('SELECT COUNT(*) AS total FROM ' . SqlIdentifier::quote($this->database, $this->model::tableName()) . $this->filterSql(), $this->bindings);
        return (int) $row['total'];
    }

    /** Query filters do not become attributes on a newly created record. */
    public function create(array $attributes): Record
    {
        return (new $this->model($this->database))->fill($attributes)->save();
    }
}
