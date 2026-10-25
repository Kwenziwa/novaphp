<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= $e($description) ?>">
<title><?= $e($title) ?> · NovaPHP Docs</title>
<link rel="stylesheet" href="/assets/docs.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="topbar"><a class="brand" href="/docs"><span class="mark" aria-hidden="true">N</span> NovaPHP <span class="badge">Docs</span></a><a href="/">Back to app ↗</a></header>
<div class="docs-shell">
<aside class="sidebar"><p class="eyebrow">THE GUIDE</p><nav aria-label="Documentation">
<?php foreach ($pages as $slug => $entry): ?>
<a href="/docs/<?= $e($slug) ?>"<?= $slug === $page ? ' aria-current="page"' : '' ?>><?= $e($entry['title']) ?></a>
<?php endforeach ?>
</nav><p class="sidebar-note">Independent PHP.<br>Small pieces. Clear foundations.</p></aside>
<main id="main" tabindex="-1">
<div class="page-meta"><span>NovaPHP · Phase 4</span><span>PHP 8.2+</span></div>
<article class="prose"><?= $content /* HTML produced by the escaping Markdown renderer. */ ?></article>
<?php $keys = array_keys($pages); $position = array_search($page, $keys, true); ?>
<nav class="page-turns" aria-label="Guide pages">
<?php if ($position > 0): $previous = $keys[$position - 1]; ?><a href="/docs/<?= $e($previous) ?>"><small>← Previous</small><?= $e($pages[$previous]['title']) ?></a><?php else: ?><span></span><?php endif ?>
<?php if ($position < count($keys) - 1): $next = $keys[$position + 1]; ?><a href="/docs/<?= $e($next) ?>"><small>Next →</small><?= $e($pages[$next]['title']) ?></a><?php endif ?>
</nav>
<footer>Educational framework · Early development</footer>
</main>
</div>
</body>
</html>
