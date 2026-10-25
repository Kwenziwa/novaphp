<?php

declare(strict_types=1);

namespace App\Models;

use Nova\Database\Record;

final class User extends Record
{
    protected const TABLE = 'users';
    protected const WRITABLE = ['name', 'email'];
    protected const HIDDEN = ['password_hash'];
}
