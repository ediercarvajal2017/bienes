<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\FechaMovimiento;
use App\Services\LoteReintegroService;
use PHPUnit\Framework\TestCase;

/** Reglas puras de los lotes de reintegro (estado, antigüedad, selección). */
final class LotesReintegroTest extends TestCase
{
    public function testAntiguedadEnPalabras(): void
    {
        self::assertSame('hoy', FechaMovimiento::antiguedad('2026-11-11', '2026-11-11'));
        self::assertSame('hoy', FechaMovimiento::antiguedad('2026-11-12', '2026-11-11'), 'una fecha futura no da negativos');
        self::assertSame('hace 1 día', FechaMovimiento::antiguedad('2026-11-10', '2026-11-11'));
        self::assertSame('hace 20 días', FechaMovimiento::antiguedad('2026-10-22', '2026-11-11'));
        self::assertSame('hace 1 mes', FechaMovimiento::antiguedad('2026-10-11', '2026-11-11'));
        self::assertSame('hace 3 meses', FechaMovimiento::antiguedad('2026-07-28', '2026-11-11'));
        self::assertSame('hace 1 año', FechaMovimiento::antiguedad('2025-11-11', '2026-11-11'));
        self::assertSame('hace 2 años y 1 mes', FechaMovimiento::antiguedad('2024-10-01', '2026-11-11'));
        self::assertSame('hace 1 año y 8 meses', FechaMovimiento::antiguedad('2025-03-03 10:20:00', '2026-11-11'), 'acepta fecha y hora');
        self::assertSame('hoy', FechaMovimiento::antiguedad('no es fecha', '2026-11-11'));
    }

    public function testFechaValidaParaFiltros(): void
    {
        self::assertTrue(FechaMovimiento::esValida('2024-02-29'));
        self::assertTrue(FechaMovimiento::esValida('2030-01-01'), 'el filtro acepta fechas futuras');
        self::assertFalse(FechaMovimiento::esValida('2023-02-29'));
        self::assertFalse(FechaMovimiento::esValida('11/11/2026'));
        self::assertFalse(FechaMovimiento::esValida(''));
        self::assertFalse(FechaMovimiento::esValida("2026-11-11' OR 1=1"));
    }

    public function testIdsUnicosDescartaBasura(): void
    {
        self::assertSame([3, 5, 8], LoteReintegroService::idsUnicos(['3', 5, '5', '8', '0', '-2', 'x', '']));
        self::assertSame([], LoteReintegroService::idsUnicos([]));
    }

    public function testAgrupaPorCategoriaConSinCategoriaAlFinal(): void
    {
        $grupos = LoteReintegroService::agruparPorCategoria([
            ['id' => 10, 'categoria_id' => 7, 'categoria_nombre' => 'Tecnología'],
            ['id' => 11, 'categoria_id' => null, 'categoria_nombre' => null],
            ['id' => 12, 'categoria_id' => 5, 'categoria_nombre' => 'Muebles'],
            ['id' => 9, 'categoria_id' => '5', 'categoria_nombre' => 'Muebles'],
        ]);

        self::assertSame(['Muebles', 'Tecnología', 'Sin categoría'], array_column($grupos, 'categoria'));
        self::assertSame([5, 7, null], array_column($grupos, 'categoria_id'));
        self::assertSame([9, 12], $grupos[0]['movimientos'], 'ordenados y sin perder ninguno');
        self::assertSame([10], $grupos[1]['movimientos']);
        self::assertSame([11], $grupos[2]['movimientos']);
        self::assertSame([], $grupos[0]['origenes'], 'reintegros sueltos: sin lote de origen');
    }

