<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\VerificacionCartera;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verificar un lote en la cartera de la Alcaldía: qué bienes del lote siguen en ella, y cómo se
 * lee el Excel de la cartera (una fila por placa, con títulos en cualquier fila o columna).
 */
final class VerificacionCarteraTest extends TestCase
{
    /** @var list<string> */
    private array $archivos = [];

    protected function tearDown(): void
    {
        foreach ($this->archivos as $archivo) {
            @unlink($archivo);
        }
    }

    /** @return list<array{id: int, codigo_identificacion: string, lote: ?string, descripcion: string, fecha_reintegro: string}> */
    private static function sillas(int $cantidad, string $placa = '200033042', string $fecha = '2026-11-02'): array
    {
        $bienes = [];
        for ($i = 1; $i <= $cantidad; $i++) {
            $bienes[] = ['id' => $i, 'codigo_identificacion' => sprintf('%s-%03d', $placa, 170 + $i), 'lote' => $placa,
                'descripcion' => 'SILLA UNIVERSITARIA', 'fecha_reintegro' => $fecha];
        }

        return $bienes;
    }

    public function testUnaPlacaPropiaSigueSoloSiApareceEnLaCartera(): void
    {
        $bienes = [
            ['id' => 1, 'codigo_identificacion' => '200388980', 'lote' => null, 'descripcion' => 'COMPUTADOR', 'fecha_reintegro' => '2026-11-01'],
            ['id' => 2, 'codigo_identificacion' => '13019802', 'lote' => null, 'descripcion' => 'TABLERO', 'fecha_reintegro' => '2026-11-02'],
        ];

        $resultado = VerificacionCartera::calcular($bienes, [], ['200388980' => 1, '99999' => 3]);

        self::assertSame(2, $resultado['total_lote']);
        self::assertSame(1, $resultado['total_siguen']);
        self::assertCount(1, $resultado['filas']);
        self::assertSame('200388980', $resultado['filas'][0]['placa']);
        self::assertFalse($resultado['filas'][0]['con_unidades']);
        self::assertSame(1, $resultado['filas'][0]['siguen']);
    }

    /** @return array<string, array{0: int, 1: int}> la cartera dice → siguen */
    public static function carterasDeSillas(): array
    {
        // El lote reintegró 8 de 178 sillas: deben quedar 170.
        return [
            'descargadas: dice 170' => [170, 0],
            'descargó de más: dice 160' => [160, 0],
            'faltan 4: dice 174' => [174, 4],
            'no descargó ninguna: dice 178' => [178, 8],
            'nunca más que las del lote: dice 190' => [190, 8],
            'la placa ya no está' => [0, 0],
        ];
    }

    #[DataProvider('carterasDeSillas')]
    public function testUnidadesDeUnaPlaca(int $carteraDice, int $siguen): void
    {
        $unidades = ['200033042' => ['en_cartera' => 170, 'aun_sin_descargar' => 0, 'en_otros_lotes' => 0, 'otros_lotes' => []]];
        $cartera = $carteraDice > 0 ? ['200033042' => $carteraDice] : [];

        $resultado = VerificacionCartera::calcular(self::sillas(8), $unidades, $cartera);

        self::assertSame(8, $resultado['total_lote']);
        self::assertSame($siguen, $resultado['total_siguen']);
        if ($siguen > 0) {
            self::assertSame(8, $resultado['filas'][0]['cantidad_lote']);
            self::assertSame(170, $resultado['filas'][0]['deberian_quedar']);
            self::assertSame($carteraDice, $resultado['filas'][0]['cartera_dice']);
        } else {
            self::assertSame([], $resultado['filas']);
        }
    }

    public function testLasUnidadesQueLaAlcaldiaAunNoTeniaNoSeLeCuentanAlLote(): void
    {
        // Otras 5 sillas de la placa están en un lote pendiente (o entregado después de la cartera):
        // es normal que sigan, así que deben quedar 175. La cartera dice 175 → el lote está descargado.
        $unidades = ['200033042' => ['en_cartera' => 170, 'aun_sin_descargar' => 5, 'en_otros_lotes' => 0, 'otros_lotes' => []]];

        self::assertSame(0, VerificacionCartera::calcular(self::sillas(8), $unidades, ['200033042' => 175])['total_siguen']);
        self::assertSame(3, VerificacionCartera::calcular(self::sillas(8), $unidades, ['200033042' => 178])['total_siguen']);
    }

    public function testAvisaCuandoOtrosLotesEntregadosTienenLaMismaPlaca(): void
    {
        $unidades = ['200033042' => ['en_cartera' => 165, 'aun_sin_descargar' => 0, 'en_otros_lotes' => 5, 'otros_lotes' => [12]]];

        $fila = VerificacionCartera::calcular(self::sillas(8), $unidades, ['200033042' => 170])['filas'][0];

        self::assertSame(5, $fila['siguen']);
        self::assertSame(5, $fila['en_otros_lotes']);
        self::assertSame([12], $fila['otros_lotes']);
    }

