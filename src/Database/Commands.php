<?php

declare(strict_types=1);

namespace Nova\Database;

use RuntimeException;

final class Commands
{
    public function __construct(private readonly Migrator $migrator, private readonly string $directory) {}

    private function starter(?string $table, bool $sql): string
    {
        $imports = $sql ? 'Connection, Migration' : 'Connection, Field, Migration';
        if ($table === null) {
            $up = $sql ? "// \$database->execute('ALTER TABLE ...');"
                : "// \$database->schema()->add('your_table', ['label' => Field::string()->optional()]);";
            $down = '// Reverse the changes made by up(), using schema helpers or raw SQL.';
        } elseif ($sql) {
            $up = '$id = $database->driver() === \'mysql\' ? \'BIGINT PRIMARY KEY AUTO_INCREMENT\' : \'INTEGER PRIMARY KEY AUTOINCREMENT\';';
            $up .= "\n        \$database->execute(\"CREATE TABLE $table (id \$id)\");";
            $down = "\$database->execute('DROP TABLE $table');";
        } else {
            $up = "\$database->schema()->create('$table', [\n            'id' => Field::id(),\n            // 'title' => Field::string(150),\n        ]);";
            $down = "\$database->schema()->drop('$table');";
        }
        return "<?php\n\ndeclare(strict_types=1);\n\nuse Nova\\Database\\{{$imports}};\n\nreturn new class implements Migration {\n    public function up(Connection \$database): void\n    {\n        $up\n    }\n\n    public function down(Connection \$database): void\n    {\n        $down\n    }\n};\n";
    }

    public function run(string $command, ?string $name = null, bool $sql = false): void
    {
        if (!in_array($command, ['make:migration', 'migrate', 'migrate:status', 'migrate:rollback'], true)) {
            throw new RuntimeException('Unknown database command.');
        }
        if ($command === 'make:migration') {
            if ($name === null || !preg_match('/^[a-z][a-z0-9_]{0,99}$/D', $name)) {
                throw new RuntimeException('Use a snake_case migration name of at most 100 characters.');
            }
            $table = str_starts_with($name, 'create_') ? substr($name, 7) : null;
            if ($table !== null && !preg_match('/^[a-z][a-z0-9_]{0,62}$/D', $table)) {
                throw new RuntimeException('Inferred table names must be simple names of at most 63 characters.');
            }
            $source = $this->starter($table, $sql);
            $filename = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y_m_d_His_u') . '_' . bin2hex(random_bytes(3)) . '_' . $name . '.php';
            $file = @fopen($this->directory . '/' . $filename, 'x');
            if ($file === false) { throw new RuntimeException('Cannot create migration.'); }
            try {
                if (fwrite($file, $source) !== strlen($source)) { throw new RuntimeException('Cannot write migration.'); }
            } finally { fclose($file); }
            echo 'Created database/migrations/' . $filename . "\n";
            return;
        }
        if ($command === 'migrate:status') {
            foreach ($this->migrator->status() as $row) {
                printf("%-8s batch=%s %s%s\n", $row['status'], $row['batch'] ?? '-', $row['migration'], $row['file_exists'] ? '' : ' [missing file]');
            }
            return;
        }
        $names = $command === 'migrate' ? $this->migrator->migrate() : $this->migrator->rollback();
        foreach ($names as $migration) { echo ($command === 'migrate' ? 'Migrated: ' : 'Rolled back: ') . $migration . "\n"; }
        if ($names === []) { echo "Nothing to do.\n"; }
    }
}
