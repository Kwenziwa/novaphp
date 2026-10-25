<?php

declare(strict_types=1);

use App\Documentation\Library;
use Nova\Http\Request;
use Nova\Support\Markdown;

$documentationApp = require dirname(__DIR__) . '/bootstrap/app.php';
$home = $documentationApp->handle(new Request('GET', '/docs'));
check($home->status === 200 && str_contains($home->body, 'Getting started') && str_contains($home->body, '/assets/docs.css'), 'documentation home renders guide and stylesheet');
foreach (Library::PAGES as $slug => $entry) {
    $response = $documentationApp->handle(new Request('GET', '/docs/' . $slug));
    check($response->status === 200 && str_contains($response->body, 'aria-current="page"') && str_contains($response->body, '<h1'), 'documentation page: ' . $slug);
}
check($documentationApp->handle(new Request('GET', '/docs/not-found'))->status === 404, 'unknown documentation page returns 404');
check($documentationApp->handle(new Request('GET', '/docs/..%2F.env'))->status === 404, 'documentation rejects path-like page names');
check($documentationApp->handle(new Request('POST', '/docs'))->status === 405, 'documentation is read only');
$migrations = $documentationApp->handle(new Request('GET', '/docs/migrations'))->body;
check(str_contains($migrations, 'schema()-&gt;create') && str_contains($migrations, '--sql'), 'migration documentation displays both APIs');
$renderer = new Markdown();
$html = $renderer->render("# Hello\n\n# Hello\n\n```php\n<?php echo '<script>';\n```\n\n<script>alert(1)</script>\n\n[unsafe](javascript:alert) [guide](/docs/migrations) [site](https://example.com)\n");
check(!str_contains($html, '<script>') && str_contains($html, '&lt;?php') && str_contains($html, '&lt;script&gt;'), 'documentation escapes raw HTML and PHP code');
check(!str_contains($html, 'href="javascript:') && str_contains($html, 'href="/docs/migrations"'), 'documentation links reject executable schemes');
check(str_contains($html, 'id="hello"') && str_contains($html, 'id="hello-2"'), 'documentation heading anchors are unique');
$table = $renderer->render("| Name | Purpose |\n| --- | --- |\n| `id` | **Key** |\n\n- One\n- Two\n");
check(str_contains($table, '<table>') && str_contains($table, '<code>id</code>') && str_contains($table, '<li>Two</li>'), 'documentation renders tables lists and inline code');

$models = $documentationApp->handle(new Request('GET', '/docs/models'))->body;
check(str_contains($models, 'Nova Records') && str_contains($models, 'WRITABLE') && str_contains($models, 'belongsTo'), 'models guide documents writes protection and relations');
$controllers = $documentationApp->handle(new Request('GET', '/docs/controllers'))->body;
check(str_contains($controllers, 'make:controller') && str_contains($controllers, 'Request') && str_contains($controllers, '404'), 'controllers guide documents generation injection and missing records');
