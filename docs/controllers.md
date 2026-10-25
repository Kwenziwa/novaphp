# Controllers

Controllers connect HTTP requests to your application. Keep validation and responses here, put persistent data rules in models or repositories, and render HTML with views.

## Generate a controller

```bash
php nova make:controller PostController
```

This creates app/Controllers/PostController.php with an index method. Names must be simple PHP class names ending in Controller. The command refuses to overwrite a file. It does not create routes or a complete resource controller automatically.

## Register controller routes

```php
use App\Controllers\PostController;

$router->get('/posts', [PostController::class, 'index']);
$router->get('/posts/{id}', [PostController::class, 'show']);
$router->post('/posts', [PostController::class, 'store']);
```

Add routes to routes/web.php or routes/api.php. The router builds class controllers through the container. Route argument names match placeholders, so {id} maps to $id. Explicitly validate identifiers before querying. Automatic model binding is not implemented.

## Inject services and use models

This example uses the existing Note model and notes table. Its method can be mapped to a GET route such as /notes-json/{id}:

```php
namespace App\Controllers;

use App\Models\Note;
use Nova\Database\Connection;
use Nova\Http\Response;

final class NoteReaderController
{
    public function __construct(private Connection $database) {}

    public function show(string $id): Response
    {
        if (!ctype_digit($id) || filter_var($id, FILTER_VALIDATE_INT) === false) {
            return Response::text('404 Not Found', 404);
        }

        $note = Note::using($this->database)->find((int) $id);
        return $note === null
            ? Response::text('404 Not Found', 404)
            : Response::json(['note' => $note]);
    }
}
```

Constructor dependencies are auto-wired. Handler arguments resolve in this order: current Request, named route parameter, declared default, then class dependency. Required scalar dependencies need a factory or default.

## Validate before writing

Inside a controller with an injected Connection:

```php
public function store(\Nova\Http\Request $request): \Nova\Http\Response
{
    $data = $request->validate([
        'title' => 'required|string|max:100',
        'body' => 'required|string|max:10000',
    ]);
    $note = \App\Models\Note::using($this->database)->create($data);
    return \Nova\Http\Response::json(['note' => $note], 201);
}
```

Import the Request, Response, and model classes when using this method inside a namespaced controller, or use their fully qualified names. Validation and model writable fields serve different purposes: validation checks input, while WRITABLE restricts which columns can be assigned. Never pass all request fields into a model, since tokens and other unrelated fields will be rejected.

Apply StartSession followed by VerifyCsrfToken to browser write routes, including JSON routes that use cookies. Existing notes routes already have both. See [forms and views](/docs/forms) and [routing](/docs/routing).

## Return responses and views

Handlers may return a Response, an array for JSON, or a string for HTML. Records can be serialized inside Response::json; their HIDDEN fields are omitted.

```php
return \Nova\Http\Response::html(
    $view->render('posts.index', ['posts' => $posts], 'layouts.app')
);
```

Use the view's $e helper for values in HTML text and quoted attributes. Templates are not automatically escaped. Handle missing records with a 404, and use local redirects for browser form flows.

The complete application example is app/Controllers/NoteController.php. It uses NoteRepository, which now delegates persistence to Nova Records while keeping the HTTP layer separate. A repository is optional; controllers can query a model directly when the operation is small.
