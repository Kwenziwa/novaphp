<?php

declare(strict_types=1);

use Nova\Database\{Connection, Field, ModelGenerator, Record};

final class TestArticle extends Record
{
    protected const TABLE = 'articles';
    protected const WRITABLE = ['title', 'body', 'active', 'views', 'score', 'meta', 'secret'];
    protected const CASTS = ['active' => 'bool', 'views' => 'int', 'score' => 'float', 'meta' => 'json'];
    protected const HIDDEN = ['secret'];
    protected const CREATED_AT = 'created_at';
    protected const UPDATED_AT = 'updated_at';
}
final class TestReply extends Record
{
    protected const TABLE = 'replies';
    protected const WRITABLE = ['article_id', 'body'];
    protected const CASTS = ['article_id' => 'int'];
}

$recordDb = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
$makeTables = static function (Connection $db): void {
    $db->schema()->create('articles', [
        'id' => Field::id(), 'title' => Field::string(100), 'body' => Field::text()->optional(),
        'active' => Field::boolean()->defaultsTo(false), 'views' => Field::integer()->defaultsTo(0),
        'score' => Field::decimal(10, 2)->optional(), 'meta' => Field::text()->optional(),
        'secret' => Field::string()->optional(), 'created_at' => Field::datetime(), 'updated_at' => Field::datetime(),
    ]);
    $db->schema()->create('replies', ['id' => Field::id(), 'article_id' => Field::integer()->optional(), 'body' => Field::text()]);
};
$makeTables($recordDb);
$article = TestArticle::using($recordDb)->create(['title' => 'Nova', 'active' => true, 'views' => '2', 'meta' => ['tags' => ['php']], 'secret' => 'hidden']);
check($article instanceof TestArticle && $article->id === 1 && $article->exists(), 'records create and hydrate model instances');
check($article->active === true && $article->views === 2 && $article->meta === ['tags' => ['php']], 'records cast booleans integers and JSON');
check($article->body === null && $article->score === null, 'records load database defaults and preserve null casts');
check($article->created_at !== null && $article->updated_at !== null, 'records populate configured timestamps');
check(!array_key_exists('secret', $article->toArray()) && !str_contains(json_encode($article), 'hidden') && $article->secret === 'hidden', 'records hide fields in array and JSON serialization');
$article->title = 'Changed';
$article->score = '1.5';
$article->save();
check(TestArticle::using($recordDb)->find(1)->title === 'Changed' && $article->score === 1.5, 'record property changes save and refresh');
$article->save();
check($article->exists(), 'unchanged record save is a no-op');
$otherCopy = TestArticle::using($recordDb)->find(1);
$recordDb->execute('UPDATE articles SET body = ? WHERE id = ?', ['Concurrent edit', 1]);
$otherCopy->title = 'Title only';
$otherCopy->save();
check($otherCopy->body === 'Concurrent edit', 'dirty saves preserve unrelated concurrent field changes');
$article->refresh();
check($article->title === 'Title only', 'refresh reloads external updates');
schemaReject(fn () => $article->fill(['title' => 'Must not change', 'id' => 999]), 'records reject mass-assigned primary keys');
check($article->title === 'Title only', 'failed fill leaves all attributes unchanged');
schemaReject(fn () => $article->fill(['unexpected' => 'x']), 'records reject unlisted attributes');
schemaReject(fn () => $article->fill(['created_at' => 'tampered']), 'records protect automatic timestamps');
schemaReject(fn () => $article->fill(['active' => 'false']), 'boolean casts reject ambiguous strings');
schemaReject(fn () => $article->fill(['views' => 'invalid']), 'integer casts reject invalid values');
schemaReject(fn () => $article->fill(['meta' => 'invalid JSON']), 'JSON casts require arrays');

TestArticle::using($recordDb)->create(['title' => 'Second', 'views' => 10]);
TestArticle::using($recordDb)->create(['title' => "x' OR 1=1 --", 'views' => 20]);
$query = TestArticle::using($recordDb);
check($query->where('title', "x' OR 1=1 --")->count() === 1 && $query->count() === 3, 'record queries bind hostile values and remain immutable');
check($query->where('views', '>=', 10)->orderBy('views', 'desc')->first()->views === 20, 'record filters ordering and first');
check(count($query->orderBy('id')->take(1)->skip(1)->all()) === 1 && $query->orderBy('id')->take(1)->skip(1)->first()->title === 'Second', 'record take and skip paginate');
check(count($query->orderBy('id')->skip(2)->all()) === 1, 'record offset works without a limit');
check($query->whereIn('id', [1, 2])->count() === 2 && $query->whereIn('id', [])->count() === 0, 'record whereIn includes empty-set semantics');
check($query->whereNull('meta')->count() === 2 && $query->where('meta', '!=', null)->count() === 1, 'record null comparisons');
check($query->take(1)->skip(2)->count() === 3, 'record count ignores pagination');
check($query->find(999) === null && $query->where('title', 'absent')->first() === null, 'missing records return null');
schemaReject(fn () => $query->where('title; DROP TABLE articles', 'x'), 'record queries reject identifier injection');
schemaReject(fn () => $query->where('id', '= ? OR 1=1 --', 1), 'record queries reject operator injection');
schemaReject(fn () => $query->orderBy('title', 'desc; DROP TABLE articles'), 'record queries reject sort injection');
schemaReject(fn () => $query->where('id', []), 'record queries reject non-scalar values');
schemaReject(fn () => $query->take(0), 'record query limits must be positive');
schemaReject(fn () => $query->skip(-1), 'record offsets cannot be negative');

