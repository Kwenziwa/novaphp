<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Documentation\Library;
use Nova\Core\Application;
use Nova\Http\Response;
use Nova\Support\Markdown;
use Nova\View\View;
use RuntimeException;

final class DocumentationController
{
    public function __construct(private Application $app, private View $view, private Markdown $markdown) {}

    public function index(): Response { return $this->show('getting-started'); }

    public function show(string $page): Response
    {
        $entry = Library::PAGES[$page] ?? null;
        if ($entry === null) { return Response::text('404 Documentation page not found', 404); }
        // Only filenames from the fixed catalog may be read; route input is never a path.
        $source = file_get_contents($this->app->basePath . '/docs/' . $entry['file']);
        if ($source === false) { throw new RuntimeException('Documentation source is unavailable.'); }
        return Response::html($this->view->render('docs.page', [
            'title' => $entry['title'], 'page' => $page, 'description' => $entry['description'],
            'pages' => Library::PAGES, 'content' => $this->markdown->render($source),
        ]));
    }
}
