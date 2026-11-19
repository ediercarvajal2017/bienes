<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Categoria;
use App\Models\Espacio;
use App\Models\Institucion;
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
 * Plantilla de la carga masiva de bienes (/cargas-masivas): un solo Excel que se explica solo.
 *  - Arriba, las instrucciones: qué se hace con el archivo, qué es obligatorio y que no se borra nada.
 *  - La fila de títulos: obligatorias en rojo claro y opcionales en verde (colores de MIA), con
 *    qué escribir debajo de cada título.
 *  - Ubicación y Categoría con lista desplegable de lo que hay en la institución (hoja oculta
 *    «Listas»). Las demás columnas validan el dato, y al pararse en una celda Excel dice qué escribir.
 *
 * CargaMasivaService::analizar() busca la fila de títulos, así que también lee los archivos
 * hechos con la plantilla anterior (títulos en la fila 1).
 */
final class PlantillaCargaBienes
{
    public const HOJA = 'Bienes';
    public const FILA_TITULOS = 8;
    /** En la lista de Ubicación cada espacio es «código — nombre»; la carga lee lo que va antes de la raya. */
    public const RAYA = '—';
    public const SEPARADOR_UBICACION = ' ' . self::RAYA . ' ';
    /** Filas con listas, validaciones y ayuda (de la 9 a la 10008). */
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
    private const TEXTO = '1F2937';          // --sigebi-gris-800
    private const TEXTO_SUAVE = '4B5563';    // --sigebi-gris-600
    private const BORDE = 'D9DEE4';          // --sigebi-border

    /**
     * Columnas A-H, en el orden que lee CargaMasivaService. «pista» va debajo del título;
     * «ayuda» es el mensaje que Excel muestra al pararse en la celda (título de hasta 32
     * caracteres y texto de hasta 255: más largos, Excel da el archivo por dañado).
     *
     * @var array<string, array{titulo: string, obligatoria: bool, ancho: int, pista: string, tituloAyuda: string, ayuda: string}>
     */
    public const COLUMNAS = [
        'A' => [
            'titulo' => 'Código', 'obligatoria' => true, 'ancho' => 22,
            'pista' => 'Obligatorio. La placa, ej.: 200033042', 'tituloAyuda' => 'Código (obligatorio)',
            'ayuda' => 'La placa del bien, como figura en la cartera. Si el código ya está en MIA, la fila actualiza ese bien; si no, lo crea. Cada código va una sola vez en el archivo.',
        ],
        'B' => [
            'titulo' => 'Descripción', 'obligatoria' => true, 'ancho' => 44,
            'pista' => 'Obligatoria. Qué es el bien, ej.: SILLA UNIVERSITARIA EN MADERA', 'tituloAyuda' => 'Descripción (obligatoria)',
            'ayuda' => 'Qué es el bien, como figura en la cartera: tipo, material, color y modelo. Máximo 200 caracteres.',
        ],
        'C' => [
            'titulo' => 'Marca', 'obligatoria' => false, 'ancho' => 16,
            'pista' => 'Ej.: Rimax', 'tituloAyuda' => 'Marca (opcional)',
            'ayuda' => 'El fabricante (Rimax, HP, Epson). Si el bien ya está en MIA y la dejas en blanco, conserva la marca que tiene.',
        ],
        'D' => [
            'titulo' => 'Fecha de ingreso', 'obligatoria' => false, 'ancho' => 18,
            'pista' => 'Ej.: 2026-01-15', 'tituloAyuda' => 'Fecha de ingreso (opcional)',
            'ayuda' => 'Día en que llegó el bien; no puede ser futura. En blanco: un bien nuevo queda con la fecha de hoy y uno que ya está en MIA conserva la suya.',
        ],
        'E' => [
            'titulo' => 'Valor', 'obligatoria' => false, 'ancho' => 17,
            'pista' => 'En pesos, ej.: 85000', 'tituloAyuda' => 'Valor (opcional)',
            'ayuda' => 'Valor unitario en pesos: solo números, sin negativos y de máximo 10 dígitos. En blanco: un bien nuevo queda en 0 y uno que ya está en MIA conserva su valor.',
        ],
        'F' => [
            'titulo' => 'Ubicación', 'obligatoria' => false, 'ancho' => 38,
            'pista' => 'Elige el espacio de la lista', 'tituloAyuda' => 'Ubicación (opcional)',
            'ayuda' => 'Elige de la lista el espacio donde queda el bien. Si no aparece, créalo o actívalo en MIA (Espacios) y descarga otra vez la plantilla. En blanco: conserva el espacio que ya tiene.',
        ],
        'G' => [
            'titulo' => 'Categoría', 'obligatoria' => false, 'ancho' => 24,
            'pista' => 'Elige de la lista', 'tituloAyuda' => 'Categoría (opcional)',
            'ayuda' => 'Elige la categoría de la lista. «Sin cartera» exige un código de 10 números. En blanco: un bien nuevo queda sin categoría y uno que ya está en MIA conserva la suya.',
        ],
        'H' => [
            'titulo' => 'Responsable individual', 'obligatoria' => false, 'ancho' => 26,
            'pista' => 'Documento, ej.: 1234567890', 'tituloAyuda' => 'Responsable (opcional)',
            'ayuda' => 'Documento de un usuario activo de MIA, sin puntos: el bien queda a su cargo. Déjalo en blanco si el bien es del espacio (grupal) o para conservar la persona que ya tiene.',
        ],
    ];

