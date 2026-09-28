<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Env;

/**
 * Política de tratamiento de datos personales y términos de uso (Ley 1581 de 2012 y
 * Decreto 1377 de 2013). El texto está en Views/legal/_texto.php.
 *
 * Cada usuario la acepta en su primer ingreso (PoliticaController); mientras no la acepte,
 * AuthMiddleware no le deja usar el sistema. Al cambiar el texto de fondo, se sube VERSION
 * y todos la vuelven a aceptar en su siguiente ingreso.
 */
final class PoliticaDatos
{
    public const VERSION = '2026-09-28';

    /** Rutas que se pueden usar con la política pendiente (además de la página pública). */
    public const RUTAS_PERMITIDAS = ['/politica/aceptar', '/logout'];

    /** ¿El usuario (fila de usuarios con politica_version) debe aceptar la versión vigente? */
    public static function pendiente(array $usuario): bool
    {
        return ($usuario['politica_version'] ?? null) !== self::VERSION;
    }

    /** Fecha de vigencia legible, p. ej. "28 de septiembre de 2026". */
    public static function fechaVigencia(): string
    {
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
            'septiembre', 'octubre', 'noviembre', 'diciembre'];
        [$anio, $mes, $dia] = array_map('intval', explode('-', self::VERSION));

        return $dia . ' de ' . $meses[$mes - 1] . ' de ' . $anio;
    }

    /** Quien presta el servicio MIA (encargado del tratamiento). PROVEEDOR_NOMBRE en el .env. */
    public static function proveedor(): string
    {
        return trim((string) Env::get('PROVEEDOR_NOMBRE', '')) ?: 'EdierTech';
    }

    /** Correo para consultas y reclamos sobre datos personales. SOPORTE_CORREO en el .env. */
    public static function correoContacto(): string
    {
        $correo = trim((string) Env::get('SOPORTE_CORREO', ''));

        return filter_var($correo, FILTER_VALIDATE_EMAIL) ? $correo : '';
    }
}
