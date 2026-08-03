<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

final class LoteReintegro
{
    public static function create(array $datos): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO lotes_reintegro (institucion_id, fecha, destino_texto, observaciones, registrado_por)
             VALUES (:institucion_id, :fecha, :destino_texto, :observaciones, :registrado_por)'
        );
        $stmt->execute($datos);

        return (int) Database::connection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT l.*, i.nombre AS institucion_nombre,
                    u.nombres AS registrado_por_nombres, u.apellidos AS registrado_por_apellidos
             FROM lotes_reintegro l
             JOIN instituciones i ON i.id = l.institucion_id
             JOIN usuarios u ON u.id = l.registrado_por
             WHERE l.id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function listar(?int $institucionId = null, int $pagina = 1, int $porPagina = 50): array
    {
        $sql = 'SELECT l.*, i.nombre AS institucion_nombre,
                       u.nombres AS registrado_por_nombres, u.apellidos AS registrado_por_apellidos,
                       COUNT(m.id) AS total_bienes
                FROM lotes_reintegro l
                JOIN instituciones i ON i.id = l.institucion_id
                JOIN usuarios u ON u.id = l.registrado_por
                LEFT JOIN movimientos m ON m.lote_reintegro_id = l.id';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' WHERE l.institucion_id = ?';
            $params[] = $institucionId;
        }

        $sql .= ' GROUP BY l.id ORDER BY l.fecha DESC, l.id DESC' . Paginador::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function contarListado(?int $institucionId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM lotes_reintegro l';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' WHERE l.institucion_id = ?';
            $params[] = $institucionId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public static function asignarMovimientos(int $loteId, array $movimientoIds): void
    {
        if (empty($movimientoIds)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($movimientoIds), '?'));
        $stmt = Database::connection()->prepare(
            "UPDATE movimientos SET lote_reintegro_id = ? WHERE id IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$loteId], $movimientoIds));
    }

    public static function bienesDe(int $loteId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT m.fecha AS fecha_reintegro, m.destino_texto, m.observaciones,
                    b.codigo_identificacion, b.descripcion, b.marca, b.valor,
                    CONCAT(eo.codigo, " - ", eo.nombre) AS espacio_origen_nombre
             FROM movimientos m
             JOIN bienes b ON b.id = m.bien_id
             LEFT JOIN espacios eo ON eo.id = m.espacio_origen_id
             WHERE m.lote_reintegro_id = ?
             ORDER BY b.codigo_identificacion'
        );
        $stmt->execute([$loteId]);

        return $stmt->fetchAll();
    }
}
