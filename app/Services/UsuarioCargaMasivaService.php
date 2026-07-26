<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Url;
use App\Models\Cargo;
use App\Models\PasswordReset;
use App\Models\Usuario;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class UsuarioCargaMasivaService
{
    private const ROL_DEFECTO = 'docente';

    /**
     * Lee un .xlsx (columnas A-F: Documento, Nombres, Apellidos, Cargo, Correo, Rol) y
     * compara cada fila contra la base de datos de la institución. No escribe nada todavía.
     *
     * $permiteSuperusuario controla si la fila puede asignar el rol "superusuario" (solo si
     * quien sube el archivo ya es superusuario), igual que la validación del formulario
     * individual.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function analizar(string $rutaArchivo, int $institucionId, bool $permiteSuperusuario): array
    {
        $sheet = IOFactory::load($rutaArchivo)->getActiveSheet();
        $filas = [];
        $documentosVistos = [];
        $correosVistos = [];

        $rolesPorNombre = [];
        foreach (Usuario::rolesParaSelect($permiteSuperusuario) as $rol) {
            $rolesPorNombre[mb_strtolower($rol['nombre'])] = $rol;
        }

        $ultimaFila = $sheet->getHighestDataRow();

        for ($numeroFila = 2; $numeroFila <= $ultimaFila; $numeroFila++) {
            $documento = trim((string) $sheet->getCell("A{$numeroFila}")->getValue());
            $nombres = trim((string) $sheet->getCell("B{$numeroFila}")->getValue());
            $apellidos = trim((string) $sheet->getCell("C{$numeroFila}")->getValue());
            $cargoTexto = trim((string) $sheet->getCell("D{$numeroFila}")->getValue());
            $email = trim((string) $sheet->getCell("E{$numeroFila}")->getValue());
            $rolTexto = trim((string) $sheet->getCell("F{$numeroFila}")->getValue());

            if ($documento === '' && $nombres === '' && $apellidos === '' && $email === '') {
                continue;
            }

            if ($documento === '' || $nombres === '' || $apellidos === '' || $email === '') {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => 'Falta documento, nombres, apellidos o correo',
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

            if ($cargoTexto === '') {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => 'Falta el cargo', 'documento' => $documento, 'nombres' => $nombres, 'apellidos' => $apellidos,
                ];
                continue;
            }

            $cargo = Cargo::findByNombre($cargoTexto);
            if (!$cargo) {
                $filas[] = [
                    'fila' => $numeroFila, 'tipo' => 'invalido',
                    'motivo' => "El cargo \"{$cargoTexto}\" no existe o está inactivo",
                    'documento' => $documento, 'nombres' => $nombres, 'apellidos' => $apellidos,
                ];
                continue;
            }

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

            $existenteEnInstitucion = Usuario::findByDocumento($documento, $institucionId);

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
     * provisional inutilizable (nadie la conoce) y reciben un correo de bienvenida con un
     * enlace para definir su propia contraseña — el mismo mecanismo de "olvidé mi
     * contraseña" — así el archivo nunca necesita traer contraseñas en texto plano.
     *
     * Devuelve cuántas filas se omitieron por un choque de documento/correo detectado justo
     * al escribir (p. ej. si alguien registró ese mismo documento por otra vía entre que se
     * analizó el archivo y se confirmó la carga).
     */
    public static function aplicar(array $filas, int $institucionId): int
    {
        $omitidas = 0;

        foreach ($filas as $fila) {
            try {
                if ($fila['tipo'] === 'nuevo') {
                    $id = Usuario::create([
                        'documento' => $fila['datos']['documento'],
                        'nombres' => $fila['datos']['nombres'],
                        'apellidos' => $fila['datos']['apellidos'],
                        'cargo_id' => $fila['datos']['cargo_id'],
                        'email' => $fila['datos']['email'],
                        'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT),
                        'institucion_id' => $institucionId,
                        'rol_id' => $fila['datos']['rol_id'],
                    ]);
                    self::enviarCorreoBienvenida($id, trim($fila['datos']['nombres'] . ' ' . $fila['datos']['apellidos']), $fila['datos']['email']);
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

    public static function enviarPlantilla(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Documento', 'Nombres', 'Apellidos', 'Cargo', 'Correo', 'Rol'], null, 'A1');
        $sheet->fromArray(['123456789', 'Juan', 'Pérez', 'Docente', 'juan.perez@correo.com', 'docente'], null, 'A2');
        foreach (range('A', 'F') as $columna) {
            $sheet->getColumnDimension($columna)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="plantilla_carga_usuarios.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
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

    private static function enviarCorreoBienvenida(int $usuarioId, string $nombre, string $email): void
    {
        try {
            $token = PasswordReset::crear($usuarioId, 60 * 24 * 7);
            $enlace = Url::absoluta('/restablecer-contrasena/' . $token);

            MailService::enviar(
                $email,
                $nombre,
                'Bienvenido a SIGEBI · Define tu contraseña',
                self::plantillaCorreoBienvenida($nombre, $enlace)
            );
        } catch (\RuntimeException $e) {
            error_log('MailService (bienvenida carga masiva de usuarios): ' . $e->getMessage());
        }
    }

    private static function plantillaCorreoBienvenida(string $nombre, string $enlace): string
    {
        $nombreEscapado = htmlspecialchars($nombre, ENT_QUOTES);
        $enlaceEscapado = htmlspecialchars($enlace, ENT_QUOTES);

        return <<<HTML
            <p>Hola {$nombreEscapado},</p>
            <p>Se creó tu cuenta en SIGEBI. Para poder ingresar, primero define tu contraseña en el siguiente enlace (válido por 7 días):</p>
            <p><a href="{$enlaceEscapado}">{$enlaceEscapado}</a></p>
            <p>Si no esperabas este correo, puedes ignorarlo.</p>
            HTML;
    }
}
