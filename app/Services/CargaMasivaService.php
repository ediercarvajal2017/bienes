<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bien;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class CargaMasivaService
{
    /**
     * Lee un .xlsx (columnas A-E: Código, Descripción, Marca, Fecha_Ingreso, Valor) y compara
     * cada fila contra la base de datos de la institución. No escribe nada todavía.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function analizar(string $rutaArchivo, int $institucionId): array
    {
        $sheet = IOFactory::load($rutaArchivo)->getActiveSheet();
        $filas = [];
        $codigosVistos = [];

        $ultimaFila = $sheet->getHighestDataRow();

        for ($numeroFila = 2; $numeroFila <= $ultimaFila; $numeroFila++) {
            $codigo = trim((string) $sheet->getCell("A{$numeroFila}")->getValue());
            $descripcion = trim((string) $sheet->getCell("B{$numeroFila}")->getValue());
            $marca = trim((string) $sheet->getCell("C{$numeroFila}")->getValue());

            if ($codigo === '' && $descripcion === '') {
                continue;
            }

            if ($codigo === '' || $descripcion === '') {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => 'Falta código o descripción', 'codigo' => $codigo, 'descripcion' => $descripcion,
                ];
                continue;
            }

            if (isset($codigosVistos[$codigo])) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => "Código repetido dentro del mismo archivo (ya aparece en la fila {$codigosVistos[$codigo]})",
                    'codigo' => $codigo, 'descripcion' => $descripcion,
                ];
                continue;
            }
            $codigosVistos[$codigo] = $numeroFila;

            $fecha = self::leerFecha($sheet->getCell("D{$numeroFila}"));
            if ($fecha === null) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => 'Fecha de ingreso inválida', 'codigo' => $codigo, 'descripcion' => $descripcion,
                ];
                continue;
            }

            $valor = self::leerValor($sheet->getCell("E{$numeroFila}"));

            $datos = [
                'codigo_identificacion' => $codigo,
                'descripcion' => $descripcion,
                'marca' => $marca !== '' ? $marca : null,
                'fecha_ingreso' => $fecha,
                'valor' => $valor,
            ];

            $existente = Bien::buscarPorCodigoInstitucion($institucionId, $codigo);

            if (!$existente) {
                $filas[] = ['fila' => $numeroFila, 'tipo' => 'nuevo', 'datos' => $datos];
                continue;
            }

            $cambios = self::detectarCambios($existente, $datos);

            $filas[] = $cambios === []
                ? ['fila' => $numeroFila, 'tipo' => 'sin_cambios', 'datos' => $datos, 'bien_id' => $existente['id']]
                : ['fila' => $numeroFila, 'tipo' => 'modificado', 'datos' => $datos, 'bien_id' => $existente['id'], 'cambios' => $cambios];
        }

        return $filas;
    }

    /**
     * Aplica los cambios a la BD. Devuelve cuántas filas se omitieron por un choque de
     * código detectado justo al escribir (p. ej. si alguien creó ese mismo código por otra
     * vía entre que se analizó el archivo y se confirmó la carga) — para que ninguna fila
     * conflictiva tumbe el resto de la operación con un error fatal.
     */
    public static function aplicar(array $filas, int $institucionId, int $usuarioId): int
    {
        $omitidas = 0;

        foreach ($filas as $fila) {
            try {
                if ($fila['tipo'] === 'nuevo') {
                    Bien::create([
                        'institucion_id' => $institucionId,
                        'codigo_identificacion' => $fila['datos']['codigo_identificacion'],
                        'descripcion' => $fila['datos']['descripcion'],
                        'marca' => $fila['datos']['marca'],
                        'categoria_id' => null,
                        'fecha_ingreso' => $fila['datos']['fecha_ingreso'],
                        'valor' => $fila['datos']['valor'],
                        'tiene_factura' => 0,
                        'estado' => 'activo',
                        'created_by' => $usuarioId,
                    ]);
                    continue;
                }

                if ($fila['tipo'] === 'modificado') {
                    $bien = Bien::find($fila['bien_id']);
                    Bien::update($fila['bien_id'], [
                        'codigo_identificacion' => $fila['datos']['codigo_identificacion'],
                        'descripcion' => $fila['datos']['descripcion'],
                        'marca' => $fila['datos']['marca'],
                        'categoria_id' => $bien['categoria_id'],
                        'fecha_ingreso' => $fila['datos']['fecha_ingreso'],
                        'valor' => $fila['datos']['valor'],
                        'tiene_factura' => $bien['tiene_factura'],
                        'estado' => $bien['estado'],
                    ]);
                }
            } catch (\PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $omitidas++;
            }
        }

        return $omitidas;
    }

    public static function enviarPlantilla(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Codigo', 'Descripcion', 'Marca', 'Fecha_Ingreso', 'Valor'], null, 'A1');
        $sheet->fromArray(['BIEN-0001', 'Silla plástica azul', 'Rimax', '2026-01-15', 85000], null, 'A2');
        foreach (range('A', 'E') as $columna) {
            $sheet->getColumnDimension($columna)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="plantilla_carga_bienes.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    private static function detectarCambios(array $existente, array $nuevo): array
    {
        $cambios = [];
        $comparar = [
            'descripcion' => 'Descripción',
            'marca' => 'Marca',
            'fecha_ingreso' => 'Fecha de ingreso',
            'valor' => 'Valor',
        ];

        foreach ($comparar as $campo => $etiqueta) {
            $actual = $existente[$campo];
            $propuesto = $nuevo[$campo];

            if ($campo === 'valor') {
                if (abs((float) $actual - (float) $propuesto) > 0.01) {
                    $cambios[$etiqueta] = ['antes' => $actual, 'despues' => $propuesto];
                }
                continue;
            }

            $actualTexto = trim((string) ($actual ?? ''));
            $propuestoTexto = trim((string) ($propuesto ?? ''));

            if ($actualTexto !== $propuestoTexto) {
                $cambios[$etiqueta] = ['antes' => $actualTexto, 'despues' => $propuestoTexto];
            }
        }

        return $cambios;
    }

    private static function leerFecha(\PhpOffice\PhpSpreadsheet\Cell\Cell $celda): ?string
    {
        $valor = $celda->getValue();

        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor) && FechaExcel::isDateTimeFormatCode($celda->getStyle()->getNumberFormat()->getFormatCode())) {
            $fecha = FechaExcel::excelToDateTimeObject($valor);

            return $fecha->format('Y-m-d');
        }

        $texto = trim((string) $valor);
        $fecha = \DateTime::createFromFormat('Y-m-d', $texto);
        if ($fecha instanceof \DateTime) {
            return $fecha->format('Y-m-d');
        }

        $marca = strtotime($texto);

        return $marca !== false ? date('Y-m-d', $marca) : null;
    }

    private static function leerValor(\PhpOffice\PhpSpreadsheet\Cell\Cell $celda): float
    {
        $valor = $celda->getValue();

        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $limpio = preg_replace('/[^0-9,.\-]/', '', (string) $valor) ?? '';

        // Formato colombiano "1.500.000,50" -> quitar puntos de miles y usar coma como decimal
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $limpio)) {
            $limpio = str_replace('.', '', $limpio);
            $limpio = str_replace(',', '.', $limpio);
        } else {
            $limpio = str_replace(',', '', $limpio);
        }

        return is_numeric($limpio) ? (float) $limpio : 0.0;
    }
}
