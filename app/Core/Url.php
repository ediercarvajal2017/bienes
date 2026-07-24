<?php

declare(strict_types=1);

namespace App\Core;

final class Url
{
    private static ?string $base = null;

    /**
     * Prefijo de la app según dónde vive realmente public/index.php (p. ej. "/gestionbienes"
     * bajo el alias por defecto de WAMP, o "" si un vhost apunta directo a /public).
     */
    public static function base(): string
    {
        if (self::$base === null) {
            $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));

            if (str_ends_with($scriptDir, '/public')) {
                $scriptDir = substr($scriptDir, 0, -strlen('/public'));
            }

            self::$base = $scriptDir === '/' ? '' : rtrim($scriptDir, '/');
        }

        return self::$base;
    }

    public static function to(string $path): string
    {
        return self::base() . '/' . ltrim($path, '/');
    }

    /**
     * Igual que to() pero añade ?v=<fecha de modificación> a los archivos estáticos
     * (CSS/JS), para que el navegador descargue la versión nueva tras cada cambio y no
     * se quede sirviendo una copia vieja desde su caché.
     */
    public static function asset(string $path): string
    {
        $url = self::to($path);
        $archivo = dirname(__DIR__, 2) . '/public/' . ltrim($path, '/');

        if (is_file($archivo)) {
            $url .= '?v=' . filemtime($archivo);
        }

        return $url;
    }

    /**
     * URL completa (esquema + host) para casos donde se necesita fuera del navegador,
     * como el contenido codificado dentro de un código QR.
     */
    public static function absoluta(string $path): string
    {
        $esquema = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $esquema . '://' . $host . self::to($path);
    }
}
