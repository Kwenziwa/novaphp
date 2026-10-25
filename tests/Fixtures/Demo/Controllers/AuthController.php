<?php

declare(strict_types=1);

namespace Tests\Fixtures\Demo\Controllers;

use Nova\Auth\{Auth, RateLimitException};
use Nova\Http\{Request, Response};
use Nova\Session\Session;
use Nova\Validation\ValidationException;
use Nova\View\View;

final class AuthController
{
    public function __construct(private Auth $auth, private Session $session, private View $view) {}

    private function json(array $data, int $status = 200): Response
    {
        $response = Response::json($data, $status);
        return new Response($response->body, $status, [...$response->headers, 'Cache-Control' => 'no-store']);
    }

    private function form(string $mode, array $errors = [], array $old = [], int $status = 200): Response
    {
        return new Response($this->view->render('auth.form', [
            'title' => $mode === 'register' ? 'Create your account' : 'Sign in',
            'mode' => $mode, 'token' => $this->session->token(), 'errors' => $errors, 'old' => $old,
        ], 'layouts.app'), $status, ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public function loginForm(): Response { return $this->auth->user() === null ? $this->form('login') : Response::redirect('/notes'); }
    public function registerForm(): Response { return $this->auth->user() === null ? $this->form('register') : Response::redirect('/notes'); }

    private function fail(Request $request, string $mode, array $errors, int $status): Response
    {
        if ($request->expectsJson()) { return $this->json(['errors' => $errors], $status); }
        $old = [];
        foreach (['name', 'email'] as $field) {
            $value = $request->body($field);
            $old[$field] = is_string($value) ? substr($value, 0, 254) : '';
        }
        return $this->form($mode, $errors, $old, $status);
    }

    private function throttled(Request $request, string $mode, RateLimitException $error): Response
    {
        $response = $this->fail($request, $mode, ['attempts' => [$error->getMessage()]], 429);
        return new Response($response->body, 429, [...$response->headers, 'Retry-After' => (string) $error->retryAfter, 'Cache-Control' => 'no-store']);
    }

    public function register(Request $request): Response
    {
        if ($this->auth->user() !== null) { return Response::redirect('/notes'); }
        try {
            $data = (new \Nova\Validation\Validator())->validate([
                'name' => $request->body('name'), 'email' => $request->body('email'),
                'password' => $request->body('password'), 'password_confirmation' => $request->body('password_confirmation'),
            ], ['name' => 'required|string|max:100', 'email' => 'required|email|max:254', 'password' => 'required|string|max:72', 'password_confirmation' => 'required|string|max:72']);
            if (!hash_equals($data['password'], $data['password_confirmation'])) {
                throw new ValidationException(['password_confirmation' => ['Passwords do not match.']]);
            }
            $user = $this->auth->register($data['name'], $data['email'], $data['password'], $request->ip());
            return $request->expectsJson() ? $this->json(['user' => $user, 'csrf_token' => $this->session->token()], 201) : Response::redirect('/notes');
        } catch (ValidationException $error) { return $this->fail($request, 'register', $error->errors, 422); }
        catch (RateLimitException $error) { return $this->throttled($request, 'register', $error); }
    }

    public function login(Request $request): Response
    {
        if ($this->auth->user() !== null) { return Response::redirect('/notes'); }
        // Malformed submissions share the same failure message as invalid credentials.
        $email = $request->body('email');
        $password = $request->body('password');
        try {
            $ok = $this->auth->attempt(is_string($email) ? $email : '', is_string($password) ? $password : '', $request->ip());
            if (!$ok) { return $this->fail($request, 'login', ['credentials' => ['Email or password is incorrect.']], 401); }
            return $request->expectsJson() ? $this->json(['user' => $this->auth->requireUser(), 'csrf_token' => $this->session->token()]) : Response::redirect('/notes');
        } catch (RateLimitException $error) { return $this->throttled($request, 'login', $error); }
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();
        return $request->expectsJson() ? $this->json(['message' => 'Signed out', 'csrf_token' => $this->session->token()]) : Response::redirect('/login');
    }

    public function csrf(): Response
    {
        $response = $this->json(['csrf_token' => $this->session->token()]);
        return new Response($response->body, 200, [...$response->headers, 'Cache-Control' => 'no-store']);
    }

    public function account(Request $request): Response
    {
        $user = $this->auth->requireUser();
        return $request->expectsJson() ? $this->json(['user' => $user, 'csrf_token' => $this->session->token()])
            : Response::html($this->view->render('auth.account', ['title' => 'Your account', 'user' => $user, 'token' => $this->session->token()], 'layouts.app'));
    }
}
