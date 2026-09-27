<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Política de verificación en dos pasos por rol (tabla politica_2fa): si es obligatoria
 * y cuántos días de gracia tiene quien todavía no la configuró.
 */
final class PoliticaDosFactores
{
    public const MAX_DIAS_GRACIA = 90;

    /** @return array{obligatorio: bool, dias_gracia: int} */
    public static function deRol(string $rolNombre): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.obligatorio, p.dias_gracia
             FROM politica_2fa p
             JOIN roles r ON r.id = p.rol_id
             WHERE r.nombre = ?'
        );
        $stmt->execute([$rolNombre]);
        $fila = $stmt->fetch();

        return [
            'obligatorio' => $fila !== false && (int) $fila['obligatorio'] === 1,
            'dias_gracia' => $fila !== false ? (int) $fila['dias_gracia'] : 0,
        ];
    }

    /** Una fila por rol (los roles sin fila aparecen como opcionales). */
    public static function listar(): array
    {
        return Database::connection()->query(
            'SELECT r.id AS rol_id, r.nombre AS rol_nombre,
                    COALESCE(p.obligatorio, 0) AS obligatorio, COALESCE(p.dias_gracia, 14) AS dias_gracia,
                    p.actualizado_en,
                    (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id AND u.eliminado_en IS NULL AND u.activo = 1) AS usuarios,
                    (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id AND u.eliminado_en IS NULL AND u.activo = 1
                        AND u.totp_activado_en IS NOT NULL) AS con_2fa
             FROM roles r
             LEFT JOIN politica_2fa p ON p.rol_id = r.id
             ORDER BY r.id'
        )->fetchAll();
    }

    public static function guardar(int $rolId, bool $obligatorio, int $diasGracia, int $actualizadoPor): void
    {
        Database::connection()->prepare(
            'INSERT INTO politica_2fa (rol_id, obligatorio, dias_gracia, actualizado_por, actualizado_en)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE obligatorio = VALUES(obligatorio), dias_gracia = VALUES(dias_gracia),
                                     actualizado_por = VALUES(actualizado_por), actualizado_en = NOW()'
        )->execute([$rolId, (int) $obligatorio, $diasGracia, $actualizadoPor]);
    }
}
