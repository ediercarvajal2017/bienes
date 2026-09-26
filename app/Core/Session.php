<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    /** Duración de "Recordarme" (y vida máxima de un archivo de sesión en el servidor). */
    public const DIAS_RECORDARME = 30;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Las sesiones se guardan en la carpeta propia de la app, con una vida máxima
        // definida aquí. Con la configuración por defecto del hosting (carpeta compartida,
        // gc_maxlifetime de 24 minutos) el servidor borraba la sesión mucho antes: ni
        // "Recordarme" ni el límite de inactividad de config/app.php se cumplían. El
        // cierre por inactividad lo aplica Auth::check().
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $dirSesiones = $config['storage_path'] . '/sesiones';
        if ((is_dir($dirSesiones) || @mkdir($dirSesiones, 0700, true)) && is_writable($dirSesiones)) {
            session_save_path($dirSesiones);
        }
        ini_set('session.gc_maxlifetime', (string) (self::DIAS_RECORDARME * 86400));
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        // Rechaza ids de sesión que el servidor no creó (evita la fijación de sesión).
        ini_set('session.use_strict_mode', '1');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure' => self::esHttps(),
        ]);

        session_name('sigebi_session');
        session_start();
    }

    /**
     * ¿La petición llegó por HTTPS? Además de $_SERVER['HTTPS'], se consideran el puerto
     * 443 y la cabecera X-Forwarded-Proto: si el hosting termina el HTTPS en un proxy
     * delante de PHP, $_SERVER['HTTPS'] puede venir vacío y la cookie de sesión quedaba sin
     * la marca "secure". (Confiar en esa cabecera aquí es inofensivo: en el peor caso la
     * cookie se marca secure en una conexión HTTP y simplemente no se envía.)
     */
    public static function esHttps(): bool
    {
        return strtolower((string) ($_SERVER['HTTPS'] ?? '')) === 'on'
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
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
            'secure' => self::esHttps(),
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

        // Expira también la cookie en el navegador (session_destroy() solo borra los datos
        // del servidor y dejaba la cookie viva, incluida la de "Recordarme" a 30 días).
        if (!headers_sent()) {
            setcookie(session_name() ?: 'sigebi_session', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Strict',
                'secure' => self::esHttps(),
            ]);
        }

        session_destroy();
    }
}
