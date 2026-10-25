<?php

declare(strict_types=1);

namespace App\Controllers;

use Nova\Core\Application;
use Nova\Http\Response;
use Nova\View\View;

final class HomeController
{
    public function __construct(private Application $app, private View $view) {}

    public function index(): Response
    {
        $file = $this->app->basePath . '/public/build/index.html';
        if (is_file($file)) {
            $html = file_get_contents($file);
            if ($html === false) { throw new \RuntimeException('Cannot read frontend build.'); }
            return Response::html($html);
        }
        return Response::html($this->view->render('frontend'));
    }
}
