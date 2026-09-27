<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;

/**
 * Límite de intentos guardado en el servidor (tabla intentos_acceso), por dirección IP y
 * por "clave" (el correo o documento intentado, guardado como hash). A diferencia del
 * límite anterior en la sesión, no se salta borrando la cookie.
 *
 * Las ventanas son deslizantes: se cuentan los intentos de los últimos N minutos.
 */
final class LimiteIntentos
{
    public static function ip(): string
    {
        // REMOTE_ADDR es la única fuente confiable: cabeceras como X-Forwarded-For las
        // puede escribir cualquiera.
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'), 0, 45);
    }

    public static function registrar(string $tipo, ?string $clave = null): void
    {
        Database::connection()
            ->prepare('INSERT INTO intentos_acceso (tipo, ip, clave, creado_en) VALUES (?, ?, ?, NOW())')
            ->execute([$tipo, self::ip(), $clave !== null ? self::hash($clave) : null]);

        // Limpieza ocasional (1 de cada 50 registros) de lo que ya no cuenta para nada.
        if (random_int(1, 50) === 1) {
            Database::connection()->exec('DELETE FROM intentos_acceso WHERE creado_en < NOW() - INTERVAL 1 DAY');
        }
    }

    /** Intentos de este tipo desde la IP actual en los últimos $minutos. */
    public static function desdeEstaIp(string $tipo, int $minutos): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM intentos_acceso WHERE tipo = ? AND ip = ? AND creado_en >= NOW() - INTERVAL ? MINUTE'
        );
        $stmt->execute([$tipo, self::ip(), $minutos]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Intentos de este tipo sobre esta clave en los últimos $minutos; con $soloEstaIp,
     * solo los hechos desde la IP actual.
     */
    public static function sobreClave(string $tipo, string $clave, int $minutos, bool $soloEstaIp = false): int
    {
        $sql = 'SELECT COUNT(*) FROM intentos_acceso WHERE tipo = ? AND clave = ? AND creado_en >= NOW() - INTERVAL ? MINUTE';
        $params = [$tipo, self::hash($clave), $minutos];
        if ($soloEstaIp) {
            $sql .= ' AND ip = ?';
            $params[] = self::ip();
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** Borra los intentos fallidos de esta clave desde la IP actual (tras un acceso exitoso). */
    public static function limpiar(string $tipo, string $clave): void
    {
        Database::connection()
            ->prepare('DELETE FROM intentos_acceso WHERE tipo = ? AND clave = ? AND ip = ?')
            ->execute([$tipo, self::hash($clave), self::ip()]);
    }

    private static function hash(string $clave): string
    {
        return hash('sha256', mb_strtolower(trim($clave)));
    }
}
