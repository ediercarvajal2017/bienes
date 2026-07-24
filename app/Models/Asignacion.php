<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Asignacion
{
    public static function activaDe(int $bienId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT a.*, CONCAT(e.codigo, " - ", e.nombre) AS espacio_nombre, e.codigo AS espacio_codigo,
                    (SELECT GROUP_CONCAT(CONCAT(u.nombres, " ", u.apellidos) SEPARATOR ", ")
                     FROM espacio_responsables er JOIN usuarios u ON u.id = er.usuario_id
                     WHERE er.espacio_id = e.id) AS responsables_nombres
             FROM asignaciones a
             LEFT JOIN espacios e ON e.id = a.espacio_id
             WHERE a.bien_id = ? AND a.activa = 1
             ORDER BY a.id DESC LIMIT 1'
        );
        $stmt->execute([$bienId]);

        return $stmt->fetch() ?: null;
    }

    public static function crear(array $datos): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO asignaciones (bien_id, espacio_id, fecha_asignacion, observaciones, activa, asignado_por)
             VALUES (:bien_id, :espacio_id, :fecha_asignacion, :observaciones, 1, :asignado_por)'
        );
        $stmt->execute([
            'bien_id' => $datos['bien_id'],
            'espacio_id' => $datos['espacio_id'],
            'fecha_asignacion' => $datos['fecha_asignacion'],
            'observaciones' => $datos['observaciones'],
            'asignado_por' => $datos['asignado_por'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function cerrarActivasDe(int $bienId): void
    {
        Database::connection()
            ->prepare('UPDATE asignaciones SET activa = 0 WHERE bien_id = ? AND activa = 1')
            ->execute([$bienId]);
    }
}
