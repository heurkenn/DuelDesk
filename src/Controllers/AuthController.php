<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\UserRepository;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\Support\Flash;
use DuelDesk\View;

final class AuthController
{
    /** @param array<string, string> $params */
    public function register(array $params = []): void
    {
        if (Auth::check()) {
            Response::redirect(Auth::isAdmin() ? '/admin' : '/');
        }

        $redirect = (string)($_GET['redirect'] ?? '');
        if ($redirect !== '' && !str_starts_with($redirect, '/')) {
            $redirect = '';
        }

        View::render('auth/register', [
            'title' => 'Inscription | DuelDesk',
            'old' => [
                'username' => '',
            ],
            'errors' => [],
            'csrfToken' => Csrf::token(),
            'redirect' => $redirect,
        ]);
    }

    /** @param array<string, string> $params */
    public function registerPost(array $params = []): void
    {
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password_confirm'] ?? '');
        $redirect = (string)($_POST['redirect'] ?? '');

        $old = [
            'username' => $username,
        ];

        $errors = [];

        if (!$this->isValidUsername($username)) {
            $errors['username'] = 'Username invalide (3-32, lettres/chiffres/._-).';
        }

        if ($password === '' || $this->strlenSafe($password) < 8) {
            $errors['password'] = 'Mot de passe requis (min 8).';
        } elseif ($password !== $password2) {
            $errors['password_confirm'] = 'Les mots de passe ne correspondent pas.';
        }

        $repo = new UserRepository();
        if ($username !== '' && $repo->findByUsername($username) !== null) {
            $errors['username'] = 'Ce username est deja utilise.';
        }

        if ($errors !== []) {
            View::render('auth/register', [
                'title' => 'Inscription | DuelDesk',
                'old' => $old,
                'errors' => $errors,
                'csrfToken' => Csrf::token(),
                'redirect' => ($redirect !== '' && str_starts_with($redirect, '/')) ? $redirect : '',
            ]);
            return;
        }

        // Bootstrap: if there is no admin yet, the first registered user becomes admin.
        $role = $repo->countAdmins() === 0 ? 'admin' : 'user';

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            Response::badRequest('Password hash failed');
        }

        $userId = $repo->create($username, $hash, $role);
        Auth::login($userId);

        Flash::set('success', $role === 'admin' ? 'Compte admin cree.' : 'Compte cree.');

        if ($redirect !== '' && str_starts_with($redirect, '/')) {
            Response::redirect($redirect);
        }

        Response::redirect($role === 'admin' ? '/admin' : '/');
    }

    /** @param array<string, string> $params */
    public function login(array $params = []): void
    {
        if (Auth::check()) {
            Response::redirect(Auth::isAdmin() ? '/admin' : '/');
        }

        $redirect = (string)($_GET['redirect'] ?? '');
        if ($redirect !== '' && !str_starts_with($redirect, '/')) {
            $redirect = '';
        }

        View::render('auth/login', [
            'title' => 'Connexion | DuelDesk',
            'old' => [
                'username' => '',
            ],
            'errors' => [],
            'csrfToken' => Csrf::token(),
            'redirect' => $redirect,
        ]);
    }

    /** @param array<string, string> $params */
    public function loginPost(array $params = []): void
    {
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $redirect = (string)($_POST['redirect'] ?? '');

        $old = ['username' => $username];
        $errors = [];

        if ($username === '') {
            $errors['username'] = 'Username requis.';
        }
        if ($password === '') {
            $errors['password'] = 'Mot de passe requis.';
        }

        $repo = new UserRepository();
        $user = $username !== '' ? $repo->findByUsername($username) : null;

        if ($errors === []) {
            $hash = is_array($user) ? (string)($user['password_hash'] ?? '') : '';
            if (!is_array($user) || $hash === '' || !password_verify($password, $hash)) {
                $errors['username'] = 'Identifiants invalides.';
            }
        }

        if ($errors !== []) {
            View::render('auth/login', [
                'title' => 'Connexion | DuelDesk',
                'old' => $old,
                'errors' => $errors,
                'csrfToken' => Csrf::token(),
                'redirect' => ($redirect !== '' && str_starts_with($redirect, '/')) ? $redirect : '',
            ]);
            return;
        }

        Auth::login((int)$user['id']);
        Flash::set('success', 'Connecte.');

        $dest = '/';
        if ($redirect !== '' && str_starts_with($redirect, '/')) {
            $dest = $redirect;
        } elseif (($user['role'] ?? '') === 'admin') {
            $dest = '/admin';
        }

        Response::redirect($dest);
    }

    /** @param array<string, string> $params */
    public function logout(array $params = []): void
    {
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }

        Auth::logout();
        Flash::set('success', 'Deconnecte.');
        Response::redirect('/');
    }

    private function strlenSafe(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int)mb_strlen($value);
        }

        return strlen($value);
    }

    private function isValidUsername(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        $len = $this->strlenSafe($value);
        if ($len < 3 || $len > 32) {
            return false;
        }

        // ASCII-only, no spaces. Adjust later if you want unicode usernames.
        return preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/', $value) === 1;
    }
}
