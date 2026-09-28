<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Fecha de un movimiento del bien (asignar, trasladar, reintegrar, reactivar): debe ser
 * una fecha real en formato AAAA-MM-DD, no posterior a hoy y no anterior al año 2000.
 * Antes solo se comprobaba con strtotime(), que aceptaba fechas futuras (2030-01-01) o
 * textos como "mañana", y el historial podía quedar con fechas imposibles.
 */
final class FechaMovimiento
{
    public const MINIMA = '2000-01-01';

    /** Mensaje de error para el usuario, o null si la fecha es válida. */
    public static function error(string $fecha): ?string
    {
        $fecha = trim($fecha);
        $valor = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if ($fecha === '' || $valor === false || $valor->format('Y-m-d') !== $fecha) {
            return 'Indica una fecha válida.';
        }
        if ($fecha > self::hoy()) {
            return 'La fecha no puede ser posterior a hoy.';
        }
        if ($fecha < self::MINIMA) {
            return 'La fecha no puede ser anterior al año 2000.';
        }

        return null;
    }

    public static function hoy(): string
    {
        return date('Y-m-d');
    }
}
