<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\LectorExcel;
use App\Models\Asignacion;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Espacio;
use App\Models\Usuario;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;

final class CargaMasivaService
{
    /**
     * Lee un .xlsx (columnas A-H: Código, Descripción, Marca, Fecha_Ingreso, Valor, Ubicación,
     * Categoría, Responsable individual) y compara cada fila contra la base de datos de la
     * institución. No escribe nada todavía.
     *
     * Los datos empiezan debajo de la fila de títulos (Código, Descripción…), que se busca en
     * las primeras filas: la plantilla trae instrucciones arriba (PlantillaCargaBienes) y los
     * archivos de la plantilla anterior la tienen en la fila 1.
     *
     * Responsable individual (opcional) es el documento de un usuario activo de la
     * institución: el bien queda a su cargo (responsabilidad individual) y la Ubicación, si
     * se indica, es donde está guardado. En blanco no cambia la persona del bien.
     *
     * Una columna opcional en blanco CONSERVA lo que ya tiene un bien existente (Marca, Valor,
     * Fecha_Ingreso, Ubicación, Categoría, Responsable): la carga nunca borra un dato porque
     * la celda venga vacía. En un bien nuevo, Marca queda vacía, Valor en 0 y Fecha_Ingreso
     * en la de hoy.
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
        $sheet = LectorExcel::hojaActiva($rutaArchivo, PlantillaCargaBienes::HOJA);
        $filas = [];
        $codigosVistos = [];
        $espaciosPorCodigo = self::mapaEspaciosPorCodigo($institucionId);
        $categoriasPorNombre = self::mapaCategoriasPorNombre($institucionId);
        $categoriasPorId = array_column(Categoria::all($institucionId), 'nombre', 'id');

        $ultimaFila = $sheet->getHighestDataRow();
        $primeraFila = LectorExcel::filaDeTitulos($sheet, 'Código', 'Descripción') + 1;

        // Una sola consulta con todos los códigos del archivo, en vez de una por fila --
        // con archivos de cientos/miles de filas, esto evita esa misma cantidad de
        // viajes a la base de datos solo para saber si cada código ya existe.
        $codigosDelArchivo = [];
        for ($numeroFila = $primeraFila; $numeroFila <= $ultimaFila; $numeroFila++) {
            $codigo = trim((string) $sheet->getCell("A{$numeroFila}")->getValue());
            if ($codigo !== '') {
                $codigosDelArchivo[] = $codigo;
            }
        }
        $existentesPorCodigo = Bien::buscarPorCodigosInstitucion($institucionId, $codigosDelArchivo);

        for ($numeroFila = $primeraFila; $numeroFila <= $ultimaFila; $numeroFila++) {
            $codigo = trim((string) $sheet->getCell("A{$numeroFila}")->getValue());
            $descripcion = trim((string) $sheet->getCell("B{$numeroFila}")->getValue());
            $marca = trim((string) $sheet->getCell("C{$numeroFila}")->getValue());
            $ubicacion = trim((string) $sheet->getCell("F{$numeroFila}")->getValue());
            $categoriaTexto = trim((string) $sheet->getCell("G{$numeroFila}")->getValue());
            // De la lista de la plantilla llega «documento — nombre»: vale el documento.
            $responsableTexto = PlantillaExcel::valorDeLista(trim((string) $sheet->getCell("H{$numeroFila}")->getValue()));

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

            // Los mismos límites de la base de datos: antes un texto más largo tumbaba toda la
            // carga al confirmarla.
            $motivoLargo = match (true) {
                mb_strlen($codigo) > 40 => 'El código tiene más de 40 caracteres',
                mb_strlen($descripcion) > 200 => 'La descripción tiene más de 200 caracteres',
                mb_strlen($marca) > 100 => 'La marca tiene más de 100 caracteres',
                default => null,
            };
            if ($motivoLargo !== null) {
                $filas[] = ['fila' => $numeroFila, 'tipo' => 'invalido', 'motivo' => $motivoLargo, 'codigo' => $codigo, 'descripcion' => $descripcion];
                continue;
            }

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
                // La misma regla del formulario del bien.
                if ($fecha > date('Y-m-d')) {
                    $filas[] = [
                        'fila' => $numeroFila, 'tipo' => 'invalido',
                        'motivo' => "La fecha de ingreso {$fecha} es futura", 'codigo' => $codigo, 'descripcion' => $descripcion,
                    ];
                    continue;
                }
            }

            $existente = $existentesPorCodigo[$codigo] ?? null;

            // Valor: en blanco conserva el del bien existente (0 si es nuevo); ilegible, negativo
            // o de más de 10 dígitos, la fila es inválida (antes quedaba en 0 sin avisar, o
            // un número enorme tumbaba toda la carga).
            $valor = self::leerValor($sheet->getCell("E{$numeroFila}"));
            if ($valor === false) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => 'El valor «' . trim((string) $sheet->getCell("E{$numeroFila}")->getValue()) . '» no se pudo leer: escribe solo números, por ejemplo 350000',
                    'codigo' => $codigo, 'descripcion' => $descripcion,
                ];
                continue;
            }
            if ($valor === null) {
                $valor = $existente !== null ? (float) $existente['valor'] : 0.0;
            } elseif ($valor < 0 || $valor >= Bien::VALOR_MAXIMO) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => 'El valor no puede ser negativo ni tener más de 10 dígitos',
                    'codigo' => $codigo, 'descripcion' => $descripcion,
                ];
                continue;
            }

            $espacioId = null;
            if ($ubicacion !== '') {
                $espacio = self::buscarEspacio($ubicacion, $espaciosPorCodigo);
                // Igual que el formulario del bien: solo un espacio activo y fuera de la papelera
                // (antes la carga dejaba bienes en un espacio inactivo o borrado).
                $motivo = match (true) {
                    $espacio === null => "Ubicación no encontrada: no existe un espacio con código \"{$ubicacion}\"",
                    $espacio['eliminado_en'] !== null => "El espacio \"{$espacio['codigo']}\" está en la papelera: restáuralo o elige otro",
                    (int) $espacio['activo'] !== 1 => "El espacio \"{$espacio['codigo']}\" está inactivo: actívalo o elige otro",
                    default => null,
                };
                if ($motivo !== null) {
                    $filas[] = [
                        'fila' => $numeroFila, 'tipo' => 'invalido',
                        'motivo' => $motivo,
                        'codigo' => $codigo, 'descripcion' => $descripcion,
                    ];
                    continue;
                }
                $espacioId = (int) $espacio['id'];
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

            $personaId = null;
            $personaNombre = null;
            if ($responsableTexto !== '') {
                $persona = Usuario::findByDocumento($responsableTexto, $institucionId);
                $motivo = null;
                if ($persona === null) {
                    $motivo = "Responsable no encontrado: no hay un usuario de la institución con documento \"{$responsableTexto}\"";
                } elseif (Usuario::elegibleACargo((int) $persona['id'], $institucionId) === null) {
                    $motivo = "El responsable con documento \"{$responsableTexto}\" no puede tener bienes a cargo (está inactivo o es superusuario)";
                }
                if ($motivo !== null) {
                    $filas[] = ['fila' => $numeroFila, 'tipo' => 'invalido', 'motivo' => $motivo, 'codigo' => $codigo, 'descripcion' => $descripcion];
                    continue;
                }
                $personaId = (int) $persona['id'];
                $personaNombre = trim($persona['nombres'] . ' ' . $persona['apellidos']);
            }

            if ($fecha === null) {
                $fecha = $existente ? $existente['fecha_ingreso'] : date('Y-m-d');
            }

            // Categoría en blanco: para un bien nuevo queda sin categoría, para uno existente
            // se conserva la que ya tenía — mismo criterio que Fecha_Ingreso más arriba.
            if ($categoriaTexto === '') {
                $categoriaId = ($existente && $existente['categoria_id'] !== null) ? (int) $existente['categoria_id'] : null;
            }

            // «Sin cartera»: la misma regla del formulario (código de 10 números) cuando el bien
            // entra a esa categoría (nuevo o cambiado a ella). Un bien que ya estaba en ella no
            // se bloquea por un código antiguo.
            $entraASinCartera = $categoriaId !== null
                && ($categoriasPorId[$categoriaId] ?? null) === Categoria::NOMBRE_CATEGORIA_PROTEGIDA
                && ($existente === null || (int) ($existente['categoria_id'] ?? 0) !== $categoriaId);
            if ($entraASinCartera && preg_match('/^[0-9]{10}$/', $codigo) !== 1) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => 'Los bienes de la categoría «' . Categoria::NOMBRE_CATEGORIA_PROTEGIDA . '» llevan un código de exactamente 10 números',
                    'codigo' => $codigo, 'descripcion' => $descripcion,
                ];
                continue;
            }

            $datos = [
                'codigo_identificacion' => $codigo,
                'descripcion' => $descripcion,
                // Marca en blanco: un bien existente conserva la suya (antes se borraba).
                'marca' => $marca !== '' ? $marca : ($existente['marca'] ?? null),
                'fecha_ingreso' => $fecha,
                'valor' => $valor,
                'espacio_id' => $espacioId,
                'ubicacion_texto' => $ubicacion !== '' ? $ubicacion : null,
                'persona_id' => $personaId,
                'persona_nombre' => $personaNombre,
                'categoria_id' => $categoriaId,
                // Se llevan tal cual del bien existente (si lo hay) para que aplicar() no
                // tenga que volver a leerlo de la base de datos solo por esto.
                'tiene_factura' => $existente['tiene_factura'] ?? null,
                'estado' => $existente['estado'] ?? null,
            ];

            if (!$existente) {
                $filas[] = ['fila' => $numeroFila, 'tipo' => 'nuevo', 'datos' => $datos];
                continue;
            }

            $asignacionActiva = $espacioId !== null || $personaId !== null ? Asignacion::activaDe((int) $existente['id']) : null;
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
        $modificados = [];

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

                    if ($fila['datos']['espacio_id'] !== null || ($fila['datos']['persona_id'] ?? null) !== null) {
                        Asignacion::crear([
                            'bien_id' => $bienId,
                            'usuario_responsable_id' => $fila['datos']['persona_id'] ?? null,
                            'espacio_id' => $fila['datos']['espacio_id'],
                            'fecha_asignacion' => date('Y-m-d'),
                            'observaciones' => null,
                            'asignado_por' => $usuarioId,
                        ]);
                    }
                    continue;
                }

                if ($fila['tipo'] === 'modificado') {
                    // Se relee el bien: entre el análisis y la confirmación pudo cambiar (p. ej.
                    // reintegrarse). El estado y la factura se toman de lo que hay AHORA, no de
                    // la foto del análisis (antes se podía deshacer un reintegro así). Un bien
                    // dado de baja no se toca: es de solo lectura.
                    $actual = Bien::find((int) $fila['bien_id']);
                    if (!$actual || $actual['estado'] === 'dado_de_baja') {
                        $omitidas++;
                        continue;
                    }

                    Bien::update($fila['bien_id'], [
                        'codigo_identificacion' => $fila['datos']['codigo_identificacion'],
                        'descripcion' => $fila['datos']['descripcion'],
                        'marca' => $fila['datos']['marca'],
                        'categoria_id' => $fila['datos']['categoria_id'],
                        'fecha_ingreso' => $fila['datos']['fecha_ingreso'],
                        'valor' => $fila['datos']['valor'],
                        'tiene_factura' => $actual['tiene_factura'],
                        'estado' => $actual['estado'],
                    ]);
                    $modificados[] = $actual;

                    // Solo se reubican bienes en circulación (un reintegrado no vuelve a un
                    // espacio por una carga masiva: requiere "Reactivar"). Lo que la fila no
                    // indica se conserva: sin Responsable, la persona que ya tenía; sin
                    // Ubicación, el espacio que ya tenía.
                    if ((isset($fila['cambios']['Ubicación']) || isset($fila['cambios']['Responsable individual']))
                        && in_array($actual['estado'], ['activo', 'en_reparacion'], true)) {
                        $asignacionAnterior = Asignacion::activaDe((int) $fila['bien_id']);
                        Asignacion::cerrarActivasDe($fila['bien_id']);
                        Asignacion::crear([
                            'bien_id' => $fila['bien_id'],
                            'usuario_responsable_id' => ($fila['datos']['persona_id'] ?? null) ?? ($asignacionAnterior['usuario_responsable_id'] ?? null),
                            'espacio_id' => $fila['datos']['espacio_id'] ?? ($asignacionAnterior['espacio_id'] ?? null),
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

        // Las unidades de una placa con otra categoría o valor: su renglón de la cartera, una vez.
        RenglonDePlaca::sincronizarDe($modificados, 'Carga masiva');

        return $omitidas;
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

        if (($nuevo['persona_id'] ?? null) !== null
            && (int) $nuevo['persona_id'] !== (int) ($asignacionActiva['usuario_responsable_id'] ?? 0)) {
            $cambios['Responsable individual'] = [
                'antes' => $asignacionActiva['persona_nombre'] ?? 'Ninguno',
                'despues' => $nuevo['persona_nombre'],
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
     * Código de espacio -> espacio (id, código, activo, eliminado_en), para toda la
     * institución, comparando por código normalizado (ver normalizarCodigoEspacio) en vez de
     * una consulta exacta por fila. Evita hasta miles de consultas en un archivo grande y, de
     * paso, hace que la ubicación coincida aunque el Excel traiga espacios de más o un
     * carácter de espacio "no separable" (muy común al pegar datos desde Word/PDF).
     * Si dos espacios comparten código, gana el que se puede usar (activo y fuera de la papelera).
     *
     * @return array<string, array<string, mixed>>
     */
    private static function mapaEspaciosPorCodigo(int $institucionId): array
    {
        $mapa = [];
        foreach (Espacio::listadoCodigos($institucionId) as $espacio) {
            $clave = self::normalizarCodigoEspacio((string) $espacio['codigo']);
            $actual = $mapa[$clave] ?? null;
            if ($actual === null || $actual['eliminado_en'] !== null || (int) $actual['activo'] !== 1) {
                $mapa[$clave] = $espacio;
            }
        }

        return $mapa;
    }

