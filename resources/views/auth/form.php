<h1><?= $e($title) ?></h1>
<?php if ($errors): ?><div role="alert"><ul>
<?php foreach ($errors as $messages): foreach ($messages as $message): ?><li><?= $e($message) ?></li><?php endforeach; endforeach ?>
</ul></div><?php endif ?>
<form method="post" action="/<?= $e($mode) ?>">
<input type="hidden" name="_token" value="<?= $e($token) ?>">
<?php if ($mode === 'register'): ?><p><label>Name <input name="name" required maxlength="100" autocomplete="name" value="<?= $e($old['name'] ?? '') ?>"></label></p><?php endif ?>
<p><label>Email <input type="email" name="email" required maxlength="254" autocomplete="username" value="<?= $e($old['email'] ?? '') ?>"></label></p>
<p><label>Password <input type="password" name="password" required autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>"></label></p>
<?php if ($mode === 'register'): ?>
<p>Use at least 15 characters and no more than 72 bytes. A long, unique passphrase works well.</p>
<p><label>Confirm password <input type="password" name="password_confirmation" required autocomplete="new-password"></label></p>
<?php endif ?>
<button type="submit"><?= $mode === 'register' ? 'Create account' : 'Sign in' ?></button>
</form>
<p><?php if ($mode === 'register'): ?><a href="/login">Already have an account? Sign in</a><?php else: ?><a href="/register">Create an account</a><?php endif ?></p>
