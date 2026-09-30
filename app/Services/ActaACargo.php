<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\BinderSinFormulas;
use App\Models\Bien;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * "Acta de bienes a cargo" de un funcionario, en Excel listo para imprimir y firmar (por
 * ejemplo, al entregarle bienes o cuando sale de la institución). Dos secciones:
 *  - Responsabilidad individual: los bienes a su cargo directo.
 *  - Responsabilidad grupal: los bienes de los espacios donde es responsable (y que no
 *    están a cargo de otra persona), con los demás responsables de cada espacio.
 * Mismo criterio de "responde por el bien" que la lista (Bien::sqlRespondePor).
 */
final class ActaACargo
{
    private const COLUMNAS = ['N.º', 'Código', 'Descripción', 'Marca', 'Categoría', 'Espacio', 'Fecha de asignación', 'Valor'];

    /**
     * @return list<array<string, mixed>> los bienes por los que responde, primero los individuales
     */
    public static function bienesDe(int $usuarioId, int $institucionId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT b.codigo_identificacion, b.descripcion, b.marca, c.nombre AS categoria, b.valor, a.fecha_asignacion,
                    CONCAT(e.codigo, ' - ', e.nombre) AS espacio_nombre,
                    IF(a.usuario_responsable_id IS NULL, 'grupal', 'individual') AS tipo,
                    (SELECT GROUP_CONCAT(CONCAT(u.nombres, ' ', u.apellidos) SEPARATOR ', ')
                       FROM espacio_responsables er JOIN usuarios u ON u.id = er.usuario_id
                      WHERE er.espacio_id = a.espacio_id AND er.usuario_id <> ?) AS otros_responsables
               FROM bienes b
               JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
               LEFT JOIN espacios e ON e.id = a.espacio_id
               LEFT JOIN categorias_bienes c ON c.id = b.categoria_id
              WHERE b.institucion_id = ? AND b.estado NOT IN ('reintegrado', 'dado_de_baja')
                AND " . Bien::sqlRespondePor() . "
              ORDER BY (a.usuario_responsable_id IS NULL), e.nombre, b.codigo_identificacion"
        );
        $stmt->execute([$usuarioId, $institucionId, $usuarioId, $usuarioId]);

