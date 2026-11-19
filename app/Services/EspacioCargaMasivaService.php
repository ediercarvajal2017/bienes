<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\LectorExcel;
use App\Models\Espacio;
use App\Models\Usuario;

final class EspacioCargaMasivaService
{
    /**
     * Lee un .xlsx (columnas A-E: Número, Nombre y Responsable 1 a 3) y compara cada fila
     * contra la base de datos de la institución. No escribe nada todavía. Los datos empiezan
     * debajo de la fila de títulos (la plantilla trae instrucciones arriba: PlantillaCargaEspacios).
     *
     * Cada responsable llega como lo deja la lista de la plantilla («documento — nombre») o
     * como documentos separados por coma (así era la plantilla anterior, con todo en la C).
     *
     * Número y Nombre son obligatorios. Responsables es obligatorio para un espacio nuevo
     * (todo espacio necesita al menos uno); en un espacio que ya existe, en blanco CONSERVA
     * sus responsables: la carga nunca borra un dato porque la celda venga vacía. Si trae
     * documentos, esa lista pasa a ser la de responsables (la vista previa lo muestra).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function analizar(string $rutaArchivo, int $institucionId): array
    {
        $sheet = LectorExcel::hojaActiva($rutaArchivo, PlantillaCargaEspacios::HOJA);
        $filas = [];
        $codigosVistos = [];

        $ultimaFila = $sheet->getHighestDataRow();
        $primeraFila = LectorExcel::filaDeTitulos($sheet, 'Número', 'Nombre') + 1;

        for ($numeroFila = $primeraFila; $numeroFila <= $ultimaFila; $numeroFila++) {
            $codigo = trim((string) $sheet->getCell("A{$numeroFila}")->getValue());
            $nombre = trim((string) $sheet->getCell("B{$numeroFila}")->getValue());
            $documentos = [];
            foreach (['C', 'D', 'E'] as $columna) {
                $celda = trim((string) $sheet->getCell("{$columna}{$numeroFila}")->getValue());
                $documentos = array_merge($documentos, str_contains($celda, PlantillaExcel::RAYA)
                    ? [PlantillaExcel::valorDeLista($celda)]
                    : explode(',', $celda));
            }

            if ($codigo === '' && $nombre === '') {
                continue;
            }

            if ($codigo === '' || $nombre === '') {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => 'Falta el número o el nombre del espacio', 'codigo' => $codigo, 'nombre' => $nombre,
                ];
                continue;
            }

            if (isset($codigosVistos[$codigo])) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => "Número de espacio repetido dentro del mismo archivo (ya aparece en la fila {$codigosVistos[$codigo]})",
                    'codigo' => $codigo, 'nombre' => $nombre,
                ];
                continue;
            }
            $codigosVistos[$codigo] = $numeroFila;

            // Los mismos límites de la base de datos: antes un texto más largo tumbaba toda la
            // carga al confirmarla.
            $motivoLargo = match (true) {
                mb_strlen($codigo) > 30 => 'El número tiene más de 30 caracteres',
                mb_strlen($nombre) > 100 => 'El nombre tiene más de 100 caracteres',
                default => null,
            };
            if ($motivoLargo !== null) {
                $filas[] = ['fila' => $numeroFila, 'tipo' => 'invalido', 'motivo' => $motivoLargo, 'codigo' => $codigo, 'nombre' => $nombre];
                continue;
            }

            $existente = Espacio::buscarPorCodigoInstitucion($institucionId, $codigo);
            if ($existente !== null && $existente['eliminado_en'] !== null) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => "El espacio {$codigo} está en la papelera: un superusuario debe restaurarlo antes de cargarlo",
                    'codigo' => $codigo, 'nombre' => $nombre,
                ];
                continue;
            }

            $documentos = array_values(array_unique(array_filter(array_map('trim', $documentos), fn ($d) => $d !== '')));
            if (empty($documentos) && $existente === null) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => 'Un espacio nuevo necesita al menos un documento de responsable', 'codigo' => $codigo, 'nombre' => $nombre,
                ];
                continue;
            }

            // null: la celda vino vacía y el espacio existente conserva sus responsables.
            $responsables = null;
            if (!empty($documentos)) {
                $responsables = [];
                $problemas = [];
                foreach ($documentos as $documento) {
                    $usuario = Usuario::findByDocumento($documento, $institucionId);
                    if (!$usuario) {
                        $problemas[] = "{$documento} (no existe en la institución)";
                        continue;
                    }
                    if ((int) $usuario['activo'] !== 1) {
                        $problemas[] = "{$documento} (usuario inactivo)";
                        continue;
                    }
                    $responsables[] = $usuario;
                }

                if ($problemas !== []) {
                    $filas[] = [
                        'fila' => $numeroFila, 'tipo' => 'invalido',
                        'motivo' => 'Responsable(s) que no se pueden asignar: ' . implode(', ', $problemas),
                        'codigo' => $codigo, 'nombre' => $nombre,
                    ];
                    continue;
                }
            }

            $datos = [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'responsables' => $responsables,
            ];

            if (!$existente) {
                $filas[] = ['fila' => $numeroFila, 'tipo' => 'nuevo', 'datos' => $datos];
                continue;
            }

            $cambios = self::detectarCambios($existente, $datos);

            $filas[] = $cambios === []
                ? ['fila' => $numeroFila, 'tipo' => 'sin_cambios', 'datos' => $datos, 'espacio_id' => $existente['id']]
                : ['fila' => $numeroFila, 'tipo' => 'modificado', 'datos' => $datos, 'espacio_id' => $existente['id'], 'cambios' => $cambios];
        }

        return $filas;
    }

    /**
     * Aplica los cambios a la BD. Devuelve cuántas filas se omitieron por un choque de
     * código detectado justo al escribir (p. ej. si alguien creó ese mismo número de
     * espacio por otra vía entre que se analizó el archivo y se confirmó la carga) — para
     * que ninguna fila conflictiva tumbe el resto de la operación con un error fatal.
     */
    public static function aplicar(array $filas, int $institucionId): int
    {
        $omitidas = 0;

        foreach ($filas as $fila) {
            try {
                if ($fila['tipo'] === 'nuevo') {
                    $id = Espacio::create([
                        'institucion_id' => $institucionId,
                        'nombre' => $fila['datos']['nombre'],
                        'codigo' => $fila['datos']['codigo'],
                    ]);
                    Espacio::sincronizarResponsables($id, array_column($fila['datos']['responsables'], 'id'));
                    continue;
                }

                if ($fila['tipo'] === 'modificado') {
                    Espacio::update($fila['espacio_id'], [
                        'nombre' => $fila['datos']['nombre'],
                        'codigo' => $fila['datos']['codigo'],
                    ]);
                    // Responsables en blanco: se conservan los que tiene.
                    if ($fila['datos']['responsables'] !== null) {
                        Espacio::sincronizarResponsables($fila['espacio_id'], array_column($fila['datos']['responsables'], 'id'));
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

    private static function detectarCambios(array $existente, array $nuevo): array
    {
        $cambios = [];

        if (trim((string) $existente['nombre']) !== trim($nuevo['nombre'])) {
            $cambios['Nombre'] = ['antes' => $existente['nombre'], 'despues' => $nuevo['nombre']];
        }

        if ($nuevo['responsables'] === null) {
            return $cambios; // en blanco: se conservan
        }

        $responsablesActuales = array_column(Espacio::responsablesDe((int) $existente['id']), 'id');
        $responsablesNuevos = array_column($nuevo['responsables'], 'id');
        sort($responsablesActuales);
        sort($responsablesNuevos);

        if ($responsablesActuales !== $responsablesNuevos) {
            $nombreDe = fn ($u) => trim($u['nombres'] . ' ' . $u['apellidos']);
            $cambios['Responsable(s)'] = [
                'antes' => implode(', ', array_map($nombreDe, Espacio::responsablesDe((int) $existente['id']))),
                'despues' => implode(', ', array_map($nombreDe, $nuevo['responsables'])),
            ];
        }

        return $cambios;
    }
}
