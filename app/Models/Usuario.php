<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;
use PDO;

final class Usuario
{
    public static function findByEmail(string $email): ?array
    {
        return self::buscarParaAcceso('u.email = ?', $email);
    }

    /**
     * Mismos datos que findByEmail(), por id: los usa el segundo paso del inicio de sesión
     * (verificación en dos pasos), cuando la contraseña ya se validó.
     */
    public static function findParaAcceso(int $id): ?array
    {
        return self::buscarParaAcceso('u.id = ?', $id);
    }

    private static function buscarParaAcceso(string $condicion, string|int $valor): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.nombre AS rol_nombre, i.activo AS institucion_activa, i.nombre AS institucion_nombre
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             JOIN instituciones i ON i.id = u.institucion_id
             WHERE ' . $condicion . ' AND u.eliminado_en IS NULL'
        );
        $stmt->execute([$valor]);
        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    public static function findByDocumento(string $documento, int $institucionId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.documento, u.nombres, u.apellidos, u.cargo_id, c.nombre AS cargo_nombre,
                    u.email, u.rol_id, r.nombre AS rol_nombre, u.activo
             FROM usuarios u
             JOIN cargos c ON c.id = u.cargo_id
             JOIN roles r ON r.id = u.rol_id
             WHERE u.documento = ? AND u.institucion_id = ? AND u.eliminado_en IS NULL'
        );
        $stmt->execute([$documento, $institucionId]);

        return $stmt->fetch() ?: null;
    }

    /**
     * El rector de la institución, para documentos oficiales que exigen su nombre y
     * documento (ej. el comprobante de reintegro FO-ADMI-009). El esquema no impone
     * que haya un único rector activo por institución, así que si hay varios se toma
     * el más antiguo (menor id) de forma determinista.
     */
    public static function rectorDe(int $institucionId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.documento, u.nombres, u.apellidos
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             WHERE u.institucion_id = ? AND r.nombre = "rector" AND u.activo = 1 AND u.eliminado_en IS NULL
             ORDER BY u.id
             LIMIT 1'
        );
        $stmt->execute([$institucionId]);

        return $stmt->fetch() ?: null;
    }

    /** Deja la prueba de que el usuario aceptó la política de datos (versión y fecha). */
    public static function aceptarPolitica(int $usuarioId, string $version): void
    {
        Database::connection()
            ->prepare('UPDATE usuarios SET politica_version = ?, politica_aceptada_en = NOW() WHERE id = ?')
            ->execute([$version, $usuarioId]);
    }

    public static function registrarLoginExitoso(int $usuarioId): void
    {
        Database::connection()
            ->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_login = NOW() WHERE id = ?')
            ->execute([$usuarioId]);
    }

    /**
     * Lo mínimo para revalidar una sesión abierta (Auth::check): si la cuenta sigue
     * activa, no está en la papelera, su institución sigue activa, su rol actual y la
     * versión de sesión vigente. null si el usuario ya no existe.
     */
    public static function estadoSesion(int $usuarioId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.activo, u.eliminado_en, u.sesion_version, u.politica_version, r.nombre AS rol_nombre,
                    i.activo AS institucion_activa
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             JOIN instituciones i ON i.id = u.institucion_id
             WHERE u.id = ?'
        );
        $stmt->execute([$usuarioId]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Cierra todas las sesiones abiertas del usuario: sube su versión de sesión, y
     * Auth::check() rechaza en la siguiente revalidación (máx. 1 minuto) cualquier sesión
     * con la versión anterior. Devuelve la versión nueva.
     */
    public static function invalidarSesiones(int $usuarioId): int
    {
        $pdo = Database::connection();
        $pdo->prepare('UPDATE usuarios SET sesion_version = sesion_version + 1 WHERE id = ?')->execute([$usuarioId]);

        $stmt = $pdo->prepare('SELECT sesion_version FROM usuarios WHERE id = ?');
        $stmt->execute([$usuarioId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Quita el bloqueo por intentos fallidos (tras restablecer la contraseña por correo:
     * quien la restablece demostró ser el dueño de la cuenta).
     */
    public static function desbloquear(int $usuarioId): void
    {
        Database::connection()
            ->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?')
            ->execute([$usuarioId]);
    }

    public static function permisosDe(int $usuarioId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.codigo
             FROM permisos p
             JOIN rol_permiso rp ON rp.permiso_id = p.id
             JOIN usuarios u ON u.rol_id = rp.rol_id
             WHERE u.id = ?'
        );
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.nombre AS rol_nombre, c.nombre AS cargo_nombre, i.nombre AS institucion_nombre
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             JOIN cargos c ON c.id = u.cargo_id
             JOIN instituciones i ON i.id = u.institucion_id
             WHERE u.id = ? AND u.eliminado_en IS NULL'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function listar(?int $institucionId = null, ?string $busqueda = null, int $pagina = 1, int $porPagina = 50, bool $incluirSuperusuarios = true): array
    {
        [$whereSql, $params] = self::condicionesListado($institucionId, $busqueda, $incluirSuperusuarios);

        $sql = 'SELECT u.*, r.nombre AS rol_nombre, c.nombre AS cargo_nombre, i.nombre AS institucion_nombre
                FROM usuarios u
                JOIN roles r ON r.id = u.rol_id
                JOIN cargos c ON c.id = u.cargo_id
                JOIN instituciones i ON i.id = u.institucion_id'
               . $whereSql
               . ' ORDER BY u.created_at DESC, u.id DESC' . Paginador::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function contarListado(?int $institucionId = null, ?string $busqueda = null, bool $incluirSuperusuarios = true): int
    {
        [$whereSql, $params] = self::condicionesListado($institucionId, $busqueda, $incluirSuperusuarios);

        $sql = 'SELECT COUNT(*)
                FROM usuarios u
                JOIN cargos c ON c.id = u.cargo_id'
               . $whereSql;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca por nombre, apellido, documento, correo o cargo — las columnas visibles
     * en /usuarios.
     */
    /**
     * $incluirSuperusuarios = false para quien no es superusuario: las cuentas de
     * superusuario no se listan (ni se pueden editar, ver UsuarioController::verificarAcceso).
     */
    private static function condicionesListado(?int $institucionId, ?string $busqueda, bool $incluirSuperusuarios = true): array
    {
        $condiciones = ['u.eliminado_en IS NULL'];
        $params = [];

        if (!$incluirSuperusuarios) {
            $condiciones[] = "u.rol_id NOT IN (SELECT id FROM roles WHERE nombre = 'superusuario')";
        }

        if ($institucionId !== null) {
            $condiciones[] = 'u.institucion_id = ?';
            $params[] = $institucionId;
        }

        if ($busqueda !== null && $busqueda !== '') {
            $termino = '%' . $busqueda . '%';
            $condiciones[] = '(u.nombres LIKE ? OR u.apellidos LIKE ? OR u.documento LIKE ? OR u.email LIKE ? OR c.nombre LIKE ?)';
            array_push($params, $termino, $termino, $termino, $termino, $termino);
        }

        $sql = ' WHERE ' . implode(' AND ', $condiciones);

        return [$sql, $params];
    }

    /**
     * Sin paginar: fuente para selects/checkboxes (p. ej. elegir responsables de un
     * espacio), donde se necesita el listado completo, no una página.
     */
    public static function listarTodos(?int $institucionId = null): array
    {
        $sql = 'SELECT u.*, r.nombre AS rol_nombre, c.nombre AS cargo_nombre, i.nombre AS institucion_nombre
                FROM usuarios u
                JOIN roles r ON r.id = u.rol_id
                JOIN cargos c ON c.id = u.cargo_id
                JOIN instituciones i ON i.id = u.institucion_id
                WHERE u.eliminado_en IS NULL';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' AND u.institucion_id = ?';
            $params[] = $institucionId;
        }

        $sql .= ' ORDER BY u.nombres, u.apellidos';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Personas que pueden tener bienes a cargo (responsabilidad individual): usuarios
     * activos de la institución del bien, sin superusuarios.
     *
     * @return list<array<string, mixed>>
     */
    public static function elegiblesACargo(int $institucionId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.id, u.nombres, u.apellidos, u.documento, c.nombre AS cargo_nombre, r.nombre AS rol_nombre
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             JOIN cargos c ON c.id = u.cargo_id
             WHERE u.institucion_id = ? AND u.activo = 1 AND u.eliminado_en IS NULL AND r.nombre <> 'superusuario'
             ORDER BY u.nombres, u.apellidos"
        );
        $stmt->execute([$institucionId]);

        return array_values($stmt->fetchAll());
    }

    /** La persona, si puede tener a cargo bienes de esa institución (ver elegiblesACargo). */
    public static function elegibleACargo(int $usuarioId, int $institucionId): ?array
    {
        foreach (self::elegiblesACargo($institucionId) as $persona) {
            if ((int) $persona['id'] === $usuarioId) {
                return $persona;
            }
        }

        return null;
    }

    public static function create(array $datos): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO usuarios (documento, nombres, apellidos, cargo_id, email, password_hash, institucion_id, rol_id, activo)
             VALUES (:documento, :nombres, :apellidos, :cargo_id, :email, :password_hash, :institucion_id, :rol_id, 1)'
        );
        $stmt->execute($datos);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $datos): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuarios SET documento = :documento, nombres = :nombres, apellidos = :apellidos,
             cargo_id = :cargo_id, email = :email, institucion_id = :institucion_id, rol_id = :rol_id
             WHERE id = :id'
        );
        $stmt->execute([
            'documento' => $datos['documento'],
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'cargo_id' => $datos['cargo_id'],
            'email' => $datos['email'],
            'institucion_id' => $datos['institucion_id'],
            'rol_id' => $datos['rol_id'],
            'id' => $id,
        ]);
    }

    public static function updatePassword(int $id, string $passwordHash): void
    {
        Database::connection()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([$passwordHash, $id]);
    }

    /** Deja activa la verificación en dos pasos con la clave (ya cifrada) del autenticador. */
    public static function activarTotp(int $id, string $secretoCifrado, int $pasoUsado): void
    {
        Database::connection()->prepare(
            'UPDATE usuarios SET totp_secreto = ?, totp_activado_en = NOW(), totp_ultimo_paso = ?
             WHERE id = ?'
        )->execute([$secretoCifrado, $pasoUsado, $id]);
    }

    /** Quita la verificación en dos pasos. */
    public static function desactivarTotp(int $id): void
    {
        Database::connection()->prepare(
            'UPDATE usuarios SET totp_secreto = NULL, totp_activado_en = NULL, totp_ultimo_paso = NULL
             WHERE id = ?'
        )->execute([$id]);
    }

    /**
     * Anti-repetición: acepta el paso de tiempo del código solo si es posterior al último
     * usado. El UPDATE condicional hace que, si llegan dos peticiones con el mismo código
     * a la vez, solo una lo consiga.
     */
    public static function registrarPasoTotp(int $id, int $paso): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuarios SET totp_ultimo_paso = ?
             WHERE id = ? AND (totp_ultimo_paso IS NULL OR totp_ultimo_paso < ?)'
        );
        $stmt->execute([$paso, $id, $paso]);

        return $stmt->rowCount() === 1;
    }

    public static function updateFoto(int $id, string $fotoPath): void
    {
        Database::connection()->prepare('UPDATE usuarios SET foto_path = ? WHERE id = ?')->execute([$fotoPath, $id]);
    }

    public static function setActivo(int $id, bool $activo): void
    {
        Database::connection()->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')->execute([(int) $activo, $id]);
    }

    /**
     * Antes solo cubría asignaciones/movimientos/evidencias/bajas/cargas/bienes creados
     * — se le agregaron jornadas y verificaciones/hallazgos porque esas tablas también
     * referencian al usuario sin CASCADE, así que un usuario cuya única actividad fuera
     * gestionar una jornada de verificación pasaba este chequeo como "libre" y luego el
     * DELETE fallaba igual por la restricción de la base de datos, con un error 500 en
     * vez de un aviso claro.
     */
    public static function estaEnUso(int $id): bool
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT id FROM asignaciones WHERE usuario_responsable_id = ? OR asignado_por = ? LIMIT 1');
        $stmt->execute([$id, $id]);
        if ($stmt->fetchColumn()) {
            return true;
        }

        $consultas = [
            'SELECT id FROM movimientos WHERE responsable_id = ? LIMIT 1',
            'SELECT id FROM formatos_reintegro WHERE registrado_por = ? LIMIT 1',
            'SELECT id FROM formatos_plaqueteo WHERE registrado_por = ? LIMIT 1',
            'SELECT id FROM facturas_administrativas WHERE registrado_por = ? LIMIT 1',
            'SELECT id FROM cartera_envios WHERE registrado_por = ? LIMIT 1',
            'SELECT id FROM bajas_bienes WHERE responsable_id = ? LIMIT 1',
            'SELECT id FROM cargas_masivas WHERE usuario_id = ? LIMIT 1',
            'SELECT id FROM espacio_responsables WHERE usuario_id = ? LIMIT 1',
            'SELECT id FROM bienes WHERE created_by = ? LIMIT 1',
            'SELECT id FROM jornadas_verificacion WHERE creada_por = ? LIMIT 1',
            'SELECT id FROM verificaciones_bienes WHERE usuario_id = ? OR revisada_por = ? LIMIT 1',
            'SELECT id FROM hallazgos_verificacion WHERE reportado_por = ? OR resuelto_por = ? LIMIT 1',
        ];

        foreach ($consultas as $sql) {
            $stmt = $pdo->prepare($sql);
            $parametros = substr_count($sql, '?') === 2 ? [$id, $id] : [$id];
            $stmt->execute($parametros);
            if ($stmt->fetchColumn()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Borrado suave: la fila sigue en la base de datos (recuperable desde la papelera
     * de superusuario), solo deja de aparecer en los listados normales.
     */
    public static function eliminar(int $id, int $eliminadoPor): void
    {
        Database::connection()
            ->prepare('UPDATE usuarios SET eliminado_en = NOW(), eliminado_por = ? WHERE id = ?')
            ->execute([$eliminadoPor, $id]);
    }

    public static function restaurar(int $id): void
    {
        Database::connection()
            ->prepare('UPDATE usuarios SET eliminado_en = NULL, eliminado_por = NULL WHERE id = ?')
            ->execute([$id]);
    }

    public static function existeDocumento(string $documento, ?int $exceptId = null): bool
    {
        return self::existeValor('documento', $documento, $exceptId);
    }

    public static function existeEmail(string $email, ?int $exceptId = null): bool
    {
        return self::existeValor('email', $email, $exceptId);
    }

    private static function existeValor(string $columna, string $valor, ?int $exceptId): bool
    {
        $sql = "SELECT id FROM usuarios WHERE {$columna} = ?";
        $params = [$valor];

        if ($exceptId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public static function rolesParaSelect(bool $incluirSuperusuario = true): array
    {
        $sql = 'SELECT id, nombre FROM roles';
        if (!$incluirSuperusuario) {
            $sql .= " WHERE nombre != 'superusuario'";
        }
        $sql .= ' ORDER BY nombre';

        return Database::connection()->query($sql)->fetchAll();
    }

    /**
     * id => "Nombres Apellidos" de TODOS los usuarios — ver Categoria::mapaIdNombre()
     * para el motivo (resolver referencias históricas en auditoría).
     */
    public static function mapaIdNombreCompleto(): array
    {
        return Database::connection()
            ->query("SELECT id, CONCAT(nombres, ' ', apellidos) FROM usuarios")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** id => nombre de todos los roles — usado para resolver rol_id en la auditoría. */
    public static function mapaRoles(): array
    {
        return Database::connection()->query('SELECT id, nombre FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
