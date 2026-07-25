<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

/**
 * Biblioteca digital de evidencia: formatos de plaqueteo realizados por la Alcaldía.
 */
final class FormatoPlaqueteo
{
    public static function create(array $datos): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO formatos_plaqueteo (institucion_id, fecha_plaqueteo, funcionario_asistio, descripcion, archivo_path, registrado_por)
             VALUES (:institucion_id, :fecha_plaqueteo, :funcionario_asistio, :descripcion, :archivo_path, :registrado_por)'
        );
        $stmt->execute($datos);

        return (int) Database::connection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM formatos_plaqueteo WHERE id = ?');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public static function actualizar(int $id, array $datos): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE formatos_plaqueteo
                SET fecha_plaqueteo = :fecha_plaqueteo, funcionario_asistio = :funcionario_asistio,
                    descripcion = :descripcion, archivo_path = :archivo_path
              WHERE id = :id'
        );
        $stmt->execute($datos + ['id' => $id]);
    }

    public static function eliminar(int $id): void
    {
        Database::connection()->prepare('DELETE FROM formatos_plaqueteo WHERE id = ?')->execute([$id]);
    }

    public static function listar(?int $institucionId = null, int $pagina = 1, int $porPagina = 50): array
    {
        $sql = 'SELECT f.*, i.nombre AS institucion_nombre,
                       u.nombres AS registrado_por_nombres, u.apellidos AS registrado_por_apellidos
                FROM formatos_plaqueteo f
                JOIN instituciones i ON i.id = f.institucion_id
                LEFT JOIN usuarios u ON u.id = f.registrado_por';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' WHERE f.institucion_id = ?';
            $params[] = $institucionId;
        }

        $sql .= ' ORDER BY f.fecha_plaqueteo DESC, f.id DESC' . Paginador::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function contarListado(?int $institucionId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM formatos_plaqueteo f';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' WHERE f.institucion_id = ?';
            $params[] = $institucionId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