$reply = TestReply::using($recordDb)->create(['article_id' => 1, 'body' => 'Hello']);
check($reply->belongsTo(TestArticle::class, 'article_id')->id === 1, 'records belongsTo resolves related model');
check($article->hasMany(TestReply::class, 'article_id')->first()->body === 'Hello', 'records hasMany returns scoped query');
$orphan = TestReply::using($recordDb)->create(['article_id' => null, 'body' => 'Orphan']);
check($orphan->belongsTo(TestArticle::class, 'article_id') === null, 'nullable belongsTo returns null');
fails(fn () => (new TestArticle($recordDb))->hasMany(TestReply::class, 'article_id'), 'Relations require a saved record');
schemaReject(fn () => $article->hasMany(stdClass::class, 'article_id'), 'relations reject non-model classes');

$isolated = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
$makeTables($isolated);
TestArticle::using($isolated)->create(['title' => 'Other connection']);
check(TestArticle::using($recordDb)->count() === 3 && TestArticle::using($isolated)->count() === 1, 'records keep connections isolated without global state');
try {
    $recordDb->transaction(function (Connection $db): void {
        TestArticle::using($db)->create(['title' => 'Rollback']);
        throw new RuntimeException('abort record write');
    });
} catch (RuntimeException) {}
check(TestArticle::using($recordDb)->where('title', 'Rollback')->count() === 0, 'records participate in connection transactions');
$recordDb->execute('DELETE FROM articles WHERE id = ?', [2]);
$stale = TestArticle::hydrate($recordDb, ['id' => 2, 'title' => 'stale']);
$stale->title = 'changed';
fails(fn () => $stale->save(), 'Record no longer exists');
check($article->delete() && !$article->exists() && TestArticle::using($recordDb)->find(1) === null, 'record delete removes persisted row');
fails(fn () => $article->save(), 'Cannot save a deleted record');
fails(fn () => $article->delete(), 'Cannot delete an unsaved or deleted record');

$directory = sys_get_temp_dir() . '/nova-models-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
try {
    $generator = new ModelGenerator($directory);
    $path = $generator->make('NovaTestCategory', 'articles');
    require $path;
    check(is_subclass_of(App\Models\NovaTestCategory::class, Record::class) && App\Models\NovaTestCategory::tableName() === 'articles', 'model generator creates executable model with table override');
    $source = file_get_contents($path);
    fails(fn () => $generator->make('NovaTestCategory', 'articles'), 'Model exists');
    check(file_get_contents($path) === $source, 'model generator refuses overwrite');
    schemaReject(fn () => $generator->make('../Escape'), 'model generator rejects path traversal');
    schemaReject(fn () => $generator->make('Class'), 'model generator rejects reserved PHP names');
    schemaReject(fn () => $generator->make('ValidName', 'bad;name'), 'model generator rejects table injection');
    $plural = $generator->make('Category');
    check(str_contains(file_get_contents($plural), "TABLE = 'categories'"), 'model generator infers common plural table names');
} finally {
    foreach (glob($directory . '/*') ?: [] as $file) { unlink($file); }
    rmdir($directory);
}

$cli = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/nova');
$name = 'NovaFixture' . bin2hex(random_bytes(5));
$path = dirname(__DIR__) . '/app/Models/' . $name . '.php';
try {
    exec($cli . ' make:model ' . escapeshellarg($name) . ' --table=articles', $output, $status);
    check($status === 0 && str_contains(file_get_contents($path), "TABLE = 'articles'"), 'CLI make:model supports table override');
    exec($cli . ' make:model ' . escapeshellarg($name) . ' --bad 2>&1', $ignored, $status);
    check($status === 1, 'CLI make:model rejects unknown flags');
} finally { if (is_file($path)) { unlink($path); } }
