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

    /**
     * La fecha es un filtro válido (AAAA-MM-DD real)? A diferencia de error(), acepta
     * cualquier fecha: sirve para los filtros "desde/hasta" de los listados.
     */
    public static function esValida(string $fecha): bool
    {
        $valor = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        return $valor !== false && $valor->format('Y-m-d') === $fecha;
    }

    /**
     * Cuánto tiempo pasó desde $fecha, en palabras: "hoy", "hace 5 días", "hace 3 meses",
     * "hace 1 año y 2 meses". Así un lote pendiente de hace dos años salta a la vista.
     */
    public static function antiguedad(string $fecha, ?string $hoy = null): string
    {
        $desde = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($fecha, 0, 10));
        $hasta = \DateTimeImmutable::createFromFormat('!Y-m-d', $hoy ?? self::hoy());
        if ($desde === false || $hasta === false || $desde >= $hasta) {
            return 'hoy';
        }

        $diferencia = $desde->diff($hasta);
        $plural = static fn (int $n, string $uno, string $varios): string => $n . ' ' . ($n === 1 ? $uno : $varios);

        if ($diferencia->y > 0) {
            return 'hace ' . $plural($diferencia->y, 'año', 'años')
                . ($diferencia->m > 0 ? ' y ' . $plural($diferencia->m, 'mes', 'meses') : '');
        }
        if ($diferencia->m > 0) {
            return 'hace ' . $plural($diferencia->m, 'mes', 'meses');
        }

        return 'hace ' . $plural((int) $diferencia->days, 'día', 'días');
    }
}
