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

    /**
     * "Recordarme" del login: la sesión ya se creó como cookie de sesión normal
     * (lifetime 0, se borra al cerrar el navegador — ver start()); esto reemite la
     * MISMA cookie con una fecha de expiración fija, sin tocar los datos de sesión
     * ni regenerar el id. Debe llamarse antes de cualquier salida al navegador.
     */
    public static function extender(int $dias): void
    {
        // session_name()/session_id() devuelven string|false por firma, aunque nunca
        // pueden ser false aquí (se llama justo después de Session::start()) -- el
        // fallback es solo para satisfacer el tipo, no un caso real esperado.
        setcookie(session_name() ?: 'sigebi_session', session_id() ?: '', [
            'expires' => time() + $dias * 86400,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure' => (($_SERVER['HTTPS'] ?? '') === 'on'),
        ]);
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

    /**
     * Bloqueo de intentos para acciones sin un usuario identificado de antemano
     * (ej. "¿olvidé mi correo?", que se prueba con un número de documento que puede
     * no existir). A diferencia del bloqueo de login -que vive en la tabla `usuarios`
     * porque ahí sí hay una cuenta concreta a la que asociarlo-, este se guarda en la
     * sesión: la mayoría de intentos fallidos aquí no corresponden a ninguna cuenta
     * real, así que no hay una fila de BD natural donde contarlos.
     */
    public static function registrarIntentoFallido(string $clave, int $maxIntentos, int $minutosBloqueo): void
    {
        $intentos = ((int) ($_SESSION['_intentos'][$clave]['n'] ?? 0)) + 1;
        $_SESSION['_intentos'][$clave]['n'] = $intentos;

        if ($intentos >= $maxIntentos) {
            $_SESSION['_intentos'][$clave]['bloqueado_hasta'] = time() + $minutosBloqueo * 60;
        }
    }

    public static function bloqueadoPorIntentos(string $clave): bool
    {
        $hasta = $_SESSION['_intentos'][$clave]['bloqueado_hasta'] ?? null;

        return $hasta !== null && $hasta > time();
    }

    public static function resetearIntentos(string $clave): void
    {
        unset($_SESSION['_intentos'][$clave]);
    }
}
