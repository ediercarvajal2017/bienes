<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\LectorExcel;
use App\Models\Espacio;
use App\Models\Usuario;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class EspacioCargaMasivaService
{
    /**
     * Lee un .xlsx (columnas A-C: Número, Nombre, Documentos de responsables separados por
     * coma) y compara cada fila contra la base de datos de la institución. No escribe nada
     * todavía.
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
        $sheet = LectorExcel::hojaActiva($rutaArchivo);
        $filas = [];
        $codigosVistos = [];

        $ultimaFila = $sheet->getHighestDataRow();

        for ($numeroFila = 2; $numeroFila <= $ultimaFila; $numeroFila++) {
            $codigo = trim((string) $sheet->getCell("A{$numeroFila}")->getValue());
            $nombre = trim((string) $sheet->getCell("B{$numeroFila}")->getValue());
            $documentosTexto = trim((string) $sheet->getCell("C{$numeroFila}")->getValue());

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

            $existente = Espacio::buscarPorCodigoInstitucion($institucionId, $codigo);
            if ($existente !== null && $existente['eliminado_en'] !== null) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => "El espacio {$codigo} está en la papelera: un superusuario debe restaurarlo antes de cargarlo",
                    'codigo' => $codigo, 'nombre' => $nombre,
                ];
                continue;
            }

            $documentos = array_values(array_unique(array_filter(array_map('trim', explode(',', $documentosTexto)), fn ($d) => $d !== '')));
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

    public static function enviarPlantilla(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Numero', 'Nombre', 'Documentos_Responsables'], null, 'A1');
        $sheet->fromArray(['101', 'Sala de sistemas 1', '123456789,987654321'], null, 'A2');
        foreach (range('A', 'C') as $columna) {
            $sheet->getColumnDimension($columna)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="plantilla_carga_espacios.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
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
