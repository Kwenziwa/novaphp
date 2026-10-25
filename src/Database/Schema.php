<?php

declare(strict_types=1);

namespace Nova\Database;

use InvalidArgumentException;

final class Schema
{
    public function __construct(private readonly Connection $database) {}

    private function identifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/D', $name)) {
            throw new InvalidArgumentException('Schema identifiers must be simple names of at most 63 characters.');
        }
        return $this->database->driver() === 'mysql' ? '`' . $name . '`' : '"' . $name . '"';
    }

    private function literal(mixed $value): string
    {
        if ($value === null) { return 'NULL'; }
        if (is_bool($value)) { return $value ? '1' : '0'; }
        if (is_int($value) || is_float($value)) { return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION); }
        // Driver-aware quoting also handles MySQL backslashes and connection modes.
        return $this->database->pdo()->quote($value);
    }

    private function column(string $name, Field $field, bool $adding = false): string
    {
        $name = $this->identifier($name);
        $definition = $field->definition();
        $type = $definition['type'];
        $mysql = $this->database->driver() === 'mysql';
        if ($adding && ($type === 'id' || ($definition['unique'] ?? false))) {
            throw new InvalidArgumentException('Added fields cannot be IDs or unique; add a separate unique index.');
        }
        $sqlType = match ($type) {
            'id' => $mysql ? 'BIGINT PRIMARY KEY AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT',
            'string' => 'VARCHAR(' . $definition['length'] . ')',
            'text' => 'TEXT',
            'integer' => $mysql ? 'BIGINT' : 'INTEGER',
            'boolean' => $mysql ? 'BOOLEAN' : 'INTEGER',
            'datetime' => $mysql ? 'DATETIME' : 'TEXT',
            'decimal' => 'DECIMAL(' . $definition['precision'] . ', ' . $definition['scale'] . ')',
        };
        if ($adding && !($definition['nullable'] ?? false)
            && (!array_key_exists('default', $definition) || $definition['default'] === null)) {
            throw new InvalidArgumentException('Added required fields need a non-null default; otherwise use optional().');
        }
        $sql = $name . ' ' . $sqlType;
        if ($type === 'id') { return $sql; }
        if ($mysql && $type === 'text' && ($definition['unique'] ?? false)) {
            throw new InvalidArgumentException('Unique text fields need driver-specific index SQL; use a bounded string field instead.');
        }
        $nullable = $definition['nullable'] ?? false;
        $sql .= $nullable ? ' NULL' : ' NOT NULL';
        if (array_key_exists('default', $definition)) {
            if ($definition['default'] === null && !$nullable) {
                throw new InvalidArgumentException('A null default requires optional().');
            }
            if ($mysql && $type === 'text') {
                throw new InvalidArgumentException('Text defaults are not portable across MySQL versions; use a string field or raw SQL.');
            }
            $sql .= ' DEFAULT ' . $this->literal($definition['default']);
        }
        if ($definition['unique'] ?? false) { $sql .= ' UNIQUE'; }
        return $sql;
    }

    /** Compile CREATE TABLE without executing it. String defaults may open the connection for quoting. */
    public function createSql(string $table, array $fields): string
    {
        $table = $this->identifier($table);
        if ($fields === []) { throw new InvalidArgumentException('A table needs at least one field.'); }
        $columns = [];
        $ids = 0;
        $names = [];
        foreach ($fields as $name => $field) {
            if (!is_string($name) || !$field instanceof Field) { throw new InvalidArgumentException('Fields must be a named array of Field descriptions.'); }
            $key = strtolower($name);
            if (isset($names[$key])) { throw new InvalidArgumentException('Duplicate field name: ' . $name); }
            $names[$key] = true;
            if ($field->definition()['type'] === 'id') { $ids++; }
            $columns[] = $this->column($name, $field);
        }
        if ($ids > 1) { throw new InvalidArgumentException('A table can have only one ID field.'); }
        return 'CREATE TABLE ' . $table . ' (' . implode(', ', $columns) . ')'
            . ($this->database->driver() === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '');
    }

    public function create(string $table, array $fields): void
    {
        $this->database->execute($this->createSql($table, $fields));
    }

    public function add(string $table, array $fields): void
    {
        $table = $this->identifier($table);
        if ($fields === []) { throw new InvalidArgumentException('Provide at least one field to add.'); }
        $statements = [];
        foreach ($fields as $name => $field) {
            if (!is_string($name) || !$field instanceof Field) { throw new InvalidArgumentException('Fields must be a named array of Field descriptions.'); }
            $statements[] = 'ALTER TABLE ' . $table . ' ADD COLUMN ' . $this->column($name, $field, true);
        }
        foreach ($statements as $sql) { $this->database->execute($sql); }
    }

    public function remove(string $table, string $field): void
    {
        $table = $this->identifier($table);
        $field = $this->identifier($field);
        if ($this->database->driver() === 'sqlite') {
            $version = $this->database->first('SELECT sqlite_version() AS version')['version'];
            if (version_compare($version, '3.35.0', '<')) {
                throw new InvalidArgumentException('Removing fields requires SQLite 3.35+ or a manual table-rebuild migration.');
            }
        }
        $this->database->execute('ALTER TABLE ' . $table . ' DROP COLUMN ' . $field);
    }

    public function dropIndex(string $table, string $name): void
    {
        $table = $this->identifier($table);
        $name = $this->identifier($name);
        $this->database->execute('DROP INDEX ' . $name . ($this->database->driver() === 'mysql' ? ' ON ' . $table : ''));
    }

    public function rename(string $from, string $to): void
    {
        $this->database->execute('ALTER TABLE ' . $this->identifier($from) . ' RENAME TO ' . $this->identifier($to));
    }

    public function drop(string $table): void
    {
        $this->database->execute('DROP TABLE ' . $this->identifier($table));
    }

    public function dropIfExists(string $table): void
    {
        $this->database->execute('DROP TABLE IF EXISTS ' . $this->identifier($table));
    }

    public function index(string $table, array $fields, bool $unique = false, ?string $name = null): void
    {
        $quotedTable = $this->identifier($table);
        if ($fields === []) { throw new InvalidArgumentException('An index needs at least one field.'); }
        $columns = [];
        foreach ($fields as $field) {
            if (!is_string($field)) { throw new InvalidArgumentException('Index fields must be names.'); }
            $columns[] = $this->identifier($field);
        }
        $name ??= substr($table, 0, 30) . '_' . substr(hash('sha256', json_encode([$table, $fields, $unique], JSON_THROW_ON_ERROR)), 0, 16) . '_idx';
        $this->database->execute('CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX ' . $this->identifier($name)
            . ' ON ' . $quotedTable . ' (' . implode(', ', $columns) . ')');
    }
}
