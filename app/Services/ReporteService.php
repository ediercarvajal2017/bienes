<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bien;
use App\Models\Movimiento;
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

        $sheet->fromArray(['Código', 'Descripción', 'Responsable', 'Ubicación', 'Fecha de asignación', 'Valor'], null, 'A1');

        $fila = 2;
        // Igual que en carteraBienes(): porPagina = 0 trae todas las filas sin paginar.
        foreach (Bien::pendientesDeReintegro($institucionId, null, 1, 0) as $b) {
            $sheet->fromArray([
                $b['codigo_identificacion'],
                $b['descripcion'],
                $b['responsables_nombres'] ?? '',
                $b['espacio_nombre'] ?? '',
                $b['fecha_asignacion'],
                (float) $b['valor'],
            ], null, "A{$fila}");
            $fila++;
        }

        self::autoajustarColumnas($sheet, 'A', 'F');

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