    /**
     * El espacio de la columna Ubicación: por su código, o por «código — nombre», como lo
     * trae la lista desplegable de la plantilla.
     *
     * @param array<string, array<string, mixed>> $espaciosPorCodigo
     * @return array<string, mixed>|null
     */
    private static function buscarEspacio(string $ubicacion, array $espaciosPorCodigo): ?array
    {
        $espacio = $espaciosPorCodigo[self::normalizarCodigoEspacio($ubicacion)] ?? null;
        if ($espacio === null && str_contains($ubicacion, PlantillaCargaBienes::RAYA)) {
            $espacio = $espaciosPorCodigo[self::normalizarCodigoEspacio(explode(PlantillaCargaBienes::RAYA, $ubicacion, 2)[0])] ?? null;
        }

        return $espacio;
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

        if (is_numeric($valor) && FechaExcel::isDateTimeFormatCode((string) $celda->getStyle()->getNumberFormat()->getFormatCode())) {
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

    /**
     * Valor de la celda: null si está vacía (se conserva el del bien), false si no se puede
     * leer como número. Acepta el formato colombiano "1.500.000,50" y "$ 350.000".
     */
    private static function leerValor(\PhpOffice\PhpSpreadsheet\Cell\Cell $celda): float|false|null
    {
        $valor = $celda->getValue();

        if ($valor === null || trim((string) $valor) === '') {
            return null;
        }

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

        return is_numeric($limpio) ? (float) $limpio : false;
    }
}
