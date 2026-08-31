<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

final class Movimiento
{
    public static function historialDe(int $bienId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT m.*, u.nombres, u.apellidos, CONCAT(e.codigo, " - ", e.nombre) AS espacio_destino_nombre
             FROM movimientos m
             JOIN usuarios u ON u.id = m.responsable_id
             LEFT JOIN espacios e ON e.id = m.espacio_destino_id
             WHERE m.bien_id = ?
             ORDER BY m.fecha DESC, m.id DESC'
        );
        $stmt->execute([$bienId]);

        return $stmt->fetchAll();
    }

    /**
     * Solo bienes cuyo estado ACTUAL sigue siendo 'reintegrado' (si un bien se reintegró
     * y luego se volvió a asignar, ya no debe aparecer aquí) y solo su reintegro más
     * reciente (por si el mismo bien se ha reintegrado más de una vez en su historia).
     */
    public static function historialReintegros(?int $institucionId = null): array
    {
        $sql = 'SELECT m.fecha AS fecha_reintegro, m.destino_texto, m.observaciones,
                       b.codigo_identificacion, b.descripcion, b.valor,
                       u.nombres AS responsable_nombres, u.apellidos AS responsable_apellidos
                FROM movimientos m
                JOIN bienes b ON b.id = m.bien_id
                JOIN usuarios u ON u.id = m.responsable_id
                WHERE m.tipo = "reintegro"
                  AND b.estado = "reintegrado"
                  AND m.id = (
                      SELECT MAX(m2.id) FROM movimientos m2
                      WHERE m2.bien_id = m.bien_id AND m2.tipo = "reintegro"
                  )';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' AND b.institucion_id = ?';
            $params[] = $institucionId;
        }

        $sql .= ' ORDER BY m.fecha DESC, m.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Reintegros ya registrados pero que todavía no fueron agrupados en ningún lote
     * (candidatos para la pantalla "Generar lote de reintegro"). Igual que en
     * historialReintegros(): si un bien se reintegró, se volvió a asignar y se reintegró
     * de nuevo, el reintegro viejo queda obsoleto — solo cuenta el más reciente, y solo si
     * el bien sigue actualmente en estado 'reintegrado' (si no, ya se reasignó otra vez).
     */
    public static function pendientesDeLote(?int $institucionId = null, int $pagina = 1, int $porPagina = 50): array
    {
        [$sql, $params] = self::sqlPendientesDeLote($institucionId);
        $sql .= ' ORDER BY m.fecha DESC, m.id DESC' . Paginador::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function contarPendientesDeLote(?int $institucionId = null): int
    {
        [$sql, $params] = self::sqlPendientesDeLote($institucionId, true);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Sin paginar: usado internamente para validar qué movimientos seleccionados por el
     * usuario siguen siendo candidatos legítimos al generar un lote (no para mostrar).
     */
    public static function pendientesDeLoteTodos(?int $institucionId = null): array
    {
        [$sql, $params] = self::sqlPendientesDeLote($institucionId);
        $sql .= ' ORDER BY m.fecha DESC, m.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private static function sqlPendientesDeLote(?int $institucionId, bool $paraContar = false): array
    {
        $sql = 'SELECT ' . ($paraContar ? 'COUNT(*)' : 'm.id, m.fecha AS fecha_reintegro, m.destino_texto, m.observaciones,
                       b.institucion_id, b.codigo_identificacion, b.descripcion, b.valor, b.foto_path,
                       CONCAT(eo.codigo, " - ", eo.nombre) AS espacio_origen_nombre,
                       CONCAT(u.nombres, " ", u.apellidos) AS responsable_nombre') . '
                FROM movimientos m
                JOIN bienes b ON b.id = m.bien_id
                LEFT JOIN espacios eo ON eo.id = m.espacio_origen_id
                LEFT JOIN usuarios u ON u.id = m.responsable_id
                WHERE m.tipo = "reintegro"
                  AND m.lote_reintegro_id IS NULL
                  AND b.estado = "reintegrado"
                  AND m.id = (
                      SELECT MAX(m2.id) FROM movimientos m2
                      WHERE m2.bien_id = m.bien_id AND m2.tipo = "reintegro"
                  )';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' AND b.institucion_id = ?';
            $params[] = $institucionId;
        }

        return [$sql, $params];
    }

    /**
     * 'lote_reintegro_id' y 'espacio_origen_id' solo aplican a ciertos movimientos;
     * se completan con null por defecto para no romper a los llamadores que no los envían.
     */
    public static function crear(array $datos): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO movimientos (bien_id, tipo, lote_reintegro_id, fecha, responsable_id, espacio_origen_id, espacio_destino_id, destino_texto, observaciones)
             VALUES (:bien_id, :tipo, :lote_reintegro_id, :fecha, :responsable_id, :espacio_origen_id, :espacio_destino_id, :destino_texto, :observaciones)'
        );
        $stmt->execute($datos + ['lote_reintegro_id' => null, 'espacio_origen_id' => null]);

        return (int) Database::connection()->lastInsertId();
    }
}