    public function testLasFechasDeReintegroDelRenglon(): void
    {
        $bienes = [...self::sillas(1, '200033042', '2026-10-20'), ...self::sillas(1, '200033042', '2026-11-02')];
        $unidades = ['200033042' => ['en_cartera' => 0, 'aun_sin_descargar' => 0, 'en_otros_lotes' => 0, 'otros_lotes' => []]];

        $fila = VerificacionCartera::calcular($bienes, $unidades, ['200033042' => 2])['filas'][0];

        self::assertSame('2026-10-20', $fila['fecha_desde']);
        self::assertSame('2026-11-02', $fila['fecha_hasta']);
    }

    /** @return array<string, array{0: mixed, 1: string}> */
    public static function placas(): array
    {
        return [
            'número de Excel' => [200033042.0, '200033042'],
            'entero' => [13019802, '13019802'],
            'texto con espacios' => [' 2000 33042 ', '200033042'],
            'ceros a la izquierda' => ['013019802', '13019802'],
            'texto con .0' => ['200033042.0', '200033042'],
            'código interno con letras' => ['sc-0001', 'SC-0001'],
            'vacía' => [null, ''],
        ];
    }

    #[DataProvider('placas')]
    public function testNormalizaLaPlaca(mixed $valor, string $esperada): void
    {
        self::assertSame($esperada, VerificacionCartera::normalizarPlaca($valor));
    }

    /** @param list<list<mixed>> $filas */
    private function cartera(array $filas, string $desde = 'A1'): string
    {
        $libro = new Spreadsheet();
        $libro->getActiveSheet()->fromArray($filas, null, $desde, true);
        $ruta = tempnam(sys_get_temp_dir(), 'cartera') . '.xlsx';
        (new Xlsx($libro))->save($ruta);
        $this->archivos[] = $ruta;

        return $ruta;
    }

    public function testLeeLaCarteraConTitulosEnOtraFilaYColumna(): void
    {
        $ruta = $this->cartera([
            ['ALCALDÍA MUNICIPAL — CARTERA DE BIENES DEL RECTOR POR CÓDIGO DE INVENTARIO Y PLACA'],
            ['Institución: IE de prueba'],
            [],
            ['Ítem', 'Descripción del elemento', 'N.º de Placa', 'Cantidad', 'Valor'],
            [1, '178 SILLAS UNIVERSITARIAS', 200033042, 170, 1500000],
            [2, 'COMPUTADOR', '200388980', 1, 2000000],
            [3, 'TABLERO', '200388980', '', 0],
            [4, 'sin placa', '', 3, 0],
        ], 'B2');

        $lectura = VerificacionCartera::leerCartera($ruta);

        self::assertTrue($lectura['encontrada']);
        self::assertSame('D', $lectura['columnaPlaca']);
        self::assertSame('N.º de Placa', $lectura['tituloPlaca']);
        self::assertSame('E', $lectura['columnaCantidad']);
        // Una placa en dos filas se suma; sin cantidad, cuenta 1.
        self::assertSame(['200033042' => 170, '200388980' => 2], $lectura['placas']);
    }

    public function testSinColumnaDeCantidadLaTomaDeLaDescripcion(): void
    {
        $ruta = $this->cartera([
            ['CODIGO', 'DESCRIPCION', 'VALOR'],
            ['200033042', '178 SILLAS UNIVERSITARIAS', 1500000],
            ['200388980', 'COMPUTADOR PORTATIL', 2000000],
        ]);

        $lectura = VerificacionCartera::leerCartera($ruta);

        self::assertSame('A', $lectura['columnaPlaca']);
        self::assertNull($lectura['columnaCantidad']);
        self::assertSame(['200033042' => 178, '200388980' => 1], $lectura['placas']);
    }

    public function testSiNoReconoceLosTitulosSeEligeLaColumnaAMano(): void
    {
        $ruta = $this->cartera([
            ['Elemento', 'Numero', 'Unidades'],
            ['SILLAS', '200033042', 174],
        ]);

        $sinElegir = VerificacionCartera::leerCartera($ruta);
        self::assertFalse($sinElegir['encontrada']);
        self::assertSame([], $sinElegir['placas']);
        self::assertSame(['A' => 'A · Elemento', 'B' => 'B · Numero', 'C' => 'C · Unidades'], $sinElegir['columnas']);

        $elegida = VerificacionCartera::leerCartera($ruta, 'b', 'C');
        self::assertTrue($elegida['encontrada']);
        // La fila de títulos ("Numero") también se lee, pero no coincide con ninguna placa de MIA.
        self::assertSame(174, $elegida['placas']['200033042']);

        self::assertFalse(VerificacionCartera::leerCartera($ruta, 'Z')['encontrada'], 'una columna que no existe');
    }
}
