<?php

declare(strict_types=1);

namespace App\Models;

use Nova\Database\Record;

final class Note extends Record
{
    protected const TABLE = 'notes';
    protected const WRITABLE = ['title', 'body'];
    protected const CREATED_AT = 'created_at';
    // Preserve the notes demo's original ISO 8601 timestamp representation.
    protected const DATE_FORMAT = 'c';
}
