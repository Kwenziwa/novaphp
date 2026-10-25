<?php

declare(strict_types=1);

namespace Nova\Database;

use RuntimeException;

final class Commands
{
    public function __construct(private readonly Migrator $migrator, private readonly string $directory) {}

    public function run(string $command, ?string $name = null): void
    {
        if (!in_array($command, ['make:migration', 'migrate', 'migrate:status', 'migrate:rollback'], true)) {
            throw new RuntimeException('Unknown database command.');
        }
        if ($command === 'make:migration') {
            if ($name === null || !preg_match('/^[a-z][a-z0-9_]{0,99}$/D', $name)) {
                throw new RuntimeException('Use a snake_case migration name of at most 100 characters.');
            }
            $filename = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y_m_d_His_u') . '_' . bin2hex(random_bytes(3)) . '_' . $name . '.php';
            $file = @fopen($this->directory . '/' . $filename, 'x');
            if ($file === false) { throw new RuntimeException('Cannot create migration.'); }
            $source = <<<'SOURCE'
<?php

declare(strict_types=1);

use Nova\Database\{Connection, Migration};

return new class implements Migration {
    public function up(Connection $database): void
    {
        // $database->execute('CREATE TABLE ...');
    }

    public function down(Connection $database): void
    {
        // Reverse the schema changes made by up().
    }
};

SOURCE;
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
