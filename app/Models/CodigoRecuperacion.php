<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Códigos de recuperación de la verificación en dos pasos: 10 por usuario, de un solo
 * uso, para entrar si se pierde el teléfono. Solo se guarda su hash SHA-256 (tienen 50
 * bits aleatorios y el intento está limitado, así que no hace falta un hash lento).
 */
final class CodigoRecuperacion
{
    public const CANTIDAD = 10;
    /** Sin 0/O ni 1/I/L, para que no se confundan al copiarlos a mano. */
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Borra los anteriores y crea códigos nuevos. Devuelve los códigos en claro: es la
     * ÚNICA vez que existen así, para mostrárselos al usuario.
     *
     * @return list<string>
     */
    public static function regenerar(int $usuarioId): array
    {
        $codigos = [];
        for ($i = 0; $i < self::CANTIDAD; $i++) {
            $codigos[] = self::aleatorio(5) . '-' . self::aleatorio(5);
        }

        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM usuario_codigos_recuperacion WHERE usuario_id = ?')->execute([$usuarioId]);
        $insertar = $pdo->prepare(
            'INSERT INTO usuario_codigos_recuperacion (usuario_id, codigo_hash, creado_en) VALUES (?, ?, NOW())'
        );
        foreach ($codigos as $codigo) {
            $insertar->execute([$usuarioId, self::hash($codigo)]);
        }

        return $codigos;
    }

    /**
     * Si el código es uno vigente del usuario, lo marca como usado (una sola vez, aunque
     * lleguen dos peticiones a la vez) y devuelve true.
     */
    public static function usar(int $usuarioId, string $codigo): bool
    {
        $normalizado = self::normalizar($codigo);
        if (strlen($normalizado) !== 10) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE usuario_codigos_recuperacion SET usado_en = NOW()
             WHERE usuario_id = ? AND codigo_hash = ? AND usado_en IS NULL
             LIMIT 1'
        );
        $stmt->execute([$usuarioId, self::hash($normalizado)]);

        return $stmt->rowCount() === 1;
    }

    public static function disponibles(int $usuarioId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM usuario_codigos_recuperacion WHERE usuario_id = ? AND usado_en IS NULL'
        );
        $stmt->execute([$usuarioId]);

        return (int) $stmt->fetchColumn();
    }

    public static function borrarDe(int $usuarioId): void
    {
        Database::connection()->prepare('DELETE FROM usuario_codigos_recuperacion WHERE usuario_id = ?')
            ->execute([$usuarioId]);
    }

    /** ¿Tiene forma de código de recuperación (y no de código de 6 dígitos)? */
    public static function pareceCodigo(string $texto): bool
    {
        return strlen(self::normalizar($texto)) === 10 && !ctype_digit(self::normalizar($texto));
    }

    private static function normalizar(string $codigo): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo) ?? '');
    }

    private static function hash(string $codigo): string
    {
        return hash('sha256', self::normalizar($codigo));
    }

    private static function aleatorio(int $largo): string
    {
        $texto = '';
        for ($i = 0; $i < $largo; $i++) {
            $texto .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        return $texto;
    }
}
