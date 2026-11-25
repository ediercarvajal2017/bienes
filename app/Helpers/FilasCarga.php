<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Filas del resultado de una carga masiva (bienes, espacios o usuarios) por páginas y por
 * estado. Antes la pantalla mostraba todas las filas de una vez: una carga de 2 475 filas
 * pesaba 1,2 MB y en un celular tardaba varios segundos en verse.
 */
final class FilasCarga
{
    public const POR_PAGINA_DEFECTO = 50;
    public const OPCIONES_POR_PAGINA = [25, 50, 100, 0];

    /** Estado de la fila => texto del filtro, en el orden en que se muestran. */
    public const ESTADOS = [
        'invalido' => 'Inválidas',
        'nuevo' => 'Nuevas',
        'modificado' => 'Modificadas',
        'sin_cambios' => 'Sin cambios',
    ];

    /**
     * @param list<array<string, mixed>> $filas Todas las filas de la carga (resultado_diff_json).
     * @param array<mixed> $consulta Parámetros de la URL ($_GET): estado, pagina y porPagina.
     *
     * @return array{filas: list<array<string, mixed>>, estado: string, conteo: array<string, int>, total: int,
     *               pagina: int, porPagina: int, totalPaginas: int, opcionesPorPagina: list<int>}
     */
    public static function pagina(array $filas, array $consulta): array
    {
        $conteo = array_fill_keys(array_keys(self::ESTADOS), 0);
        foreach ($filas as $fila) {
            $tipo = (string) ($fila['tipo'] ?? '');
            if (isset($conteo[$tipo])) {
                $conteo[$tipo]++;
            }
        }

        $estado = is_string($consulta['estado'] ?? null) && isset(self::ESTADOS[$consulta['estado']]) ? $consulta['estado'] : '';
        if ($estado !== '') {
            $filas = array_values(array_filter($filas, static fn (array $f): bool => ($f['tipo'] ?? '') === $estado));
        }

        $porPagina = (int) (is_numeric($consulta['porPagina'] ?? null) ? $consulta['porPagina'] : self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }
        $total = count($filas);
        $totalPaginas = Paginador::totalPaginas($total, $porPagina);
        $pagina = min(max(1, (int) (is_numeric($consulta['pagina'] ?? null) ? $consulta['pagina'] : 1)), max(1, $totalPaginas));

        return [
            'filas' => $porPagina === 0 ? $filas : array_slice($filas, ($pagina - 1) * $porPagina, $porPagina),
            'estado' => $estado,
            'conteo' => $conteo,
            'total' => $total,
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'totalPaginas' => $totalPaginas,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
        ];
    }
}
