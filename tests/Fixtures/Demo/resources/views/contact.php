<h1><?= $e($title) ?></h1>
<p>Try validation and flash messages. This demo does not send or store messages.</p>
<?php if ($notice): ?><p role="status"><?= $e($notice) ?></p><?php endif ?>
<?php if ($errors): ?>
<div role="alert"><ul>
<?php foreach ($errors as $messages): foreach ($messages as $message): ?>
<li><?= $e($message) ?></li>
<?php endforeach; endforeach ?>
</ul></div>
<?php endif ?>
<form method="post" action="/contact">
<input type="hidden" name="_token" value="<?= $e($token) ?>">
<p><label>Name <input name="name" required maxlength="100" value="<?= $e($old['name'] ?? '') ?>" autocomplete="name"></label></p>
<p><label>Email <input name="email" type="email" required maxlength="254" value="<?= $e($old['email'] ?? '') ?>" autocomplete="email"></label></p>
<button type="submit">Try form</button>
</form>
