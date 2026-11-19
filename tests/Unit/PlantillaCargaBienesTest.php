<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PlantillaCargaBienes;
use PHPUnit\Framework\TestCase;

/** Plantilla de la carga masiva de bienes: lo que Excel exige para no dar el archivo por dañado. */
final class PlantillaCargaBienesTest extends TestCase
{
    public function testLasAyudasCabenEnLosLimitesDeExcel(): void
    {
        foreach (PlantillaCargaBienes::COLUMNAS as $columna => $c) {
            self::assertLessThanOrEqual(32, mb_strlen($c['tituloAyuda']), "título de la ayuda de {$columna}");
            self::assertLessThanOrEqual(255, mb_strlen($c['ayuda']), "texto de la ayuda de {$columna}");
            // Con lista vacía se recorta a 150 y se agrega el aviso: también debe caber.
            self::assertLessThanOrEqual(255, mb_strlen(mb_substr($c['ayuda'], 0, 150) . ' (Esta institución todavía no tiene opciones para elegir.)'));
        }
    }

    public function testLasColumnasSonLasQueLeeLaCarga(): void
    {
        self::assertSame(range('A', 'H'), array_keys(PlantillaCargaBienes::COLUMNAS));
        self::assertSame(['A', 'B'], array_keys(array_filter(PlantillaCargaBienes::COLUMNAS, static fn (array $c): bool => $c['obligatoria'])),
            'obligatorias: Código y Descripción, igual que CargaMasivaService');
        self::assertSame(' — ', PlantillaCargaBienes::SEPARADOR_UBICACION);
    }
}
