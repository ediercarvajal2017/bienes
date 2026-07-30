<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bien;
use App\Models\Movimiento;
use App\Models\Verificacion;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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

    /**
     * "Formato de reintegro" oficial de un lote: encabezado con los datos del lote,
     * el detalle de bienes devueltos y líneas de firma al final. Reemplaza la carga
     * manual de un PDF firmado — el sistema lo genera automáticamente en Excel.
     */
    public static function formatoReintegroLote(array $lote, array $bienes): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Formato de reintegro');

        $sheet->setCellValue('A1', 'FORMATO DE REINTEGRO DE BIENES');
        $sheet->setCellValue('A2', 'Institución:');
        $sheet->setCellValue('B2', $lote['institucion_nombre']);
        $sheet->setCellValue('A3', 'Fecha del lote:');
        $sheet->setCellValue('B3', $lote['fecha']);
        $sheet->setCellValue('A4', 'Descripción del lote:');
        $sheet->setCellValue('B4', $lote['destino_texto'] ?? '');
        $sheet->setCellValue('A5', 'Registrado por:');
        $sheet->setCellValue('B5', trim($lote['registrado_por_nombres'] . ' ' . $lote['registrado_por_apellidos']));
        $sheet->setCellValue('A6', 'Observaciones:');
        $sheet->setCellValue('B6', $lote['observaciones'] ?? '');

        $fila = 8;
        $sheet->fromArray(['Código', 'Descripción', 'Fecha de reintegro', 'Destino', 'Espacio de origen', 'Valor'], null, "A{$fila}");
        $fila++;

        foreach ($bienes as $b) {
            $sheet->fromArray([
                $b['codigo_identificacion'],
                $b['descripcion'],
                $b['fecha_reintegro'],
                $b['destino_texto'],
                $b['espacio_origen_nombre'] ?? '—',
                (float) $b['valor'],
            ], null, "A{$fila}");
            $fila++;
        }

        $fila += 2;
        $sheet->setCellValue("A{$fila}", 'Firma de quien entrega: ____________________________');
        $fila++;
        $sheet->setCellValue("A{$fila}", 'Firma de quien recibe: ____________________________');

        self::autoajustarColumnas($sheet, 'A', 'F');

        return $spreadsheet;
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