    public static function enviar(int $institucionId): never
    {
        $libro = self::armar($institucionId);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="plantilla_carga_bienes.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($libro))->save('php://output');
        exit;
    }

    public static function armar(int $institucionId): Spreadsheet
    {
        $libro = new Spreadsheet();
        $libro->getProperties()->setCreator('MIA')->setTitle('Plantilla de carga masiva de bienes');
        $libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11)->getColor()->setRGB(self::TEXTO);

        $hoja = $libro->getActiveSheet();
        $hoja->setTitle(self::HOJA);
        $hoja->getTabColor()->setRGB(self::VERDE);

        $categorias = array_map(static fn (array $c): string => (string) $c['nombre'], Categoria::activas($institucionId));
        $ubicaciones = array_map(
            static fn (array $e): string => $e['codigo'] . self::SEPARADOR_UBICACION . $e['nombre'],
            Espacio::listadoParaSelect($institucionId)
        );
        $listas = self::hojaDeListas($libro, $categorias, $ubicaciones);

        self::columnasDeDatos($libro, $hoja);
        self::instrucciones($hoja, (string) (Institucion::find($institucionId)['nombre'] ?? ''));
        self::titulos($hoja);
        self::validaciones($hoja, $listas);

        $hoja->freezePane('A' . (self::FILA_TITULOS + 1));
        $hoja->setSelectedCell('A' . (self::FILA_TITULOS + 1));
        $hoja->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(self::FILA_TITULOS, self::FILA_TITULOS);
        $libro->setActiveSheetIndex(0);

