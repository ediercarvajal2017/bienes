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
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.nombre AS rol_nombre, i.activo AS institucion_activa
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             JOIN instituciones i ON i.id = u.institucion_id
             WHERE u.email = ? AND u.eliminado_en IS NULL'
        );
        $stmt->execute([$email]);
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
             WHERE u.documento = ? AND u.institucion_id = ?'
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

    public static function registrarIntentoFallido(int $usuarioId): void
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $pdo = Database::connection();

        $pdo->prepare('UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE id = ?')
            ->execute([$usuarioId]);

        $stmt = $pdo->prepare('SELECT intentos_fallidos FROM usuarios WHERE id = ?');
        $stmt->execute([$usuarioId]);
        $intentos = (int) $stmt->fetchColumn();

        if ($intentos >= $config['login_max_attempts']) {
            $hasta = date('Y-m-d H:i:s', time() + $config['login_lockout_minutes'] * 60);
            $pdo->prepare('UPDATE usuarios SET bloqueado_hasta = ? WHERE id = ?')->execute([$hasta, $usuarioId]);
        }
    }

    public static function registrarLoginExitoso(int $usuarioId): void
    {
        Database::connection()
            ->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_login = NOW() WHERE id = ?')
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

    public static function listar(?int $institucionId = null, ?string $busqueda = null, int $pagina = 1, int $porPagina = 50): array
    {
        [$whereSql, $params] = self::condicionesListado($institucionId, $busqueda);

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

    public static function contarListado(?int $institucionId = null, ?string $busqueda = null): int
    {
        [$whereSql, $params] = self::condicionesListado($institucionId, $busqueda);

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
    private static function condicionesListado(?int $institucionId, ?string $busqueda): array
    {
        $condiciones = ['u.eliminado_en IS NULL'];
        $params = [];

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
}
