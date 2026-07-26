<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class PasswordReset
{
    /**
     * Genera un token de un solo uso para restablecer la contraseña de un usuario.
     * Invalida cualquier enlace anterior sin usar del mismo usuario (para que solo el
     * más reciente sirva). Devuelve el token en claro (para el enlace del correo);
     * en la base solo se guarda su hash.
     */
    public static function crear(int $usuarioId, int $minutosValidez = 60): string
    {
        $pdo = Database::connection();
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiraEn = date('Y-m-d H:i:s', time() + $minutosValidez * 60);

        $pdo->prepare('UPDATE password_resets SET usado = 1 WHERE usuario_id = ? AND usado = 0')
            ->execute([$usuarioId]);

        $pdo->prepare('INSERT INTO password_resets (usuario_id, token_hash, expira_en) VALUES (?, ?, ?)')
            ->execute([$usuarioId, $hash, $expiraEn]);

        return $token;
    }

    /**
     * Busca un token vigente (no usado, no vencido) y devuelve sus datos junto con
     * los del usuario dueño, o null si no es válido.
     *
     * La comparación de vencimiento se hace contra la hora actual calculada por PHP
     * (misma zona horaria que se usó al crear el token en crear()), en vez de contra
     * NOW() de MySQL: el servidor de base de datos puede estar en una zona horaria
     * distinta a la de la app (p. ej. Hostinger en UTC vs. la app en America/Bogota),
     * lo que hacía que un token recién creado apareciera "vencido" de inmediato.
     */
    public static function validar(string $token): ?array
    {
        $hash = hash('sha256', $token);

        $stmt = Database::connection()->prepare(
            'SELECT pr.id, pr.usuario_id, u.email, u.nombres, u.apellidos
             FROM password_resets pr
             JOIN usuarios u ON u.id = pr.usuario_id
             WHERE pr.token_hash = ? AND pr.usado = 0 AND pr.expira_en > ?'
        );
        $stmt->execute([$hash, date('Y-m-d H:i:s')]);

        return $stmt->fetch() ?: null;
    }

    public static function marcarUsado(int $id): void
    {
        Database::connection()->prepare('UPDATE password_resets SET usado = 1 WHERE id = ?')->execute([$id]);
    }
}
