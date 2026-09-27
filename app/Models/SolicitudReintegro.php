<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

/**
 * Solicitudes de reintegro hechas por quien tiene un bien a cargo (docente). Ciclo de vida
 * (migración 032): pendiente → aprobada | rechazada | cancelada. Las transiciones se hacen
 * con UPDATE condicional (solo si sigue pendiente), así que una solicitud no se resuelve
 * dos veces.
 */
final class SolicitudReintegro
{
    public const ESTADOS = ['pendiente', 'aprobada', 'rechazada', 'cancelada'];

    public static function crear(int $bienId, int $institucionId, int $solicitadoPor, string $motivo): int
    {
        Database::connection()
            ->prepare('INSERT INTO solicitudes_reintegro (bien_id, institucion_id, solicitado_por, motivo) VALUES (?, ?, ?, ?)')
            ->execute([$bienId, $institucionId, $solicitadoPor, $motivo]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(self::sqlBase() . ' WHERE s.id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function tienePendiente(int $bienId): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT 1 FROM solicitudes_reintegro WHERE bien_id = ? AND estado = 'pendiente' LIMIT 1"
        );
        $stmt->execute([$bienId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Pendientes primero; $soloDe limita a las solicitudes de un usuario (el docente ve las
     * suyas; quien aprueba ve todas las de la institución).
     */
    public static function listar(?int $institucionId, ?int $soloDe, ?string $estado, int $pagina = 1, int $porPagina = 25): array
    {
        [$where, $params] = self::condiciones($institucionId, $soloDe, $estado);

        $stmt = Database::connection()->prepare(
            self::sqlBase() . $where
            . " ORDER BY FIELD(s.estado, 'pendiente', 'aprobada', 'rechazada', 'cancelada'), s.created_at DESC, s.id DESC"
            . Paginador::limitSql($pagina, $porPagina)
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function contar(?int $institucionId, ?int $soloDe, ?string $estado): int
    {
        [$where, $params] = self::condiciones($institucionId, $soloDe, $estado);

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM solicitudes_reintegro s' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public static function aprobarSiPendiente(int $id, int $usuarioId, int $movimientoId, ?string $respuesta): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE solicitudes_reintegro
             SET estado = 'aprobada', resuelta_por = ?, resuelta_en = NOW(), movimiento_id = ?, respuesta = ?
             WHERE id = ? AND estado = 'pendiente'"
        );
        $stmt->execute([$usuarioId, $movimientoId, $respuesta, $id]);

        return $stmt->rowCount() === 1;
    }

    public static function rechazarSiPendiente(int $id, int $usuarioId, string $respuesta): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE solicitudes_reintegro SET estado = 'rechazada', resuelta_por = ?, resuelta_en = NOW(), respuesta = ?
             WHERE id = ? AND estado = 'pendiente'"
        );
        $stmt->execute([$usuarioId, $respuesta, $id]);

        return $stmt->rowCount() === 1;
    }

    /** Solo quien la pidió puede cancelarla, y solo mientras siga pendiente. */
    public static function cancelarSiPendiente(int $id, int $solicitadoPor): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE solicitudes_reintegro SET estado = 'cancelada', resuelta_por = ?, resuelta_en = NOW()
             WHERE id = ? AND solicitado_por = ? AND estado = 'pendiente'"
        );
        $stmt->execute([$solicitadoPor, $id, $solicitadoPor]);

        return $stmt->rowCount() === 1;
    }

    private static function sqlBase(): string
    {
        return 'SELECT s.*, b.codigo_identificacion, b.descripcion AS bien_descripcion, b.estado AS bien_estado,
                       b.foto_path, b.qr_token,
                       CONCAT(us.nombres, " ", us.apellidos) AS solicitante_nombre,
                       CONCAT(ur.nombres, " ", ur.apellidos) AS resuelta_por_nombre
                FROM solicitudes_reintegro s
                JOIN bienes b ON b.id = s.bien_id
                JOIN usuarios us ON us.id = s.solicitado_por
                LEFT JOIN usuarios ur ON ur.id = s.resuelta_por';
    }

    /** @return array{0: string, 1: array<int, int|string>} */
    private static function condiciones(?int $institucionId, ?int $soloDe, ?string $estado): array
    {
        $condiciones = [];
        $params = [];

        if ($institucionId !== null) {
            $condiciones[] = 's.institucion_id = ?';
            $params[] = $institucionId;
        }
        if ($soloDe !== null) {
            $condiciones[] = 's.solicitado_por = ?';
            $params[] = $soloDe;
        }
        if ($estado !== null && in_array($estado, self::ESTADOS, true)) {
            $condiciones[] = 's.estado = ?';
            $params[] = $estado;
        }

        return [$condiciones === [] ? '' : ' WHERE ' . implode(' AND ', $condiciones), $params];
    }
}
