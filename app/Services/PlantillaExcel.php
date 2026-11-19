<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Arma las plantillas de las cargas masivas (bienes, espacios y usuarios) para que cada una
 * se explique sola:
 *  - Arriba van las instrucciones: qué se hace con el archivo, qué es obligatorio y que no se borra nada.
 *  - La fila de títulos usa los colores de MIA: obligatorias en rojo claro, obligatorias solo
 *    para un registro nuevo en amarillo y opcionales en verde. Debajo de cada título va qué escribir.
 *  - Las columnas con opciones de MIA (espacios, categorías, personas, cargos, roles) tienen
 *    lista desplegable, tomada de una hoja oculta «Listas». Las demás validan el dato, y al
 *    pararse en una celda Excel dice qué escribir.
 *
 * Las cargas buscan la fila de títulos (LectorExcel::filaDeTitulos), así que también leen los
 * archivos de las plantillas anteriores, que tenían los títulos en la fila 1.
 */
final class PlantillaExcel
{
    public const OBLIGATORIA = 'obligatoria';
    public const SI_ES_NUEVO = 'si_es_nuevo';
    public const OPCIONAL = 'opcional';

    /** Las listas muestran «código — nombre»: la carga lee lo que va antes de la raya. */
    public const RAYA = '—';
    public const SEPARADOR = ' ' . self::RAYA . ' ';
    /** Filas con listas, validaciones y ayuda debajo de los títulos. */
    public const FILAS_DE_DATOS = 10000;

    private const HOJA_LISTAS = 'Listas';

    // Colores de MIA (public/assets/css/tokens.css), sin el «#».
    private const VERDE = '1F6F54';          // --sigebi-primary
    private const VERDE_FONDO = 'E3F3E9';    // --sigebi-exito-fondo
    private const VERDE_TEXTO = '1B5E37';    // --sigebi-exito-texto
    private const VERDE_BORDE = '7CBF96';    // --sigebi-exito-borde
    private const ROJO_CLARO = 'E5A39B';     // --sigebi-peligro-borde
    private const ROJO_FONDO = 'FBE9E7';     // --sigebi-peligro-fondo
    private const ROJO_TEXTO = '8E2A20';     // --sigebi-peligro-texto
    private const AMARILLO = 'E0B54D';       // --sigebi-advertencia-borde
    private const AMARILLO_FONDO = 'FFF4D6'; // --sigebi-advertencia-fondo
    private const AMARILLO_TEXTO = '6B4600'; // --sigebi-advertencia-texto
    private const TEXTO = '1F2937';          // --sigebi-gris-800
    private const TEXTO_SUAVE = '4B5563';    // --sigebi-gris-600
    private const BORDE = 'D9DEE4';          // --sigebi-border

    /** Color del título de cada párrafo de instrucciones. */
    private const TONOS = ['verde' => self::VERDE_TEXTO, 'rojo' => self::ROJO_TEXTO, 'amarillo' => self::AMARILLO_TEXTO, 'suave' => self::TEXTO_SUAVE];

    /**
     * Fila de los títulos: la 1 es el nombre de la plantilla, la 2 la institución, luego un
     * renglón por párrafo de instrucciones.
     */
    public static function filaDeTitulos(int $parrafos): int
    {
        return 3 + $parrafos;
    }

    /** Lo que vale de una celda llenada desde una lista «código — nombre»: el código. */
    public static function valorDeLista(string $texto): string
    {
        return str_contains($texto, self::RAYA) ? trim(explode(self::RAYA, $texto, 2)[0]) : $texto;
    }

    /**
     * @param list<array{0: string, 1: string, 2: string}> $parrafos [título en negrita, texto, tono]; «{fila}» es la primera fila de datos
     * @param array<string, array{titulo: string, nivel: string, ancho: int, pista: string, tituloAyuda: string, ayuda: string, formato?: string, lista?: string, errorLista?: string, regla?: array{0: string, 1: ?string, 2: string, 3: ?string, 4: string, 5: string}}> $columnas
     *        por letra, desde la A. «regla»: [tipo, operador, fórmula 1, fórmula 2, título del error, error], con
     *        «{celda}» (la primera celda de datos de la columna) y «{rango}» (todas, con $).
     * @param array<string, array<string>> $listas opciones de cada lista, por nombre
     */
    public static function armar(string $hoja, string $titulo, string $institucion, array $parrafos, array $columnas, array $listas): Spreadsheet
    {
        $libro = new Spreadsheet();
        $libro->getProperties()->setCreator('MIA')->setTitle($titulo);
        $libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11)->getColor()->setRGB(self::TEXTO);

        $datos = $libro->getActiveSheet();
        $datos->setTitle($hoja);
        $datos->getTabColor()->setRGB(self::VERDE);

        $rangos = self::hojaDeListas($libro, $listas);
        $filaTitulos = self::filaDeTitulos(count($parrafos));
        $ultima = array_key_last($columnas);
        if ($ultima === null) {
            throw new \InvalidArgumentException('Una plantilla necesita al menos una columna.');
        }