        return array_values($stmt->fetchAll());
    }

    /**
     * @param array<string, mixed> $persona usuario (nombres, apellidos, documento, cargo_nombre)
     * @param array<string, mixed>|null $rector quien entrega (Usuario::rectorDe)
     */
    public static function generar(array $persona, string $institucion, ?array $rector): Spreadsheet
    {
        $bienes = self::bienesDe((int) $persona['id'], (int) $persona['institucion_id']);
        $individuales = array_values(array_filter($bienes, static fn (array $b): bool => $b['tipo'] === 'individual'));
        $grupales = array_values(array_filter($bienes, static fn (array $b): bool => $b['tipo'] === 'grupal'));
        $nombre = trim($persona['nombres'] . ' ' . $persona['apellidos']);

        $libro = new Spreadsheet();
        $libro->setValueBinder(new BinderSinFormulas());
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Acta de bienes a cargo');
        $hoja->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)->setFitToWidth(1)->setFitToHeight(0);
        $hoja->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.5)->setBottom(0.5);

        $titulo = static function (Worksheet $hoja, int $fila, string $texto, int $tamano = 11): void {
            $hoja->mergeCells("A{$fila}:H{$fila}");
            $hoja->setCellValueExplicit("A{$fila}", $texto, DataType::TYPE_STRING);
            $hoja->getStyle("A{$fila}")->getFont()->setBold(true)->setSize($tamano);
        };

        $titulo($hoja, 1, $institucion, 12);
        $titulo($hoja, 2, 'ACTA DE BIENES A CARGO', 14);
        $hoja->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->mergeCells('A3:D3');
        $hoja->setCellValueExplicit('A3', 'Fecha: ' . date('d/m/Y'), DataType::TYPE_STRING);

        $datos = [
            ['Funcionario', $nombre],
            ['Documento', (string) ($persona['documento'] ?? '')],
            ['Cargo', (string) ($persona['cargo_nombre'] ?? '')],
        ];
        $fila = 5;
        foreach ($datos as [$etiqueta, $valor]) {
            $hoja->setCellValueExplicit("A{$fila}", $etiqueta, DataType::TYPE_STRING);
            $hoja->getStyle("A{$fila}")->getFont()->setBold(true);
            $hoja->mergeCells("B{$fila}:E{$fila}");
            $hoja->setCellValueExplicit("B{$fila}", $valor, DataType::TYPE_STRING);
            $fila++;
        }

        $fila++;
        $fila = self::seccion($hoja, $fila, 'RESPONSABILIDAD INDIVIDUAL: bienes a cargo directo del funcionario', $individuales, false);
        $fila++;
        $fila = self::seccion($hoja, $fila, 'RESPONSABILIDAD GRUPAL: bienes de los espacios donde es responsable', $grupales, true);

        $fila++;
        $hoja->mergeCells("A{$fila}:G{$fila}");
        $hoja->setCellValueExplicit("A{$fila}", 'TOTAL: ' . count($bienes) . ' bien(es)', DataType::TYPE_STRING);
        $hoja->setCellValue("H{$fila}", array_sum(array_map(static fn (array $b): float => (float) $b['valor'], $bienes)));
        $hoja->getStyle("A{$fila}:H{$fila}")->getFont()->setBold(true);
        $hoja->getStyle("H{$fila}")->getNumberFormat()->setFormatCode('"$"#,##0');

        $fila += 2;
        $hoja->mergeCells("A{$fila}:H{$fila}");
        $hoja->setCellValueExplicit("A{$fila}", 'Relación de los bienes por los que responde el funcionario en la fecha de esta acta, según el inventario registrado en MIA.', DataType::TYPE_STRING);
        $hoja->getStyle("A{$fila}")->getAlignment()->setWrapText(true);

        // Firmas.
        $fila += 4;
        foreach ([['A', 'C', 'Entrega', $rector ? trim($rector['nombres'] . ' ' . $rector['apellidos']) : '', 'Rector(a)'],
                  ['E', 'H', 'Recibe', $nombre, 'C.C. ' . ($persona['documento'] ?? '')]] as [$desde, $hasta, $rol, $firmante, $detalle]) {
            $hoja->getStyle("{$desde}{$fila}:{$hasta}{$fila}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
            foreach ([$rol, $firmante, $detalle] as $i => $texto) {
                $f = $fila + $i;
                $hoja->mergeCells("{$desde}{$f}:{$hasta}{$f}");
                $hoja->setCellValueExplicit("{$desde}{$f}", (string) $texto, DataType::TYPE_STRING);
            }
            $hoja->getStyle("{$desde}{$fila}")->getFont()->setBold(true);
        }

        foreach (['A' => 6, 'B' => 16, 'C' => 38, 'D' => 14, 'E' => 16, 'F' => 34, 'G' => 14, 'H' => 14] as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }

        return $libro;
    }

    /**
     * @param list<array<string, mixed>> $bienes
     * @return int la siguiente fila libre
     */
    private static function seccion(Worksheet $hoja, int $fila, string $titulo, array $bienes, bool $grupal): int
    {
        $hoja->mergeCells("A{$fila}:H{$fila}");
        $hoja->setCellValueExplicit("A{$fila}", $titulo, DataType::TYPE_STRING);
        $hoja->getStyle("A{$fila}")->getFont()->setBold(true);
        $fila++;

        $encabezado = self::COLUMNAS;
        $encabezado[5] = $grupal ? 'Espacio (otros responsables)' : 'Guardado en';
        $hoja->fromArray($encabezado, null, "A{$fila}");
        $hoja->getStyle("A{$fila}:H{$fila}")->getFont()->setBold(true);
        $inicio = $fila;
        $fila++;

        if ($bienes === []) {
            $hoja->mergeCells("A{$fila}:H{$fila}");
            $hoja->setCellValueExplicit("A{$fila}", 'Sin bienes en esta sección.', DataType::TYPE_STRING);
            $fila++;
        }

        foreach ($bienes as $i => $b) {
            $espacio = (string) ($b['espacio_nombre'] ?? '');
            if ($grupal && !empty($b['otros_responsables'])) {
                $espacio .= ' (con ' . $b['otros_responsables'] . ')';
            }
            $hoja->setCellValue("A{$fila}", $i + 1);
            $hoja->setCellValueExplicit("B{$fila}", (string) $b['codigo_identificacion'], DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("C{$fila}", (string) $b['descripcion'], DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("D{$fila}", (string) ($b['marca'] ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("E{$fila}", (string) ($b['categoria'] ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("F{$fila}", $espacio !== '' ? $espacio : '—', DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("G{$fila}", (string) $b['fecha_asignacion'], DataType::TYPE_STRING);
            $hoja->setCellValue("H{$fila}", (float) $b['valor']);
            $fila++;
        }

        $hoja->mergeCells("A{$fila}:G{$fila}");
        $hoja->setCellValueExplicit("A{$fila}", 'Subtotal: ' . count($bienes) . ' bien(es)', DataType::TYPE_STRING);
        $hoja->setCellValue("H{$fila}", array_sum(array_map(static fn (array $b): float => (float) $b['valor'], $bienes)));
        $hoja->getStyle("A{$fila}:H{$fila}")->getFont()->setBold(true);
        $hoja->getStyle("H" . ($inicio + 1) . ":H{$fila}")->getNumberFormat()->setFormatCode('"$"#,##0');
        $hoja->getStyle("A{$inicio}:H{$fila}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $hoja->getStyle("C{$inicio}:F{$fila}")->getAlignment()->setWrapText(true);

        return $fila + 1;
    }
}
