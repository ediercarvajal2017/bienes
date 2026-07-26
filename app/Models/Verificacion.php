<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Verificacion
{
    /**
     * Registra el resultado de verificar un bien dentro de una jornada. Si el bien ya
     * había sido verificado en esta misma jornada (re-escaneo), se actualiza el registro
     * en vez de duplicarlo — gana la verificación más reciente.
     */
    public static function registrar(int $jornadaId, int $bienId, int $usuarioId, string $resultado, ?string $observaciones): void
    {
        Database::connection()->prepare(
            'INSERT INTO verificaciones_bienes (jornada_id, bien_id, usuario_id, resultado, observaciones)
             VALUES (:jornada_id, :bien_id, :usuario_id, :resultado, :observaciones)
             ON DUPLICATE KEY UPDATE usuario_id = VALUES(usuario_id), resultado = VALUES(resultado),
                                     observaciones = VALUES(observaciones), updated_at = CURRENT_TIMESTAMP'
        )->execute([
            'jornada_id' => $jornadaId,
            'bien_id' => $bienId,
            'usuario_id' => $usuarioId,
            'resultado' => $resultado,
            'observaciones' => $observaciones,
        ]);
    }

    public static function deBienEnJornada(int $jornadaId, int $bienId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT v.*, u.nombres, u.apellidos
             FROM verificaciones_bienes v
             JOIN usuarios u ON u.id = v.usuario_id
             WHERE v.jornada_id = ? AND v.bien_id = ?'
        );
        $stmt->execute([$jornadaId, $bienId]);

        return $stmt->fetch() ?: null;
    }

    public static function contarPorResultado(int $jornadaId, string $resultado): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM verificaciones_bienes WHERE jornada_id = ? AND resultado = ?'
        );
        $stmt->execute([$jornadaId, $resultado]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Universo de bienes que la jornada debe cubrir: todos los de la institución que no
     * estén dados de baja (los dados de baja ya salieron de circulación, no aplica
     * verificarlos físicamente).
     */
    public static function contarUniverso(int $institucionId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM bienes WHERE institucion_id = ? AND estado != 'dado_de_baja'"
        );
        $stmt->execute([$institucionId]);

        return (int) $stmt->fetchColumn();
    }

    public static function listarDiscrepancias(int $jornadaId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT v.*, b.codigo_identificacion, b.descripcion, u.nombres, u.apellidos
             FROM verificaciones_bienes v
             JOIN bienes b ON b.id = v.bien_id
             JOIN usuarios u ON u.id = v.usuario_id
             WHERE v.jornada_id = ? AND v.resultado = 'discrepancia'
             ORDER BY v.updated_at DESC"
        );
        $stmt->execute([$jornadaId]);

        return $stmt->fetchAll();
    }

    /**
     * Bienes del universo de la institución que todavía no tienen ningún registro de
     * verificación en esta jornada.
     */
    public static function listarPendientes(int $jornadaId, int $institucionId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT b.id, b.codigo_identificacion, b.descripcion, b.estado,
                    CONCAT(e.codigo, ' - ', e.nombre) AS espacio_nombre
             FROM bienes b
             LEFT JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
             LEFT JOIN espacios e ON e.id = a.espacio_id
             LEFT JOIN verificaciones_bienes v ON v.bien_id = b.id AND v.jornada_id = ?
             WHERE b.institucion_id = ? AND b.estado != 'dado_de_baja' AND v.id IS NULL
             ORDER BY b.codigo_identificacion"
        );
        $stmt->execute([$jornadaId, $institucionId]);

        return $stmt->fetchAll();
    }
}