        return $libro;
    }

    /** Texto de las filas 1 a 7, en celdas unidas de la A a la H. */
    private static function instrucciones(Worksheet $hoja, string $institucion): void
    {
        // Sin el formato de las columnas de datos (rojo, texto, fecha).
        $hoja->getStyle('A1:H' . self::FILA_TITULOS)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_NONE],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]],
            'numberFormat' => ['formatCode' => NumberFormat::FORMAT_GENERAL],
        ]);

        $hoja->mergeCells('A1:H1');
        $hoja->setCellValue('A1', 'Plantilla de carga masiva de bienes · MIA');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB(self::VERDE);
        $hoja->getRowDimension(1)->setRowHeight(26);

        $hoja->mergeCells('A2:H2');
        $hoja->setCellValue('A2', ($institucion !== '' ? "Institución: {$institucion} · " : '')
            . 'Descargada el ' . date('Y-m-d') . '. Las listas de Ubicación y Categoría son las de esta institución.');
        $hoja->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB(self::TEXTO_SUAVE);

        $parrafos = [
            3 => ['Cómo se usa: ', 'escribe un bien por fila, desde la fila ' . (self::FILA_TITULOS + 1) . ', y sube el archivo en MIA › Bienes › Acciones › Carga masiva de bienes. Primero se analiza y ves qué cambiaría en cada fila; nada se guarda hasta que pulses «Confirmar importación».', self::VERDE_TEXTO],
            4 => ['Obligatorias (títulos en rojo claro): ', 'Código (la placa) y Descripción.', self::ROJO_TEXTO],
            5 => ['Opcionales (títulos en verde): ', 'Marca, Fecha de ingreso, Valor (solo números, sin negativos, máximo 10 dígitos), Ubicación (código de un espacio que ya existe: elígelo de la lista), Categoría (nombre de una categoría activa: elígela de la lista) y Responsable individual (documento de un usuario activo: el bien queda a su cargo).', self::VERDE_TEXTO],
            6 => ['No se borra nada: ', 'los bienes que no están en el archivo no se tocan, y una columna opcional en blanco conserva lo que el bien ya tiene en MIA.', self::VERDE_TEXTO],
            7 => ['Consejo: ', 'al hacer clic en una celda, Excel te dice qué escribir en ella. No cambies el orden de las columnas ni borres la fila de títulos.', self::TEXTO_SUAVE],
        ];
        $anchoTotal = array_sum(array_column(self::COLUMNAS, 'ancho'));
        foreach ($parrafos as $fila => [$negrita, $resto, $color]) {
            $texto = new RichText();
            $texto->createTextRun($negrita)->getFont()?->setBold(true)->setSize(11)->setColor(new Color('FF' . $color));
            $texto->createTextRun($resto)->getFont()?->setSize(11)->setColor(new Color('FF' . self::TEXTO));
            $hoja->mergeCells("A{$fila}:H{$fila}");
            $hoja->getCell("A{$fila}")->setValue($texto);
            // Excel no ajusta solo el alto de las celdas unidas: se calcula por el largo.
            $lineas = (int) ceil(mb_strlen($negrita . $resto) * 1.1 / $anchoTotal);
            $hoja->getRowDimension($fila)->setRowHeight(max(1, $lineas) * 15 + 5);
        }
        $hoja->getStyle('A2:H7')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
    }

    /** Fila de títulos: nombre en negrita y, debajo, qué escribir. */
    private static function titulos(Worksheet $hoja): void
    {
        $fila = self::FILA_TITULOS;
        foreach (self::COLUMNAS as $columna => $c) {
            $texto = new RichText();
            $texto->createTextRun($c['titulo'] . ($c['obligatoria'] ? ' *' : ''))->getFont()?->setBold(true)->setSize(11)
                ->setColor(new Color('FF' . ($c['obligatoria'] ? self::ROJO_TEXTO : self::VERDE_TEXTO)));
            $texto->createTextRun("\n" . $c['pista'])->getFont()?->setSize(9)
                ->setColor(new Color('FF' . self::TEXTO_SUAVE));
            $hoja->getCell("{$columna}{$fila}")->setValue($texto);
            $hoja->getColumnDimension($columna)->setWidth($c['ancho']);

            $estilo = $hoja->getStyle("{$columna}{$fila}");
            $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($c['obligatoria'] ? self::ROJO_CLARO : self::VERDE_FONDO);
            $estilo->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($c['obligatoria'] ? self::ROJO_TEXTO : self::VERDE_BORDE);
            $estilo->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB($c['obligatoria'] ? self::ROJO_TEXTO : self::VERDE);
        }
        $hoja->getStyle("A{$fila}:H{$fila}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $hoja->getRowDimension($fila)->setRowHeight(48);
    }

    /**
     * Formato de las columnas de datos: las obligatorias en rojo muy claro; códigos y
     * documentos como texto. Va a la columna entera, no celda por celda: así el archivo no
     * lleva miles de celdas vacías (que Excel imprimiría como páginas en blanco). Se aplica
     * antes que todo lo demás, y las filas de instrucciones y de títulos lo reemplazan.
     */
    private static function columnasDeDatos(Spreadsheet $libro, Worksheet $hoja): void
    {
        $borde = ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDE]];
        $obligatoria = [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::ROJO_FONDO]],
            'borders' => ['left' => $borde, 'right' => $borde, 'top' => $borde, 'bottom' => $borde],
        ];
        // Como texto: un código o un documento con ceros a la izquierda no los pierde.
        $texto = ['numberFormat' => ['formatCode' => NumberFormat::FORMAT_TEXT]];

        $estilos = [
            'A' => $obligatoria + $texto,
            'B' => $obligatoria,
            'D' => ['numberFormat' => ['formatCode' => 'yyyy-mm-dd']],
            'E' => ['numberFormat' => ['formatCode' => '#,##0']],
            'H' => $texto,
        ];
        foreach ($estilos as $columna => $estilo) {
            // Un estilo propio de la columna (no un rango de celdas: PhpSpreadsheet repartiría
            // los bordes celda por celda hasta la última fila de Excel).
            $xf = clone $libro->getCellXfByIndex(0);
            $xf->applyFromArray($estilo);
            $libro->addCellXf($xf);
            $hoja->getColumnDimension($columna)->setXfIndex($xf->getIndex());
        }
    }

    /**
     * Validación de cada columna: el mensaje al pararse en la celda y, si el dato no sirve,
     * Excel no lo deja escribir y explica por qué.
     *
     * @param array{categorias: ?string, ubicaciones: ?string} $listas rango de cada lista, o null si está vacía
     */
    private static function validaciones(Worksheet $hoja, array $listas): void
    {
        [$desde, $hasta] = [self::FILA_TITULOS + 1, self::FILA_TITULOS + self::FILAS_DE_DATOS];

        $reglas = [
            'A' => [DataValidation::TYPE_CUSTOM, null, "AND(LEN(A{$desde})<=40,COUNTIF(\$A\${$desde}:\$A\${$hasta},A{$desde})=1)", null,
                'Código repetido o muy largo', 'Cada código va una sola vez en el archivo y tiene máximo 40 caracteres.'],
            'B' => [DataValidation::TYPE_TEXTLENGTH, DataValidation::OPERATOR_LESSTHANOREQUAL, '200', null,
                'Descripción muy larga', 'La descripción tiene máximo 200 caracteres.'],
            'C' => [DataValidation::TYPE_TEXTLENGTH, DataValidation::OPERATOR_LESSTHANOREQUAL, '100', null,
                'Marca muy larga', 'La marca tiene máximo 100 caracteres.'],
            'D' => [DataValidation::TYPE_DATE, DataValidation::OPERATOR_BETWEEN, 'DATE(1950,1,1)', 'TODAY()',
                'Fecha no válida', 'Escribe una fecha que no sea futura, por ejemplo 2026-01-15.'],
            'E' => [DataValidation::TYPE_DECIMAL, DataValidation::OPERATOR_BETWEEN, '0', '9999999999.99',
                'Valor no válido', 'Escribe solo números, sin puntos ni signo $, sin negativos y de máximo 10 dígitos. Por ejemplo 85000.'],
            'F' => $listas['ubicaciones'] !== null
                ? [DataValidation::TYPE_LIST, null, $listas['ubicaciones'], null,
                    'Elige un espacio de la lista', 'Ese espacio no está en la lista. Si falta, créalo o actívalo en MIA (Espacios) y descarga otra vez la plantilla.']
                : null,
            'G' => $listas['categorias'] !== null
                ? [DataValidation::TYPE_LIST, null, $listas['categorias'], null,
                    'Elige una categoría de la lista', 'Esa categoría no está en la lista. Si falta, créala o actívala en MIA (Categorías) y descarga otra vez la plantilla.']
                : null,
            'H' => [DataValidation::TYPE_TEXTLENGTH, DataValidation::OPERATOR_LESSTHANOREQUAL, '20', null,
                'Documento muy largo', 'El documento tiene máximo 20 caracteres, sin puntos.'],
        ];

        foreach (self::COLUMNAS as $columna => $c) {
            $regla = $reglas[$columna];
            $validacion = new DataValidation();
            $validacion->setAllowBlank(true)->setShowInputMessage(true)
                ->setPromptTitle($c['tituloAyuda'])
                ->setPrompt($c['ayuda']);
            if ($regla === null) {
                // Lista vacía: solo el mensaje, y avisa que no hay de dónde elegir.
                $validacion->setType(DataValidation::TYPE_NONE)
                    ->setPrompt(mb_substr($c['ayuda'], 0, 150) . ' (Esta institución todavía no tiene opciones para elegir.)');
            } else {
                [$tipo, $operador, $formula1, $formula2, $tituloError, $error] = $regla;
                $validacion->setType($tipo)->setErrorStyle(DataValidation::STYLE_STOP)
                    ->setShowErrorMessage(true)->setErrorTitle($tituloError)->setError($error)
                    ->setFormula1($formula1);
                if ($operador !== null) {
                    $validacion->setOperator($operador);
                }
                if ($formula2 !== null) {
                    $validacion->setFormula2($formula2);
                }
                if ($tipo === DataValidation::TYPE_LIST) {
                    $validacion->setShowDropDown(true);
                }
            }
            $hoja->setDataValidation("{$columna}{$desde}:{$columna}{$hasta}", $validacion);
        }
    }

    /**
     * Hoja oculta con las opciones de Ubicación y Categoría (una lista desplegable de Excel
     * no admite más de 255 caracteres escritos en la regla, pero sí un rango de celdas).
     *
     * @param array<string> $categorias
     * @param array<string> $ubicaciones
     * @return array{categorias: ?string, ubicaciones: ?string}
     */
    private static function hojaDeListas(Spreadsheet $libro, array $categorias, array $ubicaciones): array
    {
        $hoja = $libro->createSheet();
        $hoja->setTitle(self::HOJA_LISTAS);
        $hoja->setCellValue('A1', 'Categorías');
        $hoja->setCellValue('B1', 'Ubicaciones');
        foreach ([['A', $categorias], ['B', $ubicaciones]] as [$columna, $valores]) {
            foreach (array_values($valores) as $i => $valor) {
                $hoja->getCell($columna . ($i + 2))->setValueExplicit($valor, DataType::TYPE_STRING);
            }
        }
        $hoja->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);

        $rango = static fn (string $columna, int $cantidad): ?string => $cantidad > 0
            ? "'" . self::HOJA_LISTAS . "'!\${$columna}\$2:\${$columna}\$" . ($cantidad + 1)
            : null;

        return ['categorias' => $rango('A', count($categorias)), 'ubicaciones' => $rango('B', count($ubicaciones))];
    }
}
