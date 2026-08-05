<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;

final class AuthController
{
    public function redirectRoot(): void
    {
        header('Location: ' . Url::to(Auth::check() ? '/dashboard' : '/login'));
        exit;
    }

    public function showLogin(): void
    {
        if (Auth::check()) {
            header('Location: ' . Url::to('/dashboard'));
            exit;
        }

        View::render('auth/login', ['error' => Session::pullFlash('error')]);
    }

    public function login(): void
    {
        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to('/login'));
            exit;
        }

        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');

        if ($email !== '' && $password !== '' && Auth::attempt($email, $password)) {
            if ($request->input('recordar')) {
                Session::extender(30);
            }

            header('Location: ' . Url::to('/dashboard'));
            exit;
        }

        Session::flash('error', 'Credenciales inválidas o cuenta bloqueada temporalmente.');
        header('Location: ' . Url::to('/login'));
        exit;
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: ' . Url::to('/login'));
        exit;
    }
}
