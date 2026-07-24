<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

final class Baja
{
    /**
     * Pendientes primero (requieren atención), y dentro de cada grupo las más
     * recientes primero.
     */
    public static function listar(?int $institucionId = null, int $pagina = 1, int $porPagina = 50): array
    {
        $sql = 'SELECT bb.*, b.descripcion AS bien_descripcion, b.codigo_identificacion, b.institucion_id,
                       u.nombres, u.apellidos
                FROM bajas_bienes bb
                JOIN bienes b ON b.id = bb.bien_id
                JOIN usuarios u ON u.id = bb.responsable_id';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' WHERE b.institucion_id = ?';
            $params[] = $institucionId;
        }

        $sql .= ' ORDER BY bb.aprobada ASC, bb.fecha_reporte DESC, bb.id DESC' . Paginador::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function contarListado(?int $institucionId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM bajas_bienes bb JOIN bienes b ON b.id = bb.bien_id';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' WHERE b.institucion_id = ?';
            $params[] = $institucionId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT bb.*, b.institucion_id
             FROM bajas_bienes bb
             JOIN bienes b ON b.id = bb.bien_id
             WHERE bb.id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function crear(array $datos): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO bajas_bienes (bien_id, estado_reportado, ubicacion, responsable_id, descripcion, foto_path)
             VALUES (:bien_id, :estado_reportado, :ubicacion, :responsable_id, :descripcion, :foto_path)'
        );
        $stmt->execute($datos);

        return (int) Database::connection()->lastInsertId();
    }

    public static function aprobar(int $id): void
    {
        Database::connection()->prepare('UPDATE bajas_bienes SET aprobada = 1 WHERE id = ?')->execute([$id]);
    }

    public static function eliminar(int $id): void
    {
        Database::connection()->prepare('DELETE FROM bajas_bienes WHERE id = ?')->execute([$id]);
    }
}
