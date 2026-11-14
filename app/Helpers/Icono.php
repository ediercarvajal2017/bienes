<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Íconos de MIA a partir del diccionario único (app/Views/partials/iconos.php). Las vistas
 * usan la función icono() (app/Helpers/funciones_vista.php), que llama a Icono::html().
 *
 * Los íconos son decorativos (aria-hidden): el texto del botón, o su aria-label si solo
 * lleva ícono, es lo que leen los lectores de pantalla.
 */
final class Icono
{
    /** @var array<string, array{icono: string, activo?: string, uso: string}>|null */
    private static ?array $mapa = null;

    /** @return array<string, array{icono: string, activo?: string, uso: string}> */
    public static function mapa(): array
    {
        return self::$mapa ??= require dirname(__DIR__) . '/Views/partials/iconos.php';
    }

    /** Nombre de Bootstrap Icons (sin "bi-"); con $activo, la versión rellena si existe. */
    public static function nombre(string $clave, bool $activo = false): string
    {
        $entrada = self::mapa()[$clave] ?? throw new \InvalidArgumentException("Ícono sin definir en el diccionario: \"{$clave}\".");

        return $activo ? ($entrada['activo'] ?? $entrada['icono']) : $entrada['icono'];
    }

    /** <i class="bi bi-…" aria-hidden="true"></i>, con clases extra opcionales (ej. "me-1"). */
    public static function html(string $clave, string $clases = '', bool $activo = false): string
    {
        $clase = 'bi bi-' . self::nombre($clave, $activo) . ($clases !== '' ? ' ' . $clases : '');

        return '<i class="' . htmlspecialchars($clase, ENT_QUOTES) . '" aria-hidden="true"></i>';
    }
}
