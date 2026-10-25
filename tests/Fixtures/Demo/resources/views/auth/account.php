<h1>Your account</h1>
<p>Signed in as <?= $e($user['name']) ?> (<?= $e($user['email']) ?>).</p>
<p><a href="/notes">Your notes</a></p>
<form method="post" action="/logout"><input type="hidden" name="_token" value="<?= $e($token) ?>"><button type="submit">Sign out</button></form>
