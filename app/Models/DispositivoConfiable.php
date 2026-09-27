<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * "Confiar en este dispositivo": tras pasar la verificación en dos pasos, el navegador
 * guarda una cookie con un token aleatorio y durante DIAS no se le vuelve a pedir el
 * código. En la base solo queda el hash del token; el usuario ve sus dispositivos en
 * "Mi cuenta" y puede revocarlos.
 */
final class DispositivoConfiable
{
    public const DIAS = 30;

    /** Crea el registro y devuelve el token en claro (va solo en la cookie). */
    public static function crear(int $usuarioId, ?string $agente, ?string $ip): string
    {
        $token = bin2hex(random_bytes(32));

        Database::connection()->prepare(
            'INSERT INTO dispositivos_confiables (usuario_id, token_hash, agente, ip, creado_en, ultimo_uso_en, expira_en)
             VALUES (?, ?, ?, ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL ' . self::DIAS . ' DAY))'
        )->execute([$usuarioId, hash('sha256', $token), $agente !== null ? mb_substr($agente, 0, 255) : null, $ip]);

        return $token;
    }

    /** ¿El token es un dispositivo vigente de ese usuario? Si lo es, registra el uso. */
    public static function esValido(int $usuarioId, string $token): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id FROM dispositivos_confiables
             WHERE usuario_id = ? AND token_hash = ? AND revocado_en IS NULL AND expira_en > NOW()'
        );
        $stmt->execute([$usuarioId, hash('sha256', $token)]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            return false;
        }

        // Aparte de la búsqueda: MySQL no cuenta como "afectada" una fila cuyo valor no
        // cambia, así que con UPDATE + rowCount() un reingreso en el mismo segundo fallaba.
        $pdo->prepare('UPDATE dispositivos_confiables SET ultimo_uso_en = NOW() WHERE id = ?')->execute([$id]);

        return true;
    }

    /** Vigentes del usuario, el más reciente primero. */
    public static function listarDe(int $usuarioId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, agente, ip, creado_en, ultimo_uso_en, expira_en
             FROM dispositivos_confiables
             WHERE usuario_id = ? AND revocado_en IS NULL AND expira_en > NOW()
             ORDER BY COALESCE(ultimo_uso_en, creado_en) DESC'
        );
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll();
    }

    public static function revocar(int $id, int $usuarioId): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE dispositivos_confiables SET revocado_en = NOW()
             WHERE id = ? AND usuario_id = ? AND revocado_en IS NULL'
        );
        $stmt->execute([$id, $usuarioId]);

        return $stmt->rowCount() === 1;
    }

    public static function revocarTodosDe(int $usuarioId): int
    {
        $stmt = Database::connection()->prepare(
            'UPDATE dispositivos_confiables SET revocado_en = NOW() WHERE usuario_id = ? AND revocado_en IS NULL'
        );
        $stmt->execute([$usuarioId]);

        return $stmt->rowCount();
    }

    /** "Chrome en Windows" a partir del agente del navegador (solo para mostrarlo). */
    public static function describirAgente(?string $agente): string
    {
        $agente = (string) $agente;
        $navegador = match (true) {
            str_contains($agente, 'Edg/') => 'Edge',
            str_contains($agente, 'OPR/') => 'Opera',
            str_contains($agente, 'Firefox/') => 'Firefox',
            str_contains($agente, 'Chrome/') => 'Chrome',
            str_contains($agente, 'Safari/') => 'Safari',
            default => 'Navegador',
        };
        $sistema = match (true) {
            str_contains($agente, 'Android') => 'Android',
            str_contains($agente, 'iPhone'), str_contains($agente, 'iPad') => 'iOS',
            str_contains($agente, 'Windows') => 'Windows',
            str_contains($agente, 'Mac OS') => 'macOS',
            str_contains($agente, 'Linux') => 'Linux',
            default => 'sistema desconocido',
        };

        return "{$navegador} en {$sistema}";
    }
}