    public function testAgrupaRecordandoLosLotesDeOrigen(): void
    {
        // Lote #3 mixto, #5 de Muebles y #8 de Tecnología.
        $grupos = LoteReintegroService::agruparPorCategoria([
            ['id' => 1, 'categoria_id' => 5, 'categoria_nombre' => 'Muebles', 'lote_reintegro_id' => 3],
            ['id' => 2, 'categoria_id' => 7, 'categoria_nombre' => 'Tecnología', 'lote_reintegro_id' => 3],
            ['id' => 3, 'categoria_id' => 5, 'categoria_nombre' => 'Muebles', 'lote_reintegro_id' => 5],
            ['id' => 4, 'categoria_id' => 7, 'categoria_nombre' => 'Tecnología', 'lote_reintegro_id' => 8],
            ['id' => 5, 'categoria_id' => 5, 'categoria_nombre' => 'Muebles', 'lote_reintegro_id' => '5'],
        ]);

        self::assertCount(2, $grupos);
        self::assertSame(['categoria_id' => 5, 'categoria' => 'Muebles', 'movimientos' => [1, 3, 5], 'origenes' => [3, 5]], $grupos[0]);
        self::assertSame(['categoria_id' => 7, 'categoria' => 'Tecnología', 'movimientos' => [2, 4], 'origenes' => [3, 8]], $grupos[1]);
        self::assertSame([], LoteReintegroService::agruparPorCategoria([]));
    }

    public function testResumenDeLotesCreados(): void
    {
        self::assertSame('#14 Muebles (3 bienes)', LoteReintegroService::resumenLotes([['id' => 14, 'categoria' => 'Muebles', 'bienes' => 3]]));
        self::assertSame('#14 Muebles (3 bienes) y #15 Tecnología (1 bien)', LoteReintegroService::resumenLotes([
            ['id' => 14, 'categoria' => 'Muebles', 'bienes' => 3],
            ['id' => 15, 'categoria' => 'Tecnología', 'bienes' => 1],
        ]));
        self::assertSame('#1 A (1 bien), #2 B (2 bienes) y #3 Sin categoría (4 bienes)', LoteReintegroService::resumenLotes([
            ['id' => 1, 'categoria' => 'A', 'bienes' => 1],
            ['id' => 2, 'categoria' => 'B', 'bienes' => 2],
            ['id' => 3, 'categoria' => 'Sin categoría', 'bienes' => 4],
        ]));
    }

    public function testDetectaCuandoUnificarNoCambiaNada(): void
    {
        $grupo = static fn (array $origenes): array => ['origenes' => $origenes];

        // Un lote de una sola categoría: nada que separar.
        self::assertTrue(LoteReintegroService::esUnificacionSinCambios([$grupo([5])]));
        // #5 de Muebles y #8 de Tecnología: cada uno seguiría igual.
        self::assertTrue(LoteReintegroService::esUnificacionSinCambios([$grupo([5]), $grupo([8])]));
        // Dos lotes de Muebles: se juntan.
        self::assertFalse(LoteReintegroService::esUnificacionSinCambios([$grupo([5, 6])]));
        // Un lote mixto: se separa en dos.
        self::assertFalse(LoteReintegroService::esUnificacionSinCambios([$grupo([3]), $grupo([3])]));
        // Mixto + otro: cambia.
        self::assertFalse(LoteReintegroService::esUnificacionSinCambios([$grupo([3, 5]), $grupo([3, 8])]));
    }

    public function testDescripcionDeLaUnificacion(): void
    {
        self::assertSame('Separación por categoría del lote #3', LoteReintegroService::descripcionUnificacion([3]));
        self::assertSame('Unificación de los lotes #3 y #5', LoteReintegroService::descripcionUnificacion([3, 5]));
        self::assertSame('Unificación de los lotes #3, #5 y #8', LoteReintegroService::descripcionUnificacion([3, 5, 8]));
        self::assertSame('Unificación de 9 lotes', LoteReintegroService::descripcionUnificacion(range(1, 9)));
    }

    public function testEtiquetaDeEstado(): void
    {
        self::assertSame('Pendiente', LoteReintegroService::etiquetaEstado('pendiente'));
        self::assertSame('Reintegrado', LoteReintegroService::etiquetaEstado('reintegrado'));
        self::assertSame('Unificado', LoteReintegroService::etiquetaEstado('unificado'));
    }
}
