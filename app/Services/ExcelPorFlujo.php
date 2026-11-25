<?php

declare(strict_types=1);

namespace App\Services;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\BooleanCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Excel escrito por partes (OpenSpout), para los reportes grandes: la cartera y las planillas
 * de reintegros. PhpSpreadsheet arma todo el libro en memoria celda por celda (unos 20 µs por
 * celda): la cartera completa, con 5 876 bienes y sus dos hojas, tardaba 5 s. Por partes
 * tarda una fracción y usa poca memoria.
 *
 * El archivo es el mismo que escribía PhpSpreadsheet en ReporteService:
 *  - los tipos de cada celda siguen las reglas de su DefaultValueBinder: un texto que es un
 *    número («200033042») se guarda como número, salvo con ceros a la izquierda o más de 15
 *    cifras; un texto que empieza con «=» queda como texto, nunca como fórmula
 *    (BinderSinFormulas);
 *  - con $vacioSiIgualANull, lo que es == null (vacío, null, el cero) queda en blanco, como
 *    hacía fromArray() sin comparación estricta;
 *  - el ancho de cada columna sale de las primeras filas (ReporteService::autoajustarColumnas);
 *  - letra Calibri 11.
 */
final class ExcelPorFlujo
{
    /** Filas que se miran para calcular el ancho de cada columna (como en ReporteService). */
    private const FILAS_PARA_ANCHO = 300;
    private const QUINCE_NUEVES = 999999999999999;

    private Writer $escritor;
    private int $hojas = 0;

    private function __construct(string $destino)
    {
        $opciones = new Options();
        $opciones->DEFAULT_ROW_STYLE = (new Style())->setFontName('Calibri')->setFontSize(11);
        // Textos en la tabla compartida, como PhpSpreadsheet: con textos dentro de cada celda,
        // quien relee el archivo con PhpSpreadsheet recibe «texto enriquecido» en vez de texto.
        $opciones->SHOULD_USE_INLINE_STRINGS = false;
        $this->escritor = new Writer($opciones);
        $this->escritor->openToFile($destino);
    }

    /** Libro que se descarga: envía los encabezados de la descarga y escribe en la salida. */
    public static function descarga(string $nombreArchivo): self
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.xlsx"');
        header('Cache-Control: max-age=0');

        return new self('php://output');
    }

    /** Libro en un archivo (pruebas y mediciones). */
    public static function archivo(string $ruta): self
    {
        return new self($ruta);
    }

    /**
     * Agrega una hoja con su fila de títulos y sus filas.
     *
     * @param list<string> $encabezados
     * @param list<list<mixed>> $filas
     * @param list<int> $columnasTexto columnas (desde 0) que van siempre como texto, p. ej. la placa
     */
    public function hoja(string $titulo, array $encabezados, array $filas, bool $vacioSiIgualANull = true,
        array $columnasTexto = [], bool $inmovilizarTitulos = false): void
    {
        $hoja = $this->hojas === 0 ? $this->escritor->getCurrentSheet() : $this->escritor->addNewSheetAndMakeItCurrent();
        $hoja->setName($titulo);

        $vista = new SheetView();
        $vista->setTabSelected($this->hojas === 0);
        if ($inmovilizarTitulos) {
            $vista->setFreezeRow(2);
        }
        $hoja->setSheetView($vista);
        $this->hojas++;

        foreach (self::anchos([$encabezados, ...array_slice($filas, 0, self::FILAS_PARA_ANCHO - 1)], count($encabezados)) as $columna => $ancho) {
            $hoja->setColumnWidth($ancho, $columna + 1);
        }

        $this->escritor->addRow(new Row(array_map(static fn (string $t): Cell => new StringCell($t, null), $encabezados)));
        foreach ($filas as $fila) {
            $celdas = [];
            foreach ($fila as $i => $valor) {
                $celdas[] = self::celda($valor, $vacioSiIgualANull, in_array($i, $columnasTexto, true));
            }
            $this->escritor->addRow(new Row($celdas));
        }
    }

    public function cerrar(): void
    {
        $this->escritor->close();
    }

    /**
     * Ancho de cada columna según su texto más largo (entre 8 y 60 caracteres), igual que
     * ReporteService::autoajustarColumnas().
     *
     * @param list<list<mixed>> $muestra
     *
     * @return list<float>
     */
    private static function anchos(array $muestra, int $columnas): array
    {
        $anchos = [];
        for ($i = 0; $i < $columnas; $i++) {
            $largo = 0;
            foreach ($muestra as $fila) {
                $valor = $fila[$i] ?? null;
                $largo = max($largo, mb_strlen(is_scalar($valor) ? (string) $valor : ''));
            }
            $anchos[] = (float) min(60, max(8, $largo + 2));
        }

        return $anchos;
    }

    /** La celda con el tipo que le daba PhpSpreadsheet (DefaultValueBinder + BinderSinFormulas). */
    public static function celda(mixed $valor, bool $vacioSiIgualANull = true, bool $comoTexto = false): Cell
    {
        if ($valor === null || ($vacioSiIgualANull && $valor == null)) {
            return new EmptyCell(null, null);
        }
        if ($comoTexto) {
            return new StringCell(is_scalar($valor) ? (string) $valor : '', null);
        }
        if (is_bool($valor)) {
            return new BooleanCell($valor, null);
        }
        if (is_int($valor) && abs($valor) > self::QUINCE_NUEVES) {
            return new StringCell((string) $valor, null);
        }
        if (is_int($valor) || is_float($valor)) {
            return new NumericCell($valor, null);
        }
        $texto = is_scalar($valor) ? (string) $valor : '';
        if ($texto === '') {
            return new EmptyCell(null, null);
        }
        if (self::esNumeroComoTexto($texto) && is_numeric($texto)) {
            return new NumericCell($texto + 0, null);
        }

        return new StringCell($texto, null);
    }

    /** ¿PhpSpreadsheet guardaría este texto como número? */
    private static function esNumeroComoTexto(string $texto): bool
    {
        if (preg_match('/^[\+\-]?(\d+\.?\d*|\d*\.?\d+)([Ee][\-\+]?[0-2]?\d{1,3})?$/', $texto) !== 1) {
            return false;
        }
        $sinSigno = ltrim($texto, '+-');
        if (strlen($sinSigno) > 1 && $sinSigno[0] === '0' && $sinSigno[1] !== '.') {
            return false;
        }
        if (preg_match('/[eE.]/', $texto) !== 1 && abs((float) $texto) > self::QUINCE_NUEVES) {
            return false;
        }

        return is_numeric($texto) && is_finite((float) $texto);
    }
}
