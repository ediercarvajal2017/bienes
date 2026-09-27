<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

/**
 * Reportes de baja. Ciclo de vida (migración 031):
 *
 *   [reportar] → pendiente ──aprobar──→ aprobada   (el bien pasa a dado_de_baja)
 *                    └──────rechazar──→ rechazada  (con motivo; el reporte se conserva)
 *
 * La columna "aprobada" (0/1) se mantiene sincronizada por compatibilidad hasta que se
 * retire en una versión posterior.
 */
final class Baja
{
    /**
     * Pendientes primero (requieren atención), y dentro de cada grupo las más
     * recientes primero. $soloDe: id del usuario cuyo listado se limita a sus propios
     * reportes (quien reporta pero no aprueba bajas, p. ej. un docente).
     */
    public static function listar(?int $institucionId = null, int $pagina = 1, int $porPagina = 50, ?int $soloDe = null): array
    {
        [$where, $params] = self::condiciones($institucionId, $soloDe);

        $sql = 'SELECT bb.*, b.descripcion AS bien_descripcion, b.codigo_identificacion, b.institucion_id,
                       c.nombre AS categoria_nombre, u.nombres, u.apellidos,
                       CONCAT(ur.nombres, " ", ur.apellidos) AS resuelta_por_nombre
                FROM bajas_bienes bb
                JOIN bienes b ON b.id = bb.bien_id
                LEFT JOIN categorias_bienes c ON c.id = b.categoria_id
                JOIN usuarios u ON u.id = bb.responsable_id
                LEFT JOIN usuarios ur ON ur.id = bb.resuelta_por'
            . $where
            . " ORDER BY FIELD(bb.estado, 'pendiente', 'aprobada', 'rechazada'), bb.fecha_reporte DESC, bb.id DESC"
            . Paginador::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function contarListado(?int $institucionId = null, ?int $soloDe = null): int
    {
        [$where, $params] = self::condiciones($institucionId, $soloDe);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM bajas_bienes bb JOIN bienes b ON b.id = bb.bien_id' . $where
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** Para el indicador del panel principal: cuántas bajas siguen a la espera de aprobación. */
    public static function contarPendientes(?int $institucionId = null): int
    {
        $sql = "SELECT COUNT(*) FROM bajas_bienes bb JOIN bienes b ON b.id = bb.bien_id WHERE bb.estado = 'pendiente'";
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' AND b.institucion_id = ?';
            $params[] = $institucionId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** ¿El bien ya tiene un reporte de baja esperando aprobación? (evita reportes duplicados) */
    public static function tienePendiente(int $bienId): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT 1 FROM bajas_bienes WHERE bien_id = ? AND estado = 'pendiente' LIMIT 1"
        );
        $stmt->execute([$bienId]);

        return (bool) $stmt->fetchColumn();
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

    /**
     * $datos['verificacion_id'] es opcional: cuando la baja se origina desde una
     * discrepancia reportada en una jornada de verificación (en vez de un reporte suelto),
     * queda el vínculo guardado para poder rastrear después por qué se dio de baja el bien.
     */
    public static function crear(array $datos): int
    {
        $datos['verificacion_id'] ??= null;

        $stmt = Database::connection()->prepare(
            'INSERT INTO bajas_bienes (bien_id, verificacion_id, estado_reportado, ubicacion, responsable_id, descripcion, foto_path)
             VALUES (:bien_id, :verificacion_id, :estado_reportado, :ubicacion, :responsable_id, :descripcion, :foto_path)'
        );
        $stmt->execute($datos);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Aprueba la baja SOLO si sigue pendiente (la condición va en el UPDATE): dos clics o
     * dos personas a la vez no pueden aprobarla dos veces. Devuelve si la aprobó.
     */
    public static function aprobarSiPendiente(int $id, int $usuarioId): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE bajas_bienes SET estado = 'aprobada', aprobada = 1, resuelta_por = ?, resuelta_en = NOW()
             WHERE id = ? AND estado = 'pendiente'"
        );
        $stmt->execute([$usuarioId, $id]);

        return $stmt->rowCount() === 1;
    }

    /** Rechaza la baja SOLO si sigue pendiente; el reporte se conserva con su motivo. */
    public static function rechazarSiPendiente(int $id, int $usuarioId, string $motivo): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE bajas_bienes SET estado = 'rechazada', motivo_rechazo = ?, resuelta_por = ?, resuelta_en = NOW()
             WHERE id = ? AND estado = 'pendiente'"
        );
        $stmt->execute([$motivo, $usuarioId, $id]);

        return $stmt->rowCount() === 1;
    }

    /** @return array{0: string, 1: array<int, int>} */
    private static function condiciones(?int $institucionId, ?int $soloDe): array
    {
        $condiciones = [];
        $params = [];

        if ($institucionId !== null) {
            $condiciones[] = 'b.institucion_id = ?';
            $params[] = $institucionId;
        }

        if ($soloDe !== null) {
            $condiciones[] = 'bb.responsable_id = ?';
            $params[] = $soloDe;
        }

        return [$condiciones === [] ? '' : ' WHERE ' . implode(' AND ', $condiciones), $params];
    }
}
