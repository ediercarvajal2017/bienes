<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asignacion;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Espacio;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class CargaMasivaService
{
    /**
     * Lee un .xlsx (columnas A-G: Código, Descripción, Marca, Fecha_Ingreso, Valor, Ubicación,
     * Categoría) y compara cada fila contra la base de datos de la institución. No escribe nada
     * todavía.
     *
     * Fecha_Ingreso es opcional: si se deja en blanco, se usa la fecha de hoy para un bien
     * nuevo, o se conserva la que ya tenía el bien si es una actualización. Ubicación (opcional)
     * es el código de un espacio ya existente en la institución; si se indica y no existe, la
     * fila se marca inválida en vez de crear un espacio nuevo a ciegas. Categoría (opcional)
     * sigue el mismo criterio: debe coincidir con el NOMBRE de una categoría activa ya
     * existente, o la fila se marca inválida — nunca crea una categoría nueva a ciegas.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function analizar(string $rutaArchivo, int $institucionId): array
    {
        $sheet = IOFactory::load($rutaArchivo)->getActiveSheet();
        $filas = [];
        $codigosVistos = [];
        $espaciosPorCodigo = self::mapaEspaciosPorCodigo($institucionId);
        $categoriasPorNombre = self::mapaCategoriasPorNombre($institucionId);
        $categoriasPorId = array_column(Categoria::all($institucionId), 'nombre', 'id');

        $ultimaFila = $sheet->getHighestDataRow();

        for ($numeroFila = 2; $numeroFila <= $ultimaFila; $numeroFila++) {
            $codigo = trim((string) $sheet->getCell("A{$numeroFila}")->getValue());
            $descripcion = trim((string) $sheet->getCell("B{$numeroFila}")->getValue());
            $marca = trim((string) $sheet->getCell("C{$numeroFila}")->getValue());
            $ubicacion = trim((string) $sheet->getCell("F{$numeroFila}")->getValue());
            $categoriaTexto = trim((string) $sheet->getCell("G{$numeroFila}")->getValue());

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

            $celdaFecha = $sheet->getCell("D{$numeroFila}");
            $fechaCruda = trim((string) $celdaFecha->getValue());
            $fecha = null;
            if ($fechaCruda !== '') {
                $fecha = self::leerFecha($celdaFecha);
                if ($fecha === null) {
                    $filas[] = [
                        'fila' => $numeroFila, 'tipo' => 'invalido',
                        'motivo' => 'Fecha de ingreso inválida', 'codigo' => $codigo, 'descripcion' => $descripcion,
                    ];
                    continue;
                }
            }

            $valor = self::leerValor($sheet->getCell("E{$numeroFila}"));

            $espacioId = null;
            if ($ubicacion !== '') {
                $espacioId = $espaciosPorCodigo[self::normalizarCodigoEspacio($ubicacion)] ?? null;
                if ($espacioId === null) {
                    $filas[] = [
                        'fila' => $numeroFila, 'tipo' => 'invalido',
                        'motivo' => "Ubicación no encontrada: no existe un espacio con código \"{$ubicacion}\"",
                        'codigo' => $codigo, 'descripcion' => $descripcion,
                    ];
                    continue;
                }
            }

            $categoriaId = null;
            if ($categoriaTexto !== '') {
                $categoriaId = $categoriasPorNombre[self::normalizarNombreCategoria($categoriaTexto)] ?? null;
                if ($categoriaId === null) {
                    $filas[] = [
                        'fila' => $numeroFila, 'tipo' => 'invalido',
                        'motivo' => "Categoría no encontrada: no existe una categoría activa llamada \"{$categoriaTexto}\"",
                        'codigo' => $codigo, 'descripcion' => $descripcion,
                    ];
                    continue;
                }
            }

            $existente = Bien::buscarPorCodigoInstitucion($institucionId, $codigo);

            if ($fecha === null) {
                $fecha = $existente ? $existente['fecha_ingreso'] : date('Y-m-d');
            }

            // Categoría en blanco: para un bien nuevo queda sin categoría, para uno existente
            // se conserva la que ya tenía — mismo criterio que Fecha_Ingreso más arriba.
            if ($categoriaTexto === '') {
                $categoriaId = ($existente && $existente['categoria_id'] !== null) ? (int) $existente['categoria_id'] : null;
            }

            $datos = [
                'codigo_identificacion' => $codigo,
                'descripcion' => $descripcion,
                'marca' => $marca !== '' ? $marca : null,
                'fecha_ingreso' => $fecha,
                'valor' => $valor,
                'espacio_id' => $espacioId,
                'ubicacion_texto' => $ubicacion !== '' ? $ubicacion : null,
                'categoria_id' => $categoriaId,
            ];

            if (!$existente) {
                $filas[] = ['fila' => $numeroFila, 'tipo' => 'nuevo', 'datos' => $datos];
                continue;
            }

            $asignacionActiva = $espacioId !== null ? Asignacion::activaDe((int) $existente['id']) : null;
            $cambios = self::detectarCambios($existente, $datos, $asignacionActiva, $categoriasPorId);

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
                    $bienId = Bien::create([
                        'institucion_id' => $institucionId,
                        'codigo_identificacion' => $fila['datos']['codigo_identificacion'],
                        'descripcion' => $fila['datos']['descripcion'],
                        'marca' => $fila['datos']['marca'],
                        'categoria_id' => $fila['datos']['categoria_id'],
                        'fecha_ingreso' => $fila['datos']['fecha_ingreso'],
                        'valor' => $fila['datos']['valor'],
                        'tiene_factura' => 0,
                        'estado' => 'activo',
                        'created_by' => $usuarioId,
                    ]);

                    if ($fila['datos']['espacio_id'] !== null) {
                        Asignacion::crear([
                            'bien_id' => $bienId,
                            'espacio_id' => $fila['datos']['espacio_id'],
                            'fecha_asignacion' => date('Y-m-d'),
                            'observaciones' => null,
                            'asignado_por' => $usuarioId,
                        ]);
                    }
                    continue;
                }

                if ($fila['tipo'] === 'modificado') {
                    $bien = Bien::find($fila['bien_id']);
                    Bien::update($fila['bien_id'], [
                        'codigo_identificacion' => $fila['datos']['codigo_identificacion'],
                        'descripcion' => $fila['datos']['descripcion'],
                        'marca' => $fila['datos']['marca'],
                        'categoria_id' => $fila['datos']['categoria_id'],
                        'fecha_ingreso' => $fila['datos']['fecha_ingreso'],
                        'valor' => $fila['datos']['valor'],
                        'tiene_factura' => $bien['tiene_factura'],
                        'estado' => $bien['estado'],
                    ]);

                    if ($fila['datos']['espacio_id'] !== null && isset($fila['cambios']['Ubicación'])) {
                        Asignacion::cerrarActivasDe($fila['bien_id']);
                        Asignacion::crear([
                            'bien_id' => $fila['bien_id'],
                            'espacio_id' => $fila['datos']['espacio_id'],
                            'fecha_asignacion' => date('Y-m-d'),
                            'observaciones' => 'Actualizado por carga masiva',
                            'asignado_por' => $usuarioId,
                        ]);
                    }
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
        $sheet->fromArray(['Codigo', 'Descripcion', 'Marca', 'Fecha_Ingreso', 'Valor', 'Ubicacion', 'Categoria'], null, 'A1');
        $sheet->fromArray(['BIEN-0001', 'Silla plástica azul', 'Rimax', '2026-01-15', 85000, 'AULA-101', 'Sillas'], null, 'A2');
        foreach (range('A', 'G') as $columna) {
            $sheet->getColumnDimension($columna)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="plantilla_carga_bienes.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    private static function detectarCambios(array $existente, array $nuevo, ?array $asignacionActiva, array $categoriasPorId): array
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

        $categoriaActualId = $existente['categoria_id'] !== null ? (int) $existente['categoria_id'] : null;
        $categoriaNuevaId = $nuevo['categoria_id'];
        if ($categoriaActualId !== $categoriaNuevaId) {
            $cambios['Categoría'] = [
                'antes' => $categoriaActualId !== null ? ($categoriasPorId[$categoriaActualId] ?? '—') : 'Sin categoría',
                'despues' => $categoriaNuevaId !== null ? ($categoriasPorId[$categoriaNuevaId] ?? '—') : 'Sin categoría',
            ];
        }

        if ($nuevo['espacio_id'] !== null) {
            $espacioActualId = $asignacionActiva['espacio_id'] ?? null;
            if ((int) $nuevo['espacio_id'] !== (int) ($espacioActualId ?? 0)) {
                $cambios['Ubicación'] = [
                    'antes' => $asignacionActiva['espacio_nombre'] ?? 'Sin asignar',
                    'despues' => $nuevo['ubicacion_texto'],
                ];
            }
        }

        return $cambios;
    }

    /**
     * Código de espacio -> id, para toda la institución, comparando por código
     * normalizado (ver normalizarCodigoEspacio) en vez de una consulta exacta por fila.
     * Evita hasta miles de consultas en un archivo grande y, de paso, hace que la
     * ubicación coincida aunque el Excel traiga espacios de más o un carácter de
     * espacio "no separable" (muy común al pegar datos desde Word/PDF).
     */
    private static function mapaEspaciosPorCodigo(int $institucionId): array
    {
        $mapa = [];
        foreach (Espacio::listadoCodigos($institucionId) as $espacio) {
            $mapa[self::normalizarCodigoEspacio($espacio['codigo'])] = (int) $espacio['id'];
        }

        return $mapa;
    }

    private static function normalizarCodigoEspacio(string $texto): string
    {
        $texto = str_replace("\xC2\xA0", ' ', $texto); // espacio "no separable" (NBSP)
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        return mb_strtoupper(trim($texto));
    }

    /**
     * Nombre de categoría -> id, solo activas (una categoría inactiva no se puede asignar
     * desde el formulario normal de un bien tampoco, así que la carga masiva respeta la
     * misma regla). Comparación normalizada igual que mapaEspaciosPorCodigo(), por el mismo
     * motivo: tolerar espacios de más o un NBSP pegado desde Word/PDF.
     */
    private static function mapaCategoriasPorNombre(int $institucionId): array
    {
        $mapa = [];
        foreach (Categoria::activas($institucionId) as $categoria) {
            $mapa[self::normalizarNombreCategoria($categoria['nombre'])] = (int) $categoria['id'];
        }

        return $mapa;
    }

    private static function normalizarNombreCategoria(string $texto): string
    {
        $texto = str_replace("\xC2\xA0", ' ', $texto); // espacio "no separable" (NBSP)
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        return mb_strtoupper(trim($texto));
    }

    private static function leerFecha(\PhpOffice\PhpSpreadsheet\Cell\Cell $celda): ?string
    {
        $valor = $celda->getValue();

        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor) && FechaExcel::isDateTimeFormatCode($celda->getStyle()->getNumberFormat()->getFormatCode())) {
            // Con declare(strict_types=1), pasar una cadena numérica (una celda de
            // fecha guardada como texto en el Excel) tal cual revienta con TypeError --
            // excelToDateTimeObject() exige float|int, no string, aunque is_numeric()
            // ya haya confirmado que es un número.
            $fecha = FechaExcel::excelToDateTimeObject((float) $valor);

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
