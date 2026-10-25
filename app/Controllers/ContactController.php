<?php

declare(strict_types=1);

namespace App\Controllers;

use Nova\Http\{Request, Response};
use Nova\Session\Session;
use Nova\Validation\ValidationException;
use Nova\View\View;

final class ContactController
{
    public function __construct(private Session $session, private View $view) {}

    public function show(): Response
    {
        return Response::html($this->view->render('contact', [
            'title' => 'Contact demo', 'token' => $this->session->token(),
            'notice' => $this->session->getFlash('notice'),
            'errors' => $this->session->getFlash('errors', []),
            'old' => $this->session->getFlash('old', []),
        ], 'layouts.app'));
    }

    public function store(Request $request): Response
    {
        try {
            $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:254']);
        } catch (ValidationException $error) {
            if ($request->expectsJson()) { throw $error; }
            $this->session->flash('errors', $error->errors);
            // Retain only these non-sensitive fields, with a bounded size.
            $old = [];
            foreach (['name', 'email'] as $field) {
                $value = $request->input($field);
                $old[$field] = is_string($value) ? substr($value, 0, 254) : '';
            }
            $this->session->flash('old', $old);
            return Response::redirect('/contact');
        }
        if ($request->expectsJson()) { return Response::json(['message' => 'Demo form accepted', 'name' => $data['name']]); }
        $this->session->flash('notice', 'Thanks, ' . $data['name'] . '! Demo form accepted; no message was sent or saved.');
        return Response::redirect('/contact');
    }
}
