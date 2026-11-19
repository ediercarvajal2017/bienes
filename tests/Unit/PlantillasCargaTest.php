<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PlantillaCargaBienes;
use App\Services\PlantillaCargaEspacios;
use App\Services\PlantillaCargaUsuarios;
use App\Services\PlantillaExcel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Plantillas de las cargas masivas: lo que Excel exige para no dar el archivo por dañado. */
final class PlantillasCargaTest extends TestCase
{
    /** @return array<string, array{0: array<string, array<string, mixed>>, 1: list<string>, 2: list<string>}> */
    public static function plantillas(): array
    {
        return [
            // columnas, obligatorias siempre, obligatorias solo para un registro nuevo
            'bienes' => [PlantillaCargaBienes::COLUMNAS, ['A', 'B'], []],
            'espacios' => [PlantillaCargaEspacios::COLUMNAS, ['A', 'B'], ['C']],
            'usuarios' => [PlantillaCargaUsuarios::COLUMNAS, ['A'], ['B', 'C', 'D', 'E']],
        ];
    }

    /** @param array<string, array<string, mixed>> $columnas */
    #[DataProvider('plantillas')]
    public function testLasAyudasCabenEnLosLimitesDeExcel(array $columnas): void
    {
        foreach ($columnas as $columna => $c) {
            self::assertLessThanOrEqual(32, mb_strlen($c['tituloAyuda']), "título de la ayuda de {$columna}");
            self::assertLessThanOrEqual(255, mb_strlen($c['ayuda']), "texto de la ayuda de {$columna}");
            // Con lista vacía se recorta a 150 y se agrega el aviso: también debe caber.
            self::assertLessThanOrEqual(255, mb_strlen(mb_substr($c['ayuda'], 0, 150) . ' (Esta institución todavía no tiene opciones para elegir.)'));
            if (isset($c['regla'])) {
                self::assertLessThanOrEqual(32, mb_strlen($c['regla'][4]), "título del error de {$columna}");
                self::assertLessThanOrEqual(255, mb_strlen($c['regla'][5]), "error de {$columna}");
            }
            self::assertLessThanOrEqual(255, mb_strlen($c['errorLista'] ?? ''), "error de la lista de {$columna}");
        }
    }

    /**
     * Las obligatorias van en rojo y las que solo pide un registro nuevo en amarillo: las
     * mismas reglas que aplican los servicios de carga.
     *
     * @param array<string, array<string, mixed>> $columnas
     * @param list<string> $obligatorias
     * @param list<string> $siEsNuevo
     */
    #[DataProvider('plantillas')]
    public function testLosColoresSiguenLasReglasDeLaCarga(array $columnas, array $obligatorias, array $siEsNuevo): void
    {
        $nivel = static fn (string $n): array => array_keys(array_filter($columnas, static fn (array $c): bool => $c['nivel'] === $n));
        self::assertSame($obligatorias, $nivel(PlantillaExcel::OBLIGATORIA));
        self::assertSame($siEsNuevo, $nivel(PlantillaExcel::SI_ES_NUEVO));
        self::assertSame(range('A', (string) array_key_last($columnas)), array_keys($columnas), 'columnas seguidas desde la A');
    }

    public function testDeUnaListaSeLeeElCodigo(): void
    {
        self::assertSame('A101', PlantillaExcel::valorDeLista('A101 — Aula 101'));
        self::assertSame('1234567890', PlantillaExcel::valorDeLista('1234567890 — Ana Pérez'));
        self::assertSame('A101', PlantillaExcel::valorDeLista('A101'), 'escrito a mano, sin la raya');
        self::assertSame(' — ', PlantillaCargaBienes::SEPARADOR_UBICACION);
    }

    public function testLaFilaDeTitulosVaDespuesDeLasInstrucciones(): void
    {
        self::assertSame(8, PlantillaExcel::filaDeTitulos(5));
    }
}
