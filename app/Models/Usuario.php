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
             WHERE u.email = ?'
        );
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    public static function findByDocumento(string $documento, int $institucionId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, documento, nombres, apellidos, email, activo FROM usuarios WHERE documento = ? AND institucion_id = ?'
        );
        $stmt->execute([$documento, $institucionId]);

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
             WHERE u.id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function listar(?int $institucionId = null, int $pagina = 1, int $porPagina = 50): array
    {
        $sql = 'SELECT u.*, r.nombre AS rol_nombre, c.nombre AS cargo_nombre, i.nombre AS institucion_nombre
                FROM usuarios u
                JOIN roles r ON r.id = u.rol_id
                JOIN cargos c ON c.id = u.cargo_id
                JOIN instituciones i ON i.id = u.institucion_id';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' WHERE u.institucion_id = ?';
            $params[] = $institucionId;
        }

        $sql .= ' ORDER BY u.created_at DESC, u.id DESC' . Paginador::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function contarListado(?int $institucionId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM usuarios u';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' WHERE u.institucion_id = ?';
            $params[] = $institucionId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
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
                JOIN instituciones i ON i.id = u.institucion_id';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' WHERE u.institucion_id = ?';
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
        ];

        foreach ($consultas as $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        }

        return false;
    }

    public static function eliminar(int $id): void
    {
        Database::connection()->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
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
