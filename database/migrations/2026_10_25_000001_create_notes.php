<?php

declare(strict_types=1);

use Nova\Database\{Connection, Migration};

return new class implements Migration {
    public function up(Connection $database): void
    {
        $id = $database->driver() === 'mysql' ? 'INTEGER PRIMARY KEY AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $database->execute("CREATE TABLE notes (id $id, title VARCHAR(100) NOT NULL, body TEXT NOT NULL, created_at VARCHAR(32) NOT NULL)");
    }

    public function down(Connection $database): void
    {
        $database->execute('DROP TABLE notes');
    }
};
