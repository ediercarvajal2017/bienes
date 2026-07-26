<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * LIMIT/OFFSET se interpolan directamente (no como parámetros ligados): con
 * PDO::ATTR_EMULATE_PREPARES=false, MySQL rechaza LIMIT/OFFSET ligados como
 * string. Son enteros validados aquí mismo, nunca texto del usuario, así que
 * interpolarlos es seguro. Mismo criterio ya usado en Bien::limitSql().
 */
final class Paginador
{
    private const POR_PAGINA_MAXIMA = 200;

    public static function limitSql(int $pagina, int $porPagina): string
    {
        if ($porPagina <= 0) {
            return '';
        }

        $porPagina = self::normalizarPorPagina($porPagina);
        $offset = (max(1, $pagina) - 1) * $porPagina;

        return " LIMIT {$porPagina} OFFSET {$offset}";
    }

    /**
     * $porPagina <= 0 significa "ver todos": una sola página con todos los resultados.
     */
    public static function totalPaginas(int $total, int $porPagina): int
    {
        if ($porPagina <= 0) {
            return 1;
        }

        return (int) max(1, ceil($total / self::normalizarPorPagina($porPagina)));
    }

    private static function normalizarPorPagina(int $porPagina): int
    {
        return max(1, min(self::POR_PAGINA_MAXIMA, $porPagina));
    }
}
