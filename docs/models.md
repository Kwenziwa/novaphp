# Models and Nova Records

Nova Records is NovaPHP's small ORM: a model represents a database row, and an immutable query describes how to find rows. It uses the existing PDO Connection and has no third-party packages. Connections are explicit, so separate applications or tests do not share global model state.

## Create a model

```bash
php nova make:model Post
php nova make:model Person --table=people
```

Models live in app/Models. The generator writes a TABLE constant and an empty WRITABLE list; add allowed fields before saving. It infers common table plurals such as posts and categories, but does not implement a full language inflector. Use --table for irregular names. The generator rejects invalid names, unknown options, and existing files. It does not create or run a migration: define your table separately with [migrations](/docs/migrations).

## Define allowed fields

The included Note model uses the existing notes table:

```php
namespace App\Models;

use Nova\Database\Record;

final class Note extends Record
{
    protected const TABLE = 'notes';
    protected const WRITABLE = ['title', 'body'];
    protected const CREATED_AT = 'created_at';
    protected const DATE_FORMAT = 'c';
}
```

TABLE is required. Models use an auto-increment integer primary key named id by default; override KEY for another integer auto-increment column name. Composite, UUID, string, and manually assigned primary keys are not supported in this version. Primary keys and automatic timestamp fields cannot be mass-assigned, even if listed in WRITABLE.

## Query records

Inject Nova\Database\Connection into your controller or repository, then choose the model:

```php
use App\Models\Note;

$notes = Note::using($database)
    ->where('title', 'LIKE', '%Nova%')
    ->orderBy('id', 'desc')
    ->take(20)
    ->skip(0)
    ->all();

$note = Note::using($database)->find(1);
$first = Note::using($database)->where('title', 'Welcome')->first();
$total = Note::using($database)->count();
```

all returns an array of model objects. first and find return a model or null. count counts filtered rows and ignores sorting and pagination. Unbounded all loads all matching rows, so use take for lists. take accepts 1 through 10000; skip accepts non-negative offsets. Use a deterministic order, normally a unique key, when paginating.

Supported comparisons are =, !=, <>, <, <=, >, >=, LIKE, and NOT LIKE. Two arguments to where mean equality. Chained conditions are AND conditions. There are no raw conditions or OR groups yet.

```php
$query = Note::using($database);
$selected = $query->whereIn('id', [1, 2, 3])->all();
$missingBody = $query->whereNull('body')->all();
$presentBody = $query->where('body', '!=', null)->all();
```

Queries are immutable: filtering $query does not change the original query. An empty whereIn set matches nothing. Null equality produces IS NULL; null inequality produces IS NOT NULL. IN lists follow database null semantics; use whereNull explicitly for null values. Filters use database values; casts do not transform query bindings. Identifiers and sort directions are validated, while values are parameterized. Choose fields and operators in trusted code rather than exposing an unrestricted query interface to request input.

## Create, change, and delete

```php
$note = Note::using($database)->create([
    'title' => 'Hello NovaPHP',
    'body' => 'My first record.',
]);

$note->title = 'Updated title';
$note->save();

$note->fill(['title' => 'Another title', 'body' => 'Changed'])->save();
$array = $note->toArray();
$note->refresh();
$deleted = $note->delete();
```

create returns a saved model. save inserts a new model or updates only changed fields on an existing one, then reloads database values. An unchanged save is a no-op. You can also construct new Note($database), fill it, and call save. Query filters on a query used for create do not become attributes or constrain the inserted record; use a fresh using query for clarity.

fill and property assignment reject unlisted fields instead of silently dropping them. A failed fill leaves its attributes unchanged. Validate request data before fill or create; the model allowlist does not replace validation, authorization, or database constraints.

delete returns true when a row was removed and marks the object deleted. Saving or deleting that object again throws. refresh and dirty saves detect a missing database row; an unchanged save does not check for external deletion. Dirty writes preserve unrelated column updates but do not provide optimistic locking for concurrent edits to the same field.

## Casts and hidden attributes

For a model whose table has these columns, configure:

```php
protected const WRITABLE = ['title', 'published', 'views', 'rating', 'settings', 'secret'];
protected const CASTS = [
    'published' => 'bool',
    'views' => 'int',
    'rating' => 'float',
    'settings' => 'json',
];
protected const HIDDEN = ['secret'];
```

Supported casts are int, float, bool, string, and json. Null stays null. JSON casts accept PHP arrays and store JSON text; read them back as arrays. Replace the whole array through assignment or fill when changing a JSON field. Integer casts reject invalid integers; boolean casts accept true/false, 0/1, and the strings 0/1. Float casts use ordinary floating-point arithmetic and are unsuitable for exact money calculations. SQL decimal precision does not make PHP floats exact.

The primary key is read as an integer by default. HIDDEN removes fields from toArray and JSON serialization, but they remain accessible on the model. It is an output filter, not encryption or authorization. Unknown casts fail explicitly.

## Timestamps

Automatic timestamps are opt-in:

```php
protected const CREATED_AT = 'created_at';
protected const UPDATED_AT = 'updated_at';
protected const DATE_FORMAT = 'Y-m-d H:i:s';
```

Create the corresponding columns in a migration. The default date format is UTC YYYY-MM-DD HH:MM:SS, suitable for MySQL DATETIME. The existing Note model retains its original ISO 8601 string format because its created_at column is VARCHAR. No timestamp columns are assumed when the constants are null.

## Relations

For tables posts and comments with a post_id column on comments, add methods to your models:

```php
// In Post:
public function comments(): \Nova\Database\RecordQuery
{
    return $this->hasMany(Comment::class, 'post_id');
}

// In Comment:
public function post(): ?\Nova\Database\Record
{
    return $this->belongsTo(Post::class, 'post_id');
}
```

Import the Record and RecordQuery types or use fully qualified names inside a namespace. Related model names must resolve to Record subclasses. hasMany returns a query that can be filtered and sorted; belongsTo returns a related model or null. Calls use the parent's connection. hasMany requires a saved record. A null foreign key yields null for belongsTo. Pass an optional third argument to belongsTo to use a different owner column.

```php
$comments = $post->comments()->orderBy('id', 'desc')->take(10)->all();
$owner = $comment->post();
```

Relations are explicit method calls and perform queries when called. They are not automatically cached or eager-loaded. Calling them for each row can produce N+1 queries. Foreign-key columns, database constraints, and any desired cascade behavior must be defined separately in migrations. Saving a relation does not automatically assign foreign keys or cascade writes.

## Transactions, raw SQL, and limits

```php
$database->transaction(function (Nova\Database\Connection $database) {
    Note::using($database)->create(['title' => 'One', 'body' => 'First']);
    Note::using($database)->create(['title' => 'Two', 'body' => 'Second']);
});
```

Use the same connection for model operations and the transaction. A rollback restores the database, not existing PHP objects; discard or reload those objects after rollback. Nested transactions remain unsupported. Raw SQL through Connection remains available for operations outside the ORM.

This version supports row persistence, basic immutable queries, casts, timestamps, serialization, and explicit relations. Eager loading, joins, query scopes, bulk writes, soft deletes, events, collections, and automatic route model binding are future work. SQLite is tested end-to-end; the MySQL path is implemented but has not been verified against a live MySQL server.
