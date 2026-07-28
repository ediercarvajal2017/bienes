<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure' => (($_SERVER['HTTPS'] ?? '') === 'on'),
        ]);

        session_name('sigebi_session');
        session_start();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    public static function pullFlash(string $key): ?string
    {
        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return $message;
    }

    /**
     * Guarda los datos de un formulario que falló su validación, para que la página a la
     * que se redirige pueda volver a mostrarlos en vez de un formulario en blanco. Como
     * pullFlash(), dura solo hasta la siguiente vez que se lea (pullOld()).
     */
    public static function flashOld(array $datos): void
    {
        $_SESSION['_old'] = $datos;
    }

    public static function pullOld(): array
    {
        $datos = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);

        return $datos;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
