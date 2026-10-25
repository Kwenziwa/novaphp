<?php

// Copy into a generated migration file; examples are not automatically applied.
declare(strict_types=1);

use Nova\Database\{Connection, Field, Migration};

return new class implements Migration {
    public function up(Connection $database): void
    {
        $database->schema()->create('posts', [
            'id' => Field::id(),
            'title' => Field::string(150),
            'slug' => Field::string(150)->unique(),
            'body' => Field::text()->optional(),
            'published' => Field::boolean()->defaultsTo(false),
        ]);

        // Schema helpers and parameterized SQL can be used together.
        $database->execute('INSERT INTO posts (title, slug) VALUES (?, ?)', ['Welcome', 'welcome']);
    }

    public function down(Connection $database): void
    {
        $database->schema()->drop('posts');
    }
};
