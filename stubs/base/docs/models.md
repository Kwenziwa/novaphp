# Models and Nova Records

Nova Records is NovaPHP's own ORM. It uses explicit database connections, writable-field allowlists, immutable queries, casts, optional timestamps, and explicit relations. The clean starter ships no application models or tables.

## Generate a model

```bash
php nova make:model Post
php nova make:model Person --table=people
```

The generator infers common plurals, rejects invalid names, and refuses existing files. It does not create a migration. Define your model's table and allowed fields:

```php
namespace App\Models;

use Nova\Database\Record;

final class Post extends Record
{
    protected const TABLE = 'posts';
    protected const WRITABLE = ['title', 'body', 'published'];
    protected const CASTS = ['published' => 'bool'];
}
```

TABLE is required. The primary key defaults to an auto-increment integer id; KEY can name another integer auto-increment column. Composite, UUID, string, and manually assigned keys are not supported. Create the table through a [migration](/docs/migrations) before querying.

## Find records

```php
use App\Models\Post;

$query = Post::using($database);
$posts = $query->where('published', true)->orderBy('id', 'desc')->take(20)->all();
$post = $query->find(1);
$first = $query->where('title', 'LIKE', '%Nova%')->first();
$total = $query->count();
```

all returns an array of model objects; first/find return a model or null. Each filter returns a new query, leaving the original reusable. where accepts equality with two arguments, or an explicit =, !=, <>, <, <=, >, >=, LIKE, or NOT LIKE operator. Conditions are combined with AND. Identifiers/operators are validated and values are bound separately from SQL.

```php
$selected = $query->whereIn('id', [1, 2, 3])->all();
$missing = $query->whereNull('body')->all();
$present = $query->where('body', '!=', null)->all();
```

An empty whereIn set matches nothing. Null equality/inequality use IS NULL/IS NOT NULL. Use whereNull explicitly for null matches in sets. Query bindings use database values; attribute casts do not transform query arguments. There are no OR groups, joins, or raw conditions in this version.

## Pagination

```php
$page = $query->where('published', true)->orderBy('id', 'desc')->paginate(perPage: 20, page: 2);
return Nova\Http\Response::json($page);
```

Page contains items, total, perPage, and currentPage. JSON returns data plus meta with total, per_page, current_page, last_page, from, and to. Empty pages have null from/to. Query pagination uses a primary-key order unless another order is supplied. It runs a count plus a row query, not an atomic snapshot; concurrent writes may affect totals. Build page links with [named routes](/docs/routing). take/skip remain available, with a 10000-row size limit. count ignores ordering and pagination.

## Create, update, delete

```php
$post = Post::using($database)->create(['title' => 'Hello', 'body' => 'A post', 'published' => false]);
$post->title = 'Changed';
$post->save();
$post->fill(['body' => 'New body'])->save();
$array = $post->toArray();
$post->refresh();
$post->delete();
```

create returns a saved model; save inserts new records or updates changed fields and reloads values. An unchanged save is a no-op. fill rejects fields outside WRITABLE and leaves attributes unchanged on failure. Query filters do not become create attributes. Validate input separately. Primary keys and automatic timestamps are not writable. Deleted objects cannot be saved again.

Dirty writes preserve unrelated columns but are not optimistic locking for edits to the same field. refresh/dirty saves detect missing rows; unchanged saves do not check deletion. Transactions use the same Connection; rollback restores the database, not existing PHP objects. Discard or reload objects after rollback.

## Casts, hidden data, and timestamps

```php
protected const CASTS = ['published' => 'bool', 'views' => 'int', 'rating' => 'float', 'settings' => 'json'];
protected const HIDDEN = ['secret'];
protected const CREATED_AT = 'created_at';
protected const UPDATED_AT = 'updated_at';
```

Casts are int, float, bool, string, and json; null remains null. JSON accepts arrays and stores JSON text. Replace the whole array when editing. Boolean inputs are true/false, 0/1, or strings 0/1. Floats are unsuitable for exact money arithmetic. Hidden fields are omitted from toArray/JSON but remain accessible; hiding is not encryption or authorization.

Timestamps are opt-in and need matching columns. Default DATE_FORMAT is UTC Y-m-d H:i:s; override it if your schema uses a different representation. The starter assumes no timestamp columns.

## Relations

For application models Post and Comment, with comments.post_id:

```php
// Post method:
public function comments(): \Nova\Database\RecordQuery
{
    return $this->hasMany(Comment::class, 'post_id');
}

// Comment method:
public function post(): ?\Nova\Database\Record
{
    return $this->belongsTo(Post::class, 'post_id');
}
```

hasMany requires a saved record and returns a filterable query. belongsTo returns a model or null and accepts an optional owner-column argument. Calls use the parent's connection. Relations are explicit, uncached queries; there is no eager loading, automatic foreign-key assignment, or cascading save. Declare database foreign keys separately. Calling relation methods for every row can produce N+1 queries.

## Authorization and limits

Models do not automatically scope queries to a logged-in user. Applications must authorize records and take owner IDs from trusted server context, not request fields. See [optional authentication](/docs/authentication).

Soft deletes, eager loading, events, scopes, bulk writes, collections, and route model binding remain future work. Raw Connection SQL remains available. SQLite is locally tested; MySQL has a dedicated CI test, which must pass before claiming MySQL verification.