        self::columnasDeDatos($libro, $datos, $columnas);
        self::instrucciones($datos, $titulo, $institucion, $parrafos, $columnas, $filaTitulos, $ultima);
        self::titulos($datos, $columnas, $filaTitulos);
        self::validaciones($datos, $columnas, $rangos, $filaTitulos);

        $datos->freezePane('A' . ($filaTitulos + 1));
        $datos->setSelectedCell('A' . ($filaTitulos + 1));
        $datos->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($filaTitulos, $filaTitulos);
        $libro->setActiveSheetIndex(0);

        return $libro;
    }

    public static function enviar(Spreadsheet $libro, string $archivo): never
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $archivo . '"');
        header('Cache-Control: max-age=0');
        (new Xlsx($libro))->save('php://output');
        exit;
    }

    /**
     * Formato de cada columna de datos (relleno de las obligatorias, texto, fecha, número).
     * Va a la columna entera, no celda por celda: así el archivo no lleva miles de celdas
     * vacías (que Excel imprimiría como páginas en blanco). Se aplica antes que todo lo demás,
     * y las filas de instrucciones y de títulos lo reemplazan.
     *
     * @param array<string, array<string, mixed>> $columnas
     */
    private static function columnasDeDatos(Spreadsheet $libro, Worksheet $hoja, array $columnas): void
    {
        $borde = ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDE]];
        foreach ($columnas as $columna => $c) {
            $estilo = [];
            $relleno = match ($c['nivel']) {
                self::OBLIGATORIA => self::ROJO_FONDO,
                self::SI_ES_NUEVO => self::AMARILLO_FONDO,
                default => null,
            };
            if ($relleno !== null) {
                $estilo['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $relleno]];
                $estilo['borders'] = ['left' => $borde, 'right' => $borde, 'top' => $borde, 'bottom' => $borde];
            }
            if (isset($c['formato'])) {
                $estilo['numberFormat'] = ['formatCode' => $c['formato']];
            }
            if ($estilo === []) {
                continue;
            }
            // Un estilo propio de la columna (no un rango de celdas: PhpSpreadsheet repartiría
            // los bordes celda por celda hasta la última fila de Excel).
            $xf = clone $libro->getCellXfByIndex(0);
            $xf->applyFromArray($estilo);
            $libro->addCellXf($xf);
            $hoja->getColumnDimension((string) $columna)->setXfIndex($xf->getIndex());
        }
    }

    /**
     * Nombre de la plantilla, institución y párrafos, en celdas unidas sobre todas las columnas.
     *
     * @param list<array{0: string, 1: string, 2: string}> $parrafos
     * @param array<string, array<string, mixed>> $columnas
     */
    private static function instrucciones(Worksheet $hoja, string $titulo, string $institucion, array $parrafos, array $columnas, int $filaTitulos, string $ultima): void
    {
        // Sin el formato de las columnas de datos (relleno, texto, fecha).
        $hoja->getStyle("A1:{$ultima}{$filaTitulos}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_NONE],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]],
            'numberFormat' => ['formatCode' => NumberFormat::FORMAT_GENERAL],
        ]);

        $hoja->mergeCells("A1:{$ultima}1");
        $hoja->setCellValue('A1', $titulo . ' · MIA');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB(self::VERDE);
        $hoja->getRowDimension(1)->setRowHeight(26);

        $hoja->mergeCells("A2:{$ultima}2");
        $hoja->setCellValue('A2', ($institucion !== '' ? "Institución: {$institucion} · " : '')
            . 'Descargada el ' . date('Y-m-d') . '. Las listas desplegables traen lo que había en MIA ese día.');
        $hoja->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB(self::TEXTO_SUAVE);

        $anchoTotal = array_sum(array_column($columnas, 'ancho'));
        foreach ($parrafos as $i => [$negrita, $resto, $tono]) {
            $fila = 3 + $i;
            $resto = str_replace('{fila}', (string) ($filaTitulos + 1), $resto);
            $texto = new RichText();
            $texto->createTextRun($negrita)->getFont()?->setBold(true)->setSize(11)->setColor(new Color('FF' . (self::TONOS[$tono] ?? self::TEXTO)));
            $texto->createTextRun($resto)->getFont()?->setSize(11)->setColor(new Color('FF' . self::TEXTO));
            $hoja->mergeCells("A{$fila}:{$ultima}{$fila}");
            $hoja->getCell("A{$fila}")->setValue($texto);
            // Excel no ajusta solo el alto de las celdas unidas: se calcula por el largo.
            $lineas = (int) ceil(mb_strlen($negrita . $resto) * 0.95 / max(1, $anchoTotal));
            $hoja->getRowDimension($fila)->setRowHeight(max(1, $lineas) * 15 + 5);
        }
        $hoja->getStyle("A2:{$ultima}" . ($filaTitulos - 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * Fila de títulos: nombre en negrita y, debajo, qué escribir.
     *
     * @param array<string, array<string, mixed>> $columnas
     */
    private static function titulos(Worksheet $hoja, array $columnas, int $fila): void
    {
        foreach ($columnas as $columna => $c) {
            [$fondo, $texto, $borde, $linea] = match ($c['nivel']) {
                self::OBLIGATORIA => [self::ROJO_CLARO, self::ROJO_TEXTO, self::ROJO_TEXTO, self::ROJO_TEXTO],
                self::SI_ES_NUEVO => [self::AMARILLO, self::AMARILLO_TEXTO, self::AMARILLO_TEXTO, self::AMARILLO_TEXTO],
                default => [self::VERDE_FONDO, self::VERDE_TEXTO, self::VERDE_BORDE, self::VERDE],
            };
            $contenido = new RichText();
            $contenido->createTextRun($c['titulo'] . ($c['nivel'] === self::OBLIGATORIA ? ' *' : ''))->getFont()?->setBold(true)->setSize(11)
                ->setColor(new Color('FF' . $texto));
            $contenido->createTextRun("\n" . $c['pista'])->getFont()?->setSize(9)->setColor(new Color('FF' . self::TEXTO_SUAVE));
            $hoja->getCell("{$columna}{$fila}")->setValue($contenido);
            $hoja->getColumnDimension((string) $columna)->setWidth($c['ancho']);

            $estilo = $hoja->getStyle("{$columna}{$fila}");
            $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fondo);
            $estilo->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($borde);
            $estilo->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB($linea);
        }
        $ultima = array_key_last($columnas);
        $hoja->getStyle("A{$fila}:{$ultima}{$fila}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $hoja->getRowDimension($fila)->setRowHeight(48);
    }

    /**
     * Validación de cada columna: el mensaje al pararse en la celda y, si el dato no sirve,
     * Excel no lo deja escribir y explica por qué.
     *
     * @param array<string, array<string, mixed>> $columnas
     * @param array<string, ?string> $rangos rango de cada lista, o null si está vacía
     */
    private static function validaciones(Worksheet $hoja, array $columnas, array $rangos, int $filaTitulos): void
    {
        [$desde, $hasta] = [$filaTitulos + 1, $filaTitulos + self::FILAS_DE_DATOS];

        foreach ($columnas as $columna => $c) {
            $regla = $c['regla'] ?? null;
            if (isset($c['lista'])) {
                $rango = $rangos[$c['lista']] ?? null;
                $regla = $rango !== null
                    ? [DataValidation::TYPE_LIST, null, $rango, null, 'Elige de la lista', (string) ($c['errorLista'] ?? 'Elige una opción de la lista.')]
                    : null;
            }

            $validacion = new DataValidation();
            $validacion->setAllowBlank(true)->setShowInputMessage(true)
                ->setPromptTitle($c['tituloAyuda'])->setPrompt($c['ayuda']);
            if ($regla === null) {
                if (isset($c['lista'])) {
                    // Lista vacía: solo el mensaje, y avisa que no hay de dónde elegir.
                    $validacion->setPrompt(mb_substr($c['ayuda'], 0, 150) . ' (Esta institución todavía no tiene opciones para elegir.)');
                }
                $validacion->setType(DataValidation::TYPE_NONE);
            } else {
                [$tipo, $operador, $formula1, $formula2, $tituloError, $error] = $regla;
                $reemplazos = ['{celda}' => "{$columna}{$desde}", '{rango}' => "\${$columna}\${$desde}:\${$columna}\${$hasta}"];
                $validacion->setType($tipo)->setErrorStyle(DataValidation::STYLE_STOP)
                    ->setShowErrorMessage(true)->setErrorTitle($tituloError)->setError($error)
                    ->setFormula1(strtr($formula1, $reemplazos));
                if ($operador !== null) {
                    $validacion->setOperator($operador);
                }
                if ($formula2 !== null) {
                    $validacion->setFormula2(strtr($formula2, $reemplazos));
                }
                if ($tipo === DataValidation::TYPE_LIST) {
                    $validacion->setShowDropDown(true);
                }
            }
            $hoja->setDataValidation("{$columna}{$desde}:{$columna}{$hasta}", $validacion);
        }
    }

    /**
     * Hoja oculta con las opciones de cada lista, una por columna (una lista desplegable de
     * Excel no admite más de 255 caracteres escritos en la regla, pero sí un rango de celdas).
     *
     * @param array<string, array<string>> $listas
     * @return array<string, ?string> rango de cada lista, o null si está vacía
     */
    private static function hojaDeListas(Spreadsheet $libro, array $listas): array
    {
        $hoja = $libro->createSheet();
        $hoja->setTitle(self::HOJA_LISTAS);
        $rangos = [];
        $numero = 1;
        foreach ($listas as $nombre => $valores) {
            $columna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numero++);
            $hoja->setCellValue("{$columna}1", $nombre);
            $valores = array_values($valores);
            foreach ($valores as $i => $valor) {
                $hoja->getCell($columna . ($i + 2))->setValueExplicit($valor, DataType::TYPE_STRING);
            }
            $rangos[$nombre] = $valores !== []
                ? "'" . self::HOJA_LISTAS . "'!\${$columna}\$2:\${$columna}\$" . (count($valores) + 1)
                : null;
        }
        $hoja->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);

        return $rangos;
    }
}
