<h1>Notes demo</h1>
<p>Shared development demo: anyone who can access this page can read, change, or delete these notes. Use sample data only.</p>
<?php if ($notice): ?><p role="status"><?= $e($notice) ?></p><?php endif ?>
<?php if ($errors): ?><div role="alert"><ul>
<?php foreach ($errors as $messages): foreach ($messages as $message): ?><li><?= $e($message) ?></li><?php endforeach; endforeach ?>
</ul></div><?php endif ?>
<h2><?= isset($note['id']) ? 'Edit note' : 'Create note' ?></h2>
<form method="post" action="<?= isset($note['id']) ? '/notes/' . $e($note['id']) : '/notes' ?>">
<input type="hidden" name="_token" value="<?= $e($token) ?>">
<p><label>Title <input name="title" required maxlength="100" value="<?= $e($note['title'] ?? '') ?>"></label></p>
<p><label>Body <textarea name="body" required maxlength="10000" rows="5" cols="50"><?= $e($note['body'] ?? '') ?></textarea></label></p>
<button type="submit">Save note</button> <a href="/notes">Cancel edit</a>
</form>
<h2>Latest 100 notes</h2>
<?php if ($notes === []): ?><p>No notes yet.</p><?php endif ?>
<?php foreach ($notes as $item): ?>
<article>
<h3><?= $e($item['title']) ?></h3>
<p style="white-space: pre-wrap"><?= $e($item['body']) ?></p>
<p><small><?= $e($item['created_at']) ?></small> <a href="/notes/<?= $e($item['id']) ?>/edit">Edit</a></p>
<form method="post" action="/notes/<?= $e($item['id']) ?>/delete">
<input type="hidden" name="_token" value="<?= $e($token) ?>"><button type="submit">Delete note</button>
</form>
</article>
<?php endforeach ?>
