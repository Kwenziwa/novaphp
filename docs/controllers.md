# Controllers

Controllers connect HTTP requests to application services and responses. The clean starter includes only a welcome controller and the documentation controller; application controllers are generated when needed.

## Generate and register

```bash
php nova make:controller PostController
```

The generator writes app/Controllers/PostController.php with an index method and refuses an existing file. Add routes yourself:

```php
use App\Controllers\PostController;

$router->get('/posts', [PostController::class, 'index'], name: 'posts.index');
$router->get('/posts/{id}', [PostController::class, 'show'], name: 'posts.show');
$router->post('/posts', [PostController::class, 'store']);
```

## Inject services

```php
namespace App\Controllers;

use App\Models\Post;
use Nova\Database\Connection;
use Nova\Http\{Request, Response};

final class PostController
{
    public function __construct(private Connection $database) {}

    public function show(string $id): Response
    {
        if (!ctype_digit($id)) { return Response::text('404 Not Found', 404); }
        $post = Post::using($this->database)->find($id);
        return $post === null ? Response::text('404 Not Found', 404) : Response::json(['post' => $post]);
    }

    public function store(Request $request): Response
    {
        $data = $request->validate(['title' => 'required|string|max:150']);
        $post = Post::using($this->database)->create($data);
        return Response::json(['post' => $post], 201);
    }
}
```

Create the Post model/table first. This example assumes public posts; private data requires explicit authorization and owner-scoped queries. Route model binding is not automatic. Constructor services are auto-wired. Handler arguments resolve as current Request, named route parameter, default, then service dependency.

## Responses and failures

Handlers return Response, an array for JSON, or a string for HTML. Unhandled validation produces 422 with field errors. Throw HttpException for expected failures; production exceptions become safe 500 errors. See [routing](/docs/routing).

```php
throw new Nova\Http\HttpException(403, 'Forbidden');
```

Import the type or use its fully qualified name inside a namespace. Render PHP views with View::render and escape values through the template's $e helper. Apply sessions/CSRF to browser forms and authentication/authorization where needed. See [forms](/docs/forms) and [optional authentication](/docs/authentication).
