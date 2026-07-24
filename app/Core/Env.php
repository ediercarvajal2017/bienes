<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Carga variables desde un archivo .env (si existe) hacia getenv(), para poder
 * usar credenciales distintas en cada entorno (WAMP local vs. hosting de producción)
 * sin tocar los archivos de config/ versionados.
 */
final class Env
{
    private static bool $cargado = false;

    public static function cargar(): void
    {
        if (self::$cargado) {
            return;
        }
        self::$cargado = true;

        $archivo = dirname(__DIR__, 2) . '/.env';
        if (!is_file($archivo)) {
            return;
        }

        foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
                continue;
            }

            [$clave, $valor] = explode('=', $linea, 2);
            $clave = trim($clave);
            $valor = trim($valor, " \t\n\r\0\x0B\"'");

            if ($clave !== '' && getenv($clave) === false) {
                putenv("{$clave}={$valor}");
            }
        }
    }

    public static function get(string $clave, mixed $default = null): mixed
    {
        $valor = getenv($clave);

        return $valor === false ? $default : $valor;
    }
}
