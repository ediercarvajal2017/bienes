<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
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
            // Tras un intento fallido, el correo vuelve escrito (solo hay que corregir la clave).
            'email' => (string) (Session::pullOld()['email'] ?? ''),
        ]);
    }

    public function login(): void
    {
        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Aviso::enviar(Aviso::error('No se pudo ingresar', 'La página de ingreso estuvo abierta mucho tiempo y venció por seguridad.',
                'Escribe de nuevo tu correo y contraseña.'));
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
            header('Location: ' . Url::to('/dashboard'));
            exit;
        }

        Session::flashOld(['email' => $email]);
        Aviso::enviar(Auth::motivoFallo() === 'bloqueado'
            ? Aviso::error('No se pudo ingresar', 'Demasiados intentos fallidos con esta cuenta.',
                'Espera ' . Auth::VENTANA_MINUTOS . ' minutos y vuelve a intentarlo. Si no recuerdas la contraseña, usa «¿Olvidaste tu contraseña?».',
                Aviso::accion('¿Olvidaste tu contraseña?', '/olvide-contrasena'))
            // No se dice cuál de los dos falló: no se revela qué correos tienen cuenta.
            : Aviso::error('No se pudo ingresar', 'Correo o contraseña incorrectos.',
                'Revisa que el correo esté bien escrito y que la contraseña respete mayúsculas. Si no la recuerdas, usa «¿Olvidaste tu contraseña?».',
                Aviso::accion('¿Olvidaste tu contraseña?', '/olvide-contrasena')));
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
