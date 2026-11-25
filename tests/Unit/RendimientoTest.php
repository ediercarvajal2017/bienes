<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\TiemposServidor;
use App\Helpers\FilasCarga;
use App\Services\ExcelPorFlujo;
use App\Services\ReporteService;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;

/**
 * Mejoras de la prueba de velocidad del 2026-11-24: el resultado de una carga masiva por
 * páginas, el CSV de la cartera escrito directamente y el encabezado Server-Timing.
 */
final class RendimientoTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private static function filas(): array
    {
        $tipos = array_merge(array_fill(0, 120, 'sin_cambios'), array_fill(0, 7, 'invalido'), array_fill(0, 3, 'nuevo'));

        return array_map(static fn (string $tipo, int $i): array => ['fila' => $i + 2, 'tipo' => $tipo], $tipos, array_keys($tipos));
    }

    public function testElResultadoDeUnaCargaVaPorPaginasDeCincuenta(): void
    {
        $pagina = FilasCarga::pagina(self::filas(), []);

        self::assertCount(50, $pagina['filas']);
        self::assertSame(2, $pagina['filas'][0]['fila']);
        self::assertSame(130, $pagina['total']);
        self::assertSame(3, $pagina['totalPaginas']);
        self::assertSame(['invalido' => 7, 'nuevo' => 3, 'modificado' => 0, 'sin_cambios' => 120], $pagina['conteo']);

        $ultima = FilasCarga::pagina(self::filas(), ['pagina' => '3']);
        self::assertCount(30, $ultima['filas']);
        self::assertSame(102, $ultima['filas'][0]['fila']);

        // Una página que no existe muestra la última; «Todos» (0) trae todas.
        self::assertSame(3, FilasCarga::pagina(self::filas(), ['pagina' => '99'])['pagina']);
        self::assertCount(130, FilasCarga::pagina(self::filas(), ['porPagina' => '0'])['filas']);
        // Un tamaño que no está en las opciones vuelve al de siempre.
        self::assertSame(50, FilasCarga::pagina(self::filas(), ['porPagina' => '7'])['porPagina']);
    }

    public function testElFiltroMuestraSoloLasFilasDeUnEstado(): void
    {
        $invalidas = FilasCarga::pagina(self::filas(), ['estado' => 'invalido']);

        self::assertSame('invalido', $invalidas['estado']);
        self::assertSame(7, $invalidas['total']);
        self::assertSame(['invalido'], array_values(array_unique(array_column($invalidas['filas'], 'tipo'))));
        // El conteo de cada estado no cambia con el filtro (es el de los botones).
        self::assertSame(120, $invalidas['conteo']['sin_cambios']);

        // Un estado desconocido se ignora.
        self::assertSame('', FilasCarga::pagina(self::filas(), ['estado' => 'borrado'])['estado']);
    }

    public function testLaLineaDelCsvEsComoLaDeAntes(): void
    {
        // Todo entre comillas, comillas internas dobladas y fin de línea del sistema.
        self::assertSame('"PA-1","Silla ""roja""","Muebles"' . PHP_EOL, ReporteService::lineaCsv(['PA-1', 'Silla "roja"', 'Muebles']));
        // Como en el Excel: vacío, null y el cero quedan en blanco; los decimales se conservan.
        self::assertSame('"","","","1500000","12.5"' . PHP_EOL, ReporteService::lineaCsv(['', null, 0.0, 1500000.0, 12.5]));
    }

    public function testElCsvNoDejaQueUnTextoSeVuelvaFormula(): void
    {
        self::assertSame('"\'=HYPERLINK(1)","\'+57 300","\'@SUMA","-5","\'-abc"' . PHP_EOL,
            ReporteService::lineaCsv(['=HYPERLINK(1)', '+57 300', '@SUMA', '-5', '-abc']));
    }

    public function testElExcelPorFlujoDaACadaCeldaElTipoQueLeDabaPhpSpreadsheet(): void
    {
        // Un número escrito como texto se guarda como número; con ceros a la izquierda o más de
        // 15 cifras, como texto; lo que empieza con «=» nunca es fórmula.
        self::assertInstanceOf(NumericCell::class, ExcelPorFlujo::celda('200033042'));
        self::assertSame(200033042, ExcelPorFlujo::celda('200033042')->getValue());
        self::assertInstanceOf(StringCell::class, ExcelPorFlujo::celda('00123'));
        self::assertInstanceOf(StringCell::class, ExcelPorFlujo::celda('1234567890123456'));
        self::assertInstanceOf(StringCell::class, ExcelPorFlujo::celda('=HYPERLINK("http://x")'));
        self::assertInstanceOf(StringCell::class, ExcelPorFlujo::celda('20006633101-045'));
        // Como fromArray() sin comparación estricta: el cero queda en blanco; con ella, se ve.
        self::assertInstanceOf(EmptyCell::class, ExcelPorFlujo::celda(0.0));
        self::assertInstanceOf(NumericCell::class, ExcelPorFlujo::celda(0, false));
        // Una columna de texto (la placa) no se vuelve número.
        self::assertInstanceOf(StringCell::class, ExcelPorFlujo::celda('200033042', true, true));
    }

    public function testElExcelPorFlujoEscribeLasHojasComoAntes(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'flujo') . '.xlsx';
        try {
            $libro = ExcelPorFlujo::archivo($ruta);
            $libro->hoja('Cartera de bienes', ['Código', 'Descripción', 'Valor'], [['PA-1', '=SUMA(A1:A2)', 1500.0], ['200033042', 'Silla', 0.0]]);
            $libro->hoja('Cartera por placa', ['Placa', 'Cantidad'], [['00012', 0], ['200033042', 7]], false, [0], true);
            $libro->cerrar();

            $leido = IOFactory::load($ruta);
            self::assertSame(['Cartera de bienes', 'Cartera por placa'], $leido->getSheetNames());
            self::assertSame('Cartera de bienes', $leido->getActiveSheet()->getTitle());

            $cartera = $leido->getSheet(0);
            self::assertSame('=SUMA(A1:A2)', $cartera->getCell('B2')->getValue());
            self::assertSame(DataType::TYPE_STRING, $cartera->getCell('B2')->getDataType());
            self::assertSame(DataType::TYPE_NUMERIC, $cartera->getCell('A3')->getDataType());
            self::assertNull($cartera->getCell('C3')->getValue());
            self::assertNull($cartera->getFreezePane());
            self::assertEqualsWithDelta(14.0, $cartera->getColumnDimension('B')->getWidth(), 0.01);

            $placas = $leido->getSheet(1);
            self::assertSame('A2', $placas->getFreezePane());
            self::assertSame('00012', $placas->getCell('A2')->getValue());
            self::assertSame(DataType::TYPE_STRING, $placas->getCell('A3')->getDataType());
            self::assertEquals(0, $placas->getCell('B2')->getValue());
        } finally {
            @unlink($ruta);
        }
    }

    public function testElEncabezadoDeTiemposLlevaSoloDuracionesEnMilisegundos(): void
    {
        self::assertSame('arranque;dur=3, bd;dur=12, sesion;dur=1, app;dur=40',
            TiemposServidor::valor(['arranque' => 2.6, 'bd' => 11.5, 'sesion' => 0.8, 'app' => 40.2]));
    }
}
