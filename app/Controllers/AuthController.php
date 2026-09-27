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

        View::render('auth/login', [
            'error' => Session::pullFlash('error'),
            // Tras un intento fallido, el correo vuelve escrito (solo hay que corregir la clave).
            'email' => (string) (Session::pullOld()['email'] ?? ''),
        ]);
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

        $resultado = $email !== '' && $password !== ''
            ? Auth::attempt($email, $password, (bool) $request->input('recordar'))
            : Auth::INGRESO_FALLIDO;

        if ($resultado === Auth::INGRESO_REQUIERE_2FA) {
            header('Location: ' . Url::to('/2fa/verificar'));
            exit;
        }

        if ($resultado === Auth::INGRESO_OK) {
            header('Location: ' . Url::to(Auth::debeConfigurarDosFactores() ? '/2fa/configurar' : '/dashboard'));
            exit;
        }

        Session::flashOld(['email' => $email]);
        Session::flash('error', Auth::motivoFallo() === 'bloqueado'
            ? 'Demasiados intentos fallidos. Espera ' . Auth::VENTANA_MINUTOS . ' minutos antes de volver a intentarlo.'
            : 'Correo o contraseña incorrectos.');
        header('Location: ' . Url::to('/login'));
        exit;
    }

    public function logout(): void
    {
        // Con token CSRF: otra página no puede cerrar la sesión del usuario a escondidas.
        Csrf::verificarORedirigir(new Request(), '/dashboard');

        Auth::logout();
        // Borra la caché del navegador para este sitio (incluida la del service worker),
        // para que en un equipo compartido no quede ninguna pantalla del usuario guardada.
        header('Clear-Site-Data: "cache"');
        header('Location: ' . Url::to('/login'));
        exit;
    }
}
