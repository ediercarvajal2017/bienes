<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Hallazgo
{
    public static function crear(array $datos): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO hallazgos_verificacion (jornada_id, institucion_id, espacio_id, descripcion, foto_path, reportado_por)
             VALUES (:jornada_id, :institucion_id, :espacio_id, :descripcion, :foto_path, :reportado_por)'
        );
        $stmt->execute($datos);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Pendientes de una jornada, con el nombre del espacio y de quien reportó — para la
     * sección "Bienes no registrados encontrados" de /verificaciones/{id}.
     */
    public static function pendientesDeJornada(int $jornadaId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT h.*, CONCAT(e.codigo, " - ", e.nombre) AS espacio_nombre, u.nombres, u.apellidos
             FROM hallazgos_verificacion h
             JOIN espacios e ON e.id = h.espacio_id
             JOIN usuarios u ON u.id = h.reportado_por
             WHERE h.jornada_id = ? AND h.estado = "pendiente"
             ORDER BY h.created_at DESC'
        );
        $stmt->execute([$jornadaId]);

        return $stmt->fetchAll();
    }

    public static function contarPendientesDeJornada(int $jornadaId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM hallazgos_verificacion WHERE jornada_id = ? AND estado = "pendiente"');
        $stmt->execute([$jornadaId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Devuelve el hallazgo (con nombre de espacio y de quien lo reportó) si sigue
     * pendiente y, cuando se indica $institucionId (todos menos el superusuario),
     * si pertenece a esa institución — para que nadie formalice o descarte un hallazgo
     * ajeno adivinando el id en la URL.
     */
    public static function pendienteAccesible(int $id, ?int $institucionId): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $sql = 'SELECT h.*, CONCAT(e.codigo, " - ", e.nombre) AS espacio_nombre, u.nombres, u.apellidos
                FROM hallazgos_verificacion h
                JOIN espacios e ON e.id = h.espacio_id
                JOIN usuarios u ON u.id = h.reportado_por
                WHERE h.id = ? AND h.estado = "pendiente"';
        $params = [$id];

        if ($institucionId !== null) {
            $sql .= ' AND h.institucion_id = ?';
            $params[] = $institucionId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() ?: null;
    }

    public static function marcarRegistrado(int $id, int $bienId, int $resueltoPor): void
    {
        Database::connection()->prepare(
            'UPDATE hallazgos_verificacion SET estado = "registrado", bien_id = ?, resuelto_por = ?, resuelto_en = NOW() WHERE id = ?'
        )->execute([$bienId, $resueltoPor, $id]);
    }

    public static function marcarDescartado(int $id, int $resueltoPor, ?string $observaciones): void
    {
        Database::connection()->prepare(
            'UPDATE hallazgos_verificacion SET estado = "descartado", observaciones_resolucion = ?, resuelto_por = ?, resuelto_en = NOW() WHERE id = ?'
        )->execute([$observaciones, $resueltoPor, $id]);
    }
}
