<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\NoteRepository;
use Nova\Http\{Request, Response};
use Nova\Session\Session;
use Nova\Validation\ValidationException;
use Nova\View\View;

final class NoteController
{
    public function __construct(private NoteRepository $notes, private Session $session, private View $view) {}

    private function render(?array $note = null, array $errors = [], int $status = 200): Response
    {
        return Response::html($this->view->render('notes.index', [
            'title' => 'Notes demo', 'notes' => $this->notes->all(), 'note' => $note,
            'errors' => $errors, 'token' => $this->session->token(),
            'notice' => $this->session->getFlash('notice'),
        ], 'layouts.app'), $status);
    }

    public function index(Request $request): Response
    {
        return $request->expectsJson() ? Response::json(['notes' => $this->notes->all()]) : $this->render();
    }

    private function find(string $id): ?array
    {
        if (!preg_match('/^[1-9][0-9]*$/D', $id) || filter_var($id, FILTER_VALIDATE_INT) === false) { return null; }
        return $this->notes->find((int) $id);
    }

    public function edit(string $id): Response
    {
        $note = $this->find($id);
        return $note === null ? Response::text('404 Not Found', 404) : $this->render($note);
    }

    private function save(Request $request, ?array $note): Response
    {
        try {
            $data = $request->validate(['title' => 'required|string|max:100', 'body' => 'required|string|max:10000']);
        } catch (ValidationException $error) {
            if ($request->expectsJson()) { throw $error; }
            $old = $note ?? [];
            foreach (['title' => 100, 'body' => 10000] as $field => $limit) {
                $value = $request->input($field);
                $old[$field] = is_string($value) ? substr($value, 0, $limit) : '';
            }
            return $this->render($old, $error->errors, 422);
        }
        if ($note === null) { $id = $this->notes->create($data); }
        else { $id = (int) $note['id']; $this->notes->update($id, $data); }
        if ($request->expectsJson()) { return Response::json(['note' => $this->notes->find($id)], $note === null ? 201 : 200); }
        $this->session->flash('notice', $note === null ? 'Note created.' : 'Note updated.');
        return Response::redirect('/notes');
    }

    public function store(Request $request): Response { return $this->save($request, null); }

    public function update(Request $request, string $id): Response
    {
        $note = $this->find($id);
        return $note === null ? Response::text('404 Not Found', 404) : $this->save($request, $note);
    }

    public function delete(Request $request, string $id): Response
    {
        $note = $this->find($id);
        if ($note === null) { return Response::text('404 Not Found', 404); }
        $this->notes->delete((int) $note['id']);
        if ($request->expectsJson()) { return Response::json(['deleted' => true]); }
        $this->session->flash('notice', 'Note deleted.');
        return Response::redirect('/notes');
    }
}
