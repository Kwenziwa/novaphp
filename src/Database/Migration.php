<?php

declare(strict_types=1);

namespace Nova\Database;

interface Migration
{
    public function up(Connection $database): void;
    public function down(Connection $database): void;
}
