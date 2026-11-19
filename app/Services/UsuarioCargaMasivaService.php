<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\LectorExcel;
use App\Models\Cargo;
use App\Models\Usuario;

final class UsuarioCargaMasivaService
{
    private const ROL_DEFECTO = 'docente';

    /**
     * Lee un .xlsx (columnas A-F: Documento, Nombres, Apellidos, Cargo, Correo, Rol) y
     * compara cada fila contra la base de datos de la institución. No escribe nada todavía.
     * Los datos empiezan debajo de la fila de títulos (la plantilla trae instrucciones arriba:
     * PlantillaCargaUsuarios).
     *
     * $permiteSuperusuario controla si la fila puede asignar el rol "superusuario" (solo si
     * quien sube el archivo ya es superusuario), igual que la validación del formulario
     * individual.
     *
     * Documento es obligatorio siempre (identifica al usuario). Para un usuario NUEVO también
     * lo son Nombres, Apellidos, Correo y Cargo; Rol en blanco es "docente". Para uno que YA
     * EXISTE, cualquier columna en blanco CONSERVA lo que tiene (antes Rol en blanco lo
     * pasaba a docente): la carga nunca borra ni cambia un dato porque la celda venga vacía.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function analizar(string $rutaArchivo, int $institucionId, bool $permiteSuperusuario): array
    {
        $sheet = LectorExcel::hojaActiva($rutaArchivo, PlantillaCargaUsuarios::HOJA);
        $filas = [];
        $documentosVistos = [];
        $correosVistos = [];

        $rolesPorNombre = [];
        foreach (Usuario::rolesParaSelect($permiteSuperusuario) as $rol) {
            $rolesPorNombre[mb_strtolower($rol['nombre'])] = $rol;
        }

        $ultimaFila = $sheet->getHighestDataRow();
        $primeraFila = LectorExcel::filaDeTitulos($sheet, 'Documento', 'Nombres') + 1;

        for ($numeroFila = $primeraFila; $numeroFila <= $ultimaFila; $numeroFila++) {
            $documento = trim((string) $sheet->getCell("A{$numeroFila}")->getValue());
            $nombres = trim((string) $sheet->getCell("B{$numeroFila}")->getValue());
            $apellidos = trim((string) $sheet->getCell("C{$numeroFila}")->getValue());
            $cargoTexto = trim((string) $sheet->getCell("D{$numeroFila}")->getValue());
            $email = trim((string) $sheet->getCell("E{$numeroFila}")->getValue());
            $rolTexto = trim((string) $sheet->getCell("F{$numeroFila}")->getValue());

            if ($documento === '' && $nombres === '' && $apellidos === '' && $email === '') {
                continue;
            }

            if ($documento === '') {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => 'Falta el documento: es el que identifica al usuario',
                    'documento' => $documento, 'nombres' => $nombres, 'apellidos' => $apellidos,
                ];
                continue;
            }

            // Los mismos límites de la base de datos: antes un texto más largo tumbaba toda la
            // carga al confirmarla.
            $motivoLargo = match (true) {
                mb_strlen($documento) > 20 => 'El documento tiene más de 20 caracteres',
                mb_strlen($nombres) > 100 => 'Los nombres tienen más de 100 caracteres',
                mb_strlen($apellidos) > 100 => 'Los apellidos tienen más de 100 caracteres',
                mb_strlen($email) > 150 => 'El correo tiene más de 150 caracteres',
                default => null,
            };
            if ($motivoLargo !== null) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido', 'motivo' => $motivoLargo,
                    'documento' => $documento, 'nombres' => $nombres, 'apellidos' => $apellidos,
                ];
                continue;
            }

            // Un usuario que ya existe conserva lo que la fila deja en blanco.
            $existenteEnInstitucion = Usuario::findByDocumento($documento, $institucionId);

            // Una cuenta de superusuario solo la administra otro superusuario, igual que en el
            // formulario (UsuarioController::verificarAcceso). Sin esto, un rector que comparta
            // institución con él podía cambiarle el correo (y recuperar su contraseña) o el rol.
            if (!$permiteSuperusuario && ($existenteEnInstitucion['rol_nombre'] ?? '') === 'superusuario') {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => 'Es una cuenta de superusuario: solo otro superusuario puede modificarla',
                    'documento' => $documento, 'nombres' => $nombres, 'apellidos' => $apellidos,
                ];
                continue;
            }

            if ($existenteEnInstitucion !== null) {
                $nombres = $nombres !== '' ? $nombres : (string) $existenteEnInstitucion['nombres'];
                $apellidos = $apellidos !== '' ? $apellidos : (string) $existenteEnInstitucion['apellidos'];
                $email = $email !== '' ? $email : (string) $existenteEnInstitucion['email'];
            } elseif ($nombres === '' || $apellidos === '' || $email === '') {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => 'Un usuario nuevo necesita nombres, apellidos y correo',
                    'documento' => $documento, 'nombres' => $nombres, 'apellidos' => $apellidos,
                ];
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => 'Correo electrónico inválido',
                    'documento' => $documento, 'nombres' => $nombres, 'apellidos' => $apellidos,
                ];
                continue;
            }

            if (isset($documentosVistos[$documento])) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => "Documento repetido dentro del mismo archivo (ya aparece en la fila {$documentosVistos[$documento]})",
                    'documento' => $documento, 'nombres' => $nombres, 'apellidos' => $apellidos,
                ];
                continue;
            }

            $emailNormalizado = mb_strtolower($email);
            if (isset($correosVistos[$emailNormalizado])) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => "Correo repetido dentro del mismo archivo (ya aparece en la fila {$correosVistos[$emailNormalizado]})",
                    'documento' => $documento, 'nombres' => $nombres, 'apellidos' => $apellidos,
                ];
                continue;
            }
            $documentosVistos[$documento] = $numeroFila;
            $correosVistos[$emailNormalizado] = $numeroFila;

            if ($cargoTexto === '' && $existenteEnInstitucion === null) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => 'Un usuario nuevo necesita el cargo', 'documento' => $documento, 'nombres' => $nombres, 'apellidos' => $apellidos,
                ];
                continue;
            }

            $cargo = $cargoTexto === ''
                ? ['id' => $existenteEnInstitucion['cargo_id'], 'nombre' => $existenteEnInstitucion['cargo_nombre']]
                : Cargo::findByNombre($cargoTexto);
            if (!$cargo) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => "El cargo \"{$cargoTexto}\" no existe o está inactivo",
                    'documento' => $documento, 'nombres' => $nombres, 'apellidos' => $apellidos,
                ];
                continue;
            }

            if ($rolTexto === '' && $existenteEnInstitucion !== null) {
                // Rol en blanco: el usuario que ya existe conserva el suyo.
                $rol = ['id' => $existenteEnInstitucion['rol_id'], 'nombre' => $existenteEnInstitucion['rol_nombre']];
            } else {
                $nombreRol = mb_strtolower($rolTexto !== '' ? $rolTexto : self::ROL_DEFECTO);
                if (!isset($rolesPorNombre[$nombreRol])) {
                    $filas[] = [
                        'fila' => $numeroFila, 'tipo' => 'invalido',
                        'motivo' => $rolTexto !== '' ? "El rol \"{$rolTexto}\" no es válido" : 'No se pudo asignar el rol por defecto',
                        'documento' => $documento, 'nombres' => $nombres, 'apellidos' => $apellidos,
                    ];
                    continue;
                }
                $rol = $rolesPorNombre[$nombreRol];
            }

            if (!$existenteEnInstitucion && Usuario::existeDocumento($documento)) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => 'Ya existe un usuario con ese documento en otra institución',
                    'documento' => $documento, 'nombres' => $nombres, 'apellidos' => $apellidos,
                ];
                continue;
            }

            $exceptId = $existenteEnInstitucion ? (int) $existenteEnInstitucion['id'] : null;
            if (Usuario::existeEmail($email, $exceptId)) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => 'Ya existe otro usuario con ese correo electrónico',
                    'documento' => $documento, 'nombres' => $nombres, 'apellidos' => $apellidos,
                ];
                continue;
            }

            $datos = [
                'documento' => $documento,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'cargo_id' => (int) $cargo['id'],
                'cargo_nombre' => $cargo['nombre'],
                'email' => $email,
                'rol_id' => (int) $rol['id'],
                'rol_nombre' => $rol['nombre'],
            ];

            if (!$existenteEnInstitucion) {
                $filas[] = ['fila' => $numeroFila, 'tipo' => 'nuevo', 'datos' => $datos];
                continue;
            }

            $cambios = self::detectarCambios($existenteEnInstitucion, $datos);

            $filas[] = $cambios === []
                ? ['fila' => $numeroFila, 'tipo' => 'sin_cambios', 'datos' => $datos, 'usuario_id' => (int) $existenteEnInstitucion['id']]
                : ['fila' => $numeroFila, 'tipo' => 'modificado', 'datos' => $datos, 'usuario_id' => (int) $existenteEnInstitucion['id'], 'cambios' => $cambios];
        }

        return $filas;
    }

    /**
     * Aplica los cambios a la BD. Los usuarios nuevos se crean con una contraseña
     * provisional inutilizable (nadie la conoce): no se envía ningún correo desde aquí —
     * con archivos grandes, enviar un correo de bienvenida por cada usuario dentro de esta
     * misma petición agotaba el tiempo de espera de nginx (504 Gateway Time-out) — así que
     * cada usuario nuevo debe usar "¿Olvidaste tu contraseña?" con su correo registrado para
     * activarse la primera vez.
     *
     * Devuelve cuántas filas se omitieron por un choque de documento/correo detectado justo
     * al escribir (p. ej. si alguien registró ese mismo documento por otra vía entre que se
     * analizó el archivo y se confirmó la carga), o porque quien confirma no es superusuario
     * y la fila crea o toca una cuenta de superusuario (se vuelve a comprobar aquí: quien
     * confirma puede no ser quien analizó, y el rol pudo cambiar entre tanto).
     */
    public static function aplicar(array $filas, int $institucionId, bool $permiteSuperusuario): int
    {
        $omitidas = 0;

        foreach ($filas as $fila) {
            if (!$permiteSuperusuario && in_array($fila['tipo'], ['nuevo', 'modificado'], true)) {
                $actual = $fila['tipo'] === 'modificado' ? Usuario::find((int) $fila['usuario_id']) : null;
                if (($fila['datos']['rol_nombre'] ?? '') === 'superusuario' || ($actual['rol_nombre'] ?? '') === 'superusuario') {
                    $omitidas++;
                    continue;
                }
            }

            try {
                if ($fila['tipo'] === 'nuevo') {
                    Usuario::create([
                        'documento' => $fila['datos']['documento'],
                        'nombres' => $fila['datos']['nombres'],
                        'apellidos' => $fila['datos']['apellidos'],
                        'cargo_id' => $fila['datos']['cargo_id'],
                        'email' => $fila['datos']['email'],
                        'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT),
                        'institucion_id' => $institucionId,
                        'rol_id' => $fila['datos']['rol_id'],
                    ]);
                    continue;
                }

                if ($fila['tipo'] === 'modificado') {
                    Usuario::update($fila['usuario_id'], [
                        'documento' => $fila['datos']['documento'],
                        'nombres' => $fila['datos']['nombres'],
                        'apellidos' => $fila['datos']['apellidos'],
                        'cargo_id' => $fila['datos']['cargo_id'],
                        'email' => $fila['datos']['email'],
                        'institucion_id' => $institucionId,
                        'rol_id' => $fila['datos']['rol_id'],
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

    private static function detectarCambios(array $existente, array $nuevo): array
    {
        $cambios = [];
        $comparar = ['nombres' => 'Nombres', 'apellidos' => 'Apellidos', 'email' => 'Correo'];

        foreach ($comparar as $campo => $etiqueta) {
            $actual = trim((string) ($existente[$campo] ?? ''));
            $propuesto = trim((string) $nuevo[$campo]);

            if ($actual !== $propuesto) {
                $cambios[$etiqueta] = ['antes' => $actual, 'despues' => $propuesto];
            }
        }

        if ((int) $existente['cargo_id'] !== $nuevo['cargo_id']) {
            $cambios['Cargo'] = ['antes' => $existente['cargo_nombre'], 'despues' => $nuevo['cargo_nombre']];
        }

        if ((int) $existente['rol_id'] !== $nuevo['rol_id']) {
            $cambios['Rol'] = ['antes' => $existente['rol_nombre'], 'despues' => $nuevo['rol_nombre']];
        }

        return $cambios;
    }
}
