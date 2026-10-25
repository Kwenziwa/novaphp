<?php

declare(strict_types=1);

namespace App\Controllers;

use Nova\Http\Response;

final class HomeController
{
    public function index(): Response
    {
        $page = file_get_contents(dirname(__DIR__, 2) . '/resources/views/welcome.php');
        return Response::html($page === false ? 'Welcome to NovaPHP' : $page);
    }
}
