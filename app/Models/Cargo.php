<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Cargo
{
    public static function activos(): array
    {
        return Database::connection()->query('SELECT * FROM cargos WHERE activo = 1 AND eliminado_en IS NULL ORDER BY nombre')->fetchAll();
    }

    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM cargos WHERE eliminado_en IS NULL ORDER BY nombre')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM cargos WHERE id = ? AND eliminado_en IS NULL');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function findByNombre(string $nombre): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM cargos WHERE nombre = ? AND activo = 1 AND eliminado_en IS NULL');
        $stmt->execute([$nombre]);

        return $stmt->fetch() ?: null;
    }

    public static function create(string $nombre): int
    {
        $stmt = Database::connection()->prepare('INSERT INTO cargos (nombre) VALUES (?)');
        $stmt->execute([$nombre]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function renombrar(int $id, string $nombre): void
    {
        Database::connection()->prepare('UPDATE cargos SET nombre = ? WHERE id = ?')->execute([$nombre, $id]);
    }

    public static function setActivo(int $id, bool $activo): void
    {
        Database::connection()->prepare('UPDATE cargos SET activo = ? WHERE id = ?')->execute([(int) $activo, $id]);
    }

    public static function estaEnUso(int $id): bool
    {
        $stmt = Database::connection()->prepare('SELECT id FROM usuarios WHERE cargo_id = ? LIMIT 1');
        $stmt->execute([$id]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Borrado suave: la fila sigue en la base de datos (recuperable desde la papelera
     * de superusuario), solo deja de aparecer en los listados normales.
     */
    public static function eliminar(int $id, int $eliminadoPor): void
    {
        Database::connection()
            ->prepare('UPDATE cargos SET eliminado_en = NOW(), eliminado_por = ? WHERE id = ?')
            ->execute([$eliminadoPor, $id]);
    }

    public static function restaurar(int $id): void
    {
        Database::connection()
            ->prepare('UPDATE cargos SET eliminado_en = NULL, eliminado_por = NULL WHERE id = ?')
            ->execute([$id]);
    }

    public static function existeNombre(string $nombre, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM cargos WHERE nombre = ?';
        $params = [$nombre];

        if ($exceptId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }
}
