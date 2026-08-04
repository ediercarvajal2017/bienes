<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bien;
use App\Models\Movimiento;
use App\Models\Verificacion;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as MedidaDibujo;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ReporteService
{
    public static function carteraBienes(?int $institucionId): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cartera de bienes');

        $sheet->fromArray(
            ['Código', 'Descripción', 'Marca', 'Categoría', 'Ubicación', 'Responsable', 'Estado', 'Valor', 'Fecha de ingreso'],
            null,
            'A1'
        );

        $fila = 2;
        // porPagina = 0: sentinel de Bien::listar() para traer todas las filas sin
        // paginar — un reporte exportado nunca debe quedar truncado a una página.
        foreach (Bien::listar($institucionId, null, 1, 0) as $b) {
            $sheet->fromArray([
                $b['codigo_identificacion'],
                $b['descripcion'],
                $b['marca'],
                $b['categoria_nombre'],
                $b['espacio_nombre'] ?? '',
                $b['responsables_nombres'] ?? '',
                self::etiquetaEstado($b['estado']),
                (float) $b['valor'],
                $b['fecha_ingreso'],
            ], null, "A{$fila}");
            $fila++;
        }

        self::autoajustarColumnas($sheet, 'A', 'I');

        return $spreadsheet;
    }

    public static function planillaReintegros(?int $institucionId): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reintegros pendientes');

        $sheet->fromArray(['Código', 'Descripción', 'Categoría', 'Responsable', 'Ubicación', 'Fecha de asignación', 'Valor'], null, 'A1');

        $fila = 2;
        // Igual que en carteraBienes(): porPagina = 0 trae todas las filas sin paginar.
        foreach (Bien::pendientesDeReintegro($institucionId, null, 1, 0) as $b) {
            $sheet->fromArray([
                $b['codigo_identificacion'],
                $b['descripcion'],
                $b['categoria_nombre'] ?? '',
                $b['responsables_nombres'] ?? '',
                $b['espacio_nombre'] ?? '',
                $b['fecha_asignacion'],
                (float) $b['valor'],
            ], null, "A{$fila}");
            $fila++;
        }

        self::autoajustarColumnas($sheet, 'A', 'G');

        return $spreadsheet;
    }

    public static function historialReintegros(?int $institucionId): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Bienes reintegrados');

        $sheet->fromArray(
            ['Código', 'Descripción', 'Fecha de reintegro', 'Destino', 'Registrado por', 'Observaciones', 'Valor'],
            null,
            'A1'
        );

        $fila = 2;
        foreach (Movimiento::historialReintegros($institucionId) as $m) {
            $sheet->fromArray([
                $m['codigo_identificacion'],
                $m['descripcion'],
                $m['fecha_reintegro'],
                $m['destino_texto'],
                trim($m['responsable_nombres'] . ' ' . $m['responsable_apellidos']),
                $m['observaciones'] ?? '',
                (float) $m['valor'],
            ], null, "A{$fila}");
            $fila++;
        }

        self::autoajustarColumnas($sheet, 'A', 'G');

        return $spreadsheet;
    }

    private const REINTEGRO_BODEGA_NOMBRE = 'BODEGA DE REINTEGRO';
    private const REINTEGRO_BODEGA_DEPENDENCIA = 'BIENES MUEBLES';
    private const REINTEGRO_BODEGA_DOCUMENTO = '111111111';
    private const REINTEGRO_RESOLUCION = 'Resolución número SSS202250105520 DEL 10 DE OCTUBRE DE 2022';
    private const REINTEGRO_BIENES_POR_HOJA = 8;
    private const REINTEGRO_LOGO = __DIR__ . '/../../storage/plantillas/comprobante_reintegro_logo.png';
    private const REINTEGRO_TEXTO_LEGAL = __DIR__ . '/../../storage/plantillas/comprobante_reintegro_legal.png';

    /**
     * Comprobante oficial FO-ADMI-009 "Comprobante traslado y reintegro" (versión 6)
     * que exige la Alcaldía para recibir bienes reintegrados — reproduce ese formato
     * exacto en Excel. El "responsable que entrega" siempre es el rector de la
     * institución (así lo exige la Alcaldía, sin importar quién operó el reintegro
     * en el sistema); el "responsable que recibe" es la bodega de reintegro, un dato
     * fijo que no depende de la institución. Si el lote tiene más bienes de los que
     * caben en un comprobante, se reparten en varias hojas.
     */
    public static function comprobanteReintegroLote(array $lote, array $bienes, array $rector): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $paginas = array_chunk($bienes, self::REINTEGRO_BIENES_POR_HOJA) ?: [[]];
        $totalPaginas = count($paginas);

        foreach ($paginas as $indice => $bienesPagina) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($totalPaginas > 1 ? 'Comprobante ' . ($indice + 1) : 'Comprobante');
            self::dibujarComprobanteReintegro($sheet, $lote, $bienesPagina, $rector);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Inserta una imagen ajustada a un área de celdas (conservando su proporción,
     * para no verla deforme), y la centra dentro de esa área si sobra espacio.
     */
    private static function insertarImagenAjustada(
        Worksheet $sheet,
        string $ruta,
        string $celda,
        array $columnas,
        array $filas,
        int $anchoNatural,
        int $altoNatural
    ): void {
        if (!is_file($ruta)) {
            return;
        }

        $anchoDisponible = self::anchoColumnasPx($sheet, $columnas);
        $altoDisponible = self::altoFilasPx($sheet, $filas);

        $escala = min($anchoDisponible / $anchoNatural, $altoDisponible / $altoNatural);
        $ancho = (int) round($anchoNatural * $escala);
        $alto = (int) round($altoNatural * $escala);

        $dibujo = new Drawing();
        $dibujo->setPath($ruta);
        $dibujo->setResizeProportional(false);
        $dibujo->setCoordinates($celda);
        $dibujo->setWidth($ancho);
        $dibujo->setHeight($alto);
        $dibujo->setOffsetX((int) round(($anchoDisponible - $ancho) / 2));
        $dibujo->setOffsetY((int) round(($altoDisponible - $alto) / 2));
        $dibujo->setWorksheet($sheet);
    }

    /**
     * Inserta una imagen al ancho exacto de un rango de columnas (p. ej. el ancho
     * completo de la tabla de bienes), conservando su proporción original.
     */
    private static function insertarImagenAlAncho(
        Worksheet $sheet,
        string $ruta,
        string $celda,
        array $columnas,
        int $anchoNatural,
        int $altoNatural
    ): void {
        if (!is_file($ruta)) {
            return;
        }

        $ancho = self::anchoColumnasPx($sheet, $columnas);
        $alto = (int) round($altoNatural * ($ancho / $anchoNatural));

        $dibujo = new Drawing();
        $dibujo->setPath($ruta);
        $dibujo->setResizeProportional(false);
        $dibujo->setCoordinates($celda);
        $dibujo->setWidth($ancho);
        $dibujo->setHeight($alto);
        $dibujo->setWorksheet($sheet);
    }

    private static function anchoColumnasPx(Worksheet $sheet, array $columnas): int
    {
        $fuente = new Font();
        $total = 0;
        foreach ($columnas as $columna) {
            $total += MedidaDibujo::cellDimensionToPixels((float) $sheet->getColumnDimension($columna)->getWidth(), $fuente);
        }

        return $total;
    }

    private static function altoFilasPx(Worksheet $sheet, array $filas): int
    {
        $total = 0;
        foreach ($filas as $fila) {
            $total += MedidaDibujo::pointsToPixels($sheet->getRowDimension($fila)->getRowHeight());
        }

        return $total;
    }

    private static function dibujarComprobanteReintegro(Worksheet $sheet, array $lote, array $bienes, array $rector): void
    {
        foreach (['A', 'B'] as $columna) {
            $sheet->getColumnDimension($columna)->setWidth(11);
        }
        $sheet->getColumnDimension('C')->setWidth(8);
        foreach (['D', 'E', 'F', 'G'] as $columna) {
            $sheet->getColumnDimension($columna)->setWidth(13);
        }
        foreach (['H', 'I', 'J', 'K'] as $columna) {
            $sheet->getColumnDimension($columna)->setWidth(11);
        }
        // Alto explícito de las filas del logo (2 a 4): sin esto, PhpSpreadsheet no
        // reporta un alto de fila (usa -1) y no se puede calcular el área disponible
        // para ajustar el logo a la celda.
        $sheet->getRowDimension(2)->setRowHeight(15);
        $sheet->getRowDimension(3)->setRowHeight(15);
        $sheet->getRowDimension(4)->setRowHeight(15.75);

        $negrita = ['font' => ['bold' => true]];
        $centrado = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]];
        $bordeFino = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
        $grisEtiqueta = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C0C0C0']]];

        $sheet->mergeCells('A2:B2')->setCellValue('A2', 'Cód. FO-ADMI-009');
        $sheet->mergeCells('C2:I2')->setCellValue('C2', 'Formato');
        $sheet->mergeCells('J2:K4');
        $sheet->mergeCells('A3:B4')->setCellValue('A3', 'Versión.6');
        $sheet->mergeCells('C3:I4')->setCellValue('C3', 'FO-ADMI Comprobante traslado y reintegro');
        $sheet->mergeCells('A5:K5');

        $sheet->mergeCells('A6:A7')->setCellValue('A6', 'COMPROBANTE');
        $sheet->mergeCells('B6:C7');
        $sheet->mergeCells('D6:D7')->setCellValue('D6', ' TRASLADO');
        $sheet->mergeCells('E6:E7');
        $sheet->mergeCells('F6:F7')->setCellValue('F6', 'REINTEGRO');
        $sheet->mergeCells('G6:G7')->setCellValue('G6', 'X');
        $sheet->mergeCells('H6:H7')->setCellValue('H6', 'FECHA');
        $sheet->setCellValue('I6', 'DÍA');
        $sheet->setCellValue('J6', 'MES');
        $sheet->setCellValue('K6', 'AÑO');

        [$anio, $mes, $dia] = array_pad(explode('-', (string) $lote['fecha']), 3, '');
        $sheet->setCellValue('I7', (int) $dia);
        $sheet->setCellValue('J7', (int) $mes);
        $sheet->setCellValue('K7', (int) $anio);

        // Responsable que entrega: siempre el rector, dato exigido por la Alcaldía.
        $sheet->mergeCells('A8:C8')->setCellValue('A8', 'RESPONSABLE QUE ENTREGA');
        $sheet->mergeCells('D8:G8')->setCellValue('D8', 'DEPENDENCIA');
        $sheet->mergeCells('H8:K8')->setCellValue('H8', 'DOC. DE IDENTIDAD');
        $sheet->mergeCells('A9:C10')->setCellValue('A9', trim($rector['nombres'] . ' ' . $rector['apellidos']));
        $sheet->mergeCells('D9:G10')->setCellValue('D9', $lote['institucion_nombre']);
        $sheet->mergeCells('H9:K10')->setCellValueExplicit('H9', $rector['documento'], DataType::TYPE_STRING);

        // Responsable que recibe: la bodega de reintegro, dato fijo que no depende
        // de la institución.
        $sheet->mergeCells('A11:C11')->setCellValue('A11', 'RESPONSABLE QUE RECIBE');
        $sheet->mergeCells('D11:G11')->setCellValue('D11', 'DEPENDENCIA');
        $sheet->mergeCells('H11:K11')->setCellValue('H11', 'DOC. DE IDENTIDAD');
        $sheet->mergeCells('A12:C13')->setCellValue('A12', self::REINTEGRO_BODEGA_NOMBRE);
        $sheet->mergeCells('D12:G13')->setCellValue('D12', self::REINTEGRO_BODEGA_DEPENDENCIA);
        $sheet->mergeCells('H12:K13')->setCellValueExplicit('H12', self::REINTEGRO_BODEGA_DOCUMENTO, DataType::TYPE_STRING);

        $sheet->mergeCells('A14:B14')->setCellValue('A14', 'PLACA');
        $sheet->setCellValue('C14', 'CANT.');
        $sheet->mergeCells('D14:G14')->setCellValue('D14', 'ARTÍCULO');
        $sheet->mergeCells('H14:K14')->setCellValue('H14', 'MARCA');

        $fila = 15;
        foreach ($bienes as $bien) {
            $sheet->mergeCells("A{$fila}:B{$fila}")->setCellValueExplicit("A{$fila}", $bien['codigo_identificacion'], DataType::TYPE_STRING);
            $sheet->setCellValue("C{$fila}", 1);
            $sheet->mergeCells("D{$fila}:G{$fila}")->setCellValue("D{$fila}", $bien['descripcion']);
            $sheet->mergeCells("H{$fila}:K{$fila}")->setCellValue("H{$fila}", $bien['marca'] ?? '');
            $fila++;
        }
        // Filas vacías hasta completar la plantilla (siempre 8 renglones, como el
        // formato oficial impreso), aunque el lote tenga menos bienes.
        for (; $fila <= 22; $fila++) {
            $sheet->mergeCells("A{$fila}:B{$fila}");
            $sheet->mergeCells("D{$fila}:G{$fila}");
            $sheet->mergeCells("H{$fila}:K{$fila}");
        }

        $sheet->setCellValue('A23', 'Observaciones:');
        $observaciones = trim(($lote['observaciones'] ?? '') . (!empty($lote['destino_texto']) ? ' Destino: ' . $lote['destino_texto'] : ''));
        $sheet->mergeCells('B23:K23')->setCellValue('B23', $observaciones);
        $sheet->mergeCells('A24:K24');

        $sheet->mergeCells('A25:C25')->setCellValue('A25', 'RESP. QUE ENTREGA');
        $sheet->mergeCells('D25:E25')->setCellValue('D25', 'JEFE DEP. ENTREGA');
        $sheet->setCellValue('F25', 'U. BIENES MUEBLES');
        $sheet->mergeCells('G25:I25')->setCellValue('G25', 'RESP. QUE RECIBE');
        $sheet->mergeCells('J25:K25')->setCellValue('J25', 'JEFE. DEP. RECIBE');
        $sheet->mergeCells('A26:C27');
        $sheet->mergeCells('D26:E28');
        $sheet->mergeCells('F26:F28');
        $sheet->mergeCells('G26:I27');
        $sheet->mergeCells('J26:K28');
        $sheet->setCellValue('A28', 'C.C.');
        $sheet->mergeCells('B28:C28')->setCellValueExplicit('B28', $rector['documento'], DataType::TYPE_STRING);
        $sheet->setCellValue('G28', 'C.C.');
        $sheet->mergeCells('H28:I28');

        $sheet->mergeCells('A29:K29');
        $sheet->mergeCells('A30:K30');
        $sheet->mergeCells('A31:K31')->setCellValue('A31', self::REINTEGRO_RESOLUCION);

        $sheet->getStyle('A2:K4')->applyFromArray($negrita);
        $sheet->getStyle('A2:K4')->applyFromArray($centrado);
        $sheet->getStyle('A2:K4')->applyFromArray($bordeFino);
        $sheet->getStyle('A6:K7')->applyFromArray($negrita);
        $sheet->getStyle('A6:K7')->applyFromArray($centrado);
        $sheet->getStyle('A8:K8')->applyFromArray($negrita);
        $sheet->getStyle('A11:K11')->applyFromArray($negrita);
        $sheet->getStyle('A14:K14')->applyFromArray($negrita);
        $sheet->getStyle('A14:K14')->applyFromArray($centrado);
        $sheet->getStyle('C15:C22')->applyFromArray($centrado);
        $sheet->getStyle('A25:K25')->applyFromArray($negrita);
        $sheet->getStyle('A6:K22')->applyFromArray($bordeFino);

        // Relleno gris: igual que en el formato original, solo en las etiquetas de
        // encabezado, nunca en las celdas donde se escribe un dato.
        foreach (['A6:A7', 'D6:D7', 'F6:F7', 'H6:H7', 'I6', 'J6', 'K6', 'A8:K8', 'A11:K11', 'A14:K14'] as $rango) {
            $sheet->getStyle($rango)->applyFromArray($grisEtiqueta);
        }

        self::insertarImagenAjustada($sheet, self::REINTEGRO_LOGO, 'J2', ['J', 'K'], [2, 3, 4], 162, 61);
        self::insertarImagenAlAncho($sheet, self::REINTEGRO_TEXTO_LEGAL, 'A33', ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'], 832, 364);
        $sheet->getStyle('A25:K28')->applyFromArray($bordeFino);

        // Página lista para imprimir tal como en el formato original de la Alcaldía,
        // sin que quien la descargue tenga que configurar nada a mano: horizontal,
        // tamaño A4 y los mismos márgenes del archivo oficial.
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $pageSetup->setFitToPage(false);
        $pageSetup->setScale(100);

        $margenes = $sheet->getPageMargins();
        $margenes->setTop(0.98425196850394);
        $margenes->setBottom(0.15748031496063);
        $margenes->setLeft(0.51181102362205);
        $margenes->setRight(0.70866141732283);
        $margenes->setHeader(0.31496062992126);
        $margenes->setFooter(0.31496062992126);
    }

    /**
     * Consolidado completo de una jornada de verificación, en 3 hojas — sin paginar ni
     * filtrar (a diferencia de la pantalla en pantalla, que sí pagina/filtra para no
     * saturar la vista de trabajo diaria): "Sin novedad", "Discrepancias" (con su estado
     * de revisión y quién/cuándo la atendió) y "Pendientes". Este archivo es el respaldo
     * que se puede archivar o compartir con quien pida cuentas de la jornada.
     */
    public static function jornadaVerificacion(array $jornada, int $institucionId): Spreadsheet
    {
        $jornadaId = (int) $jornada['id'];
        $spreadsheet = new Spreadsheet();

        $sheetOk = $spreadsheet->getActiveSheet();
        $sheetOk->setTitle('Sin novedad');
        self::encabezadoJornada($sheetOk, $jornada);

        $fila = 5;
        $sheetOk->fromArray(['Código', 'Descripción', 'Ubicación', 'Responsable(s)', 'Verificado por', 'Fecha'], null, "A{$fila}");
        $fila++;
        foreach (Verificacion::listarPorResultado($jornadaId, 'ok', null, 1, 0) as $v) {
            $sheetOk->fromArray([
                $v['codigo_identificacion'],
                $v['descripcion'],
                $v['espacio_nombre'] ?? '',
                $v['responsables_nombres'] ?? '',
                trim($v['nombres'] . ' ' . $v['apellidos']),
                substr($v['updated_at'], 0, 16),
            ], null, "A{$fila}");
            $fila++;
        }
        self::autoajustarColumnas($sheetOk, 'A', 'F');

        $sheetDiscrepancias = $spreadsheet->createSheet();
        $sheetDiscrepancias->setTitle('Discrepancias');
        $sheetDiscrepancias->fromArray(
            ['Código', 'Descripción', 'Ubicación', 'Responsable(s)', 'Motivo', 'Observación', 'Reportado por', 'Fecha reporte', 'Estado', 'Revisado por', 'Fecha revisión'],
            null,
            'A1'
        );
        $fila = 2;
        // revisada = null: trae TODAS (atendidas y sin atender), a diferencia de la
        // pantalla, que por defecto solo muestra las pendientes.
        foreach (Verificacion::listarPorResultado($jornadaId, 'discrepancia', null, 1, 0, null) as $d) {
            $sheetDiscrepancias->fromArray([
                $d['codigo_identificacion'],
                $d['descripcion'],
                $d['espacio_nombre'] ?? '',
                $d['responsables_nombres'] ?? '',
                Verificacion::etiquetaMotivo($d['motivo'] ?? null),
                $d['observaciones'] ?? '',
                trim($d['nombres'] . ' ' . $d['apellidos']),
                substr($d['updated_at'], 0, 16),
                empty($d['revisada']) ? 'Sin atender' : 'Revisada',
                !empty($d['revisor_nombres']) ? trim($d['revisor_nombres'] . ' ' . $d['revisor_apellidos']) : '',
                !empty($d['revisada_en']) ? substr($d['revisada_en'], 0, 16) : '',
            ], null, "A{$fila}");
            $fila++;
        }
        self::autoajustarColumnas($sheetDiscrepancias, 'A', 'K');

        $sheetPendientes = $spreadsheet->createSheet();
        $sheetPendientes->setTitle('Pendientes');
        $sheetPendientes->fromArray(['Código', 'Descripción', 'Ubicación'], null, 'A1');
        $fila = 2;
        foreach (Verificacion::listarPendientes($jornadaId, $institucionId, null, 1, 0) as $p) {
            $sheetPendientes->fromArray([
                $p['codigo_identificacion'],
                $p['descripcion'],
                $p['espacio_nombre'] ?? '',
            ], null, "A{$fila}");
            $fila++;
        }
        self::autoajustarColumnas($sheetPendientes, 'A', 'C');

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private static function encabezadoJornada(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $jornada): void
    {
        $sheet->setCellValue('A1', 'Jornada: ' . $jornada['nombre']);
        $sheet->setCellValue('A2', 'Iniciada: ' . $jornada['fecha_inicio']);
        $sheet->setCellValue('A3', $jornada['estado'] === 'cerrada'
            ? 'Cerrada: ' . substr((string) $jornada['fecha_cierre'], 0, 16)
            : 'En progreso');
    }

    public static function enviarXlsx(Spreadsheet $spreadsheet, string $nombreArchivo): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    public static function enviarCsv(Spreadsheet $spreadsheet, string $nombreArchivo): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
        header('Cache-Control: max-age=0');
        $writer = new Csv($spreadsheet);
        $writer->setDelimiter(',');
        $writer->save('php://output');
        exit;
    }

    private static function autoajustarColumnas(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $desde, string $hasta): void
    {
        foreach (range($desde, $hasta) as $columna) {
            $sheet->getColumnDimension($columna)->setAutoSize(true);
        }
    }

    private static function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            'activo' => 'Activo',
            'reintegrado' => 'Reintegrado',
            'en_reparacion' => 'En reparación',
            'dado_de_baja' => 'Dado de baja',
            default => $estado,
        };
    }
}
