<?php

declare(strict_types=1);

use Nova\Database\{Connection, Field, Migration};

return new class implements Migration {
    public function up(Connection $database): void
    {
        $schema = $database->schema();
        $schema->create('users', [
            'id' => Field::id(), 'name' => Field::string(100),
            'email' => Field::string(254)->unique(), 'password_hash' => Field::string(255),
            'created_at' => Field::datetime(),
        ]);
        $schema->create('auth_attempts', [
            'key_hash' => Field::string(64)->unique(),
            'attempts' => Field::integer(), 'window_started' => Field::integer(),
        ]);
        $schema->index('auth_attempts', ['window_started'], name: 'auth_attempts_window_index');
        if ($database->driver() === 'mysql') {
            $database->execute('ALTER TABLE notes ADD COLUMN user_id BIGINT NULL, ADD CONSTRAINT notes_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
        } else {
            $database->execute('ALTER TABLE notes ADD COLUMN user_id INTEGER NULL REFERENCES users(id) ON DELETE CASCADE');
        }
        $schema->index('notes', ['user_id', 'id'], name: 'notes_owner_index');
        // Existing notes retain a null owner and are inaccessible to normal user queries.
    }

    public function down(Connection $database): void
    {
        $schema = $database->schema();
        $schema->dropIndex('notes', 'notes_owner_index');
        if ($database->driver() === 'mysql') { $database->execute('ALTER TABLE notes DROP FOREIGN KEY notes_user_fk'); }
        $schema->remove('notes', 'user_id');
        $schema->drop('auth_attempts');
        $schema->drop('users');
    }
};
