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

    public function testEtiquetaDeEstado(): void
    {
        self::assertSame('Pendiente', LoteReintegroService::etiquetaEstado('pendiente'));
        self::assertSame('Reintegrado', LoteReintegroService::etiquetaEstado('reintegrado'));
        self::assertSame('Unificado', LoteReintegroService::etiquetaEstado('unificado'));
    }
}
