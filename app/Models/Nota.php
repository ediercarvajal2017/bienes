<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Notas rápidas de cada usuario (ícono de la barra superior). Son PRIVADAS: todas las
 * consultas filtran por usuario_id, así que nadie (ni el rector ni el superusuario) lee o
 * cambia la nota de otro aunque conozca su id. No pasan por la auditoría ni por la
 * exportación completa, por ser contenido personal.
 */
final class Nota
{
    public const COLORES = ['amarillo', 'verde', 'rosado', 'azul', 'morado'];
    public const MAX_CARACTERES = 2000;
    public const MAX_POR_USUARIO = 100;

    /**
     * @return list<array<string, mixed>> la más reciente primero
     */
    public static function delUsuario(int $usuarioId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, texto, color, updated_at FROM notas WHERE usuario_id = ? ORDER BY updated_at DESC, id DESC'
        );
        $stmt->execute([$usuarioId]);

        return array_values($stmt->fetchAll());
    }

    public static function contar(int $usuarioId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM notas WHERE usuario_id = ?');
        $stmt->execute([$usuarioId]);

        return (int) $stmt->fetchColumn();
    }

    public static function buscar(int $id, int $usuarioId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, texto, color, updated_at FROM notas WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, $usuarioId]);

        return $stmt->fetch() ?: null;
    }

    public static function crear(int $usuarioId, string $texto, string $color): int
    {
        Database::connection()
            ->prepare('INSERT INTO notas (usuario_id, texto, color) VALUES (?, ?, ?)')
            ->execute([$usuarioId, $texto, $color]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Cambia el texto, el color o ambos (null = no se toca). El llamador ya comprobó con
     * buscar() que la nota es del usuario; el filtro por usuario_id se repite por si acaso.
     */
    public static function actualizar(int $id, int $usuarioId, ?string $texto, ?string $color): void
    {
        $campos = [];
        $params = [];
        if ($texto !== null) {
            $campos[] = 'texto = ?';
            $params[] = $texto;
        }
        if ($color !== null) {
            $campos[] = 'color = ?';
            $params[] = $color;
        }
        if ($campos === []) {
            return;
        }

        array_push($params, $id, $usuarioId);
        Database::connection()
            ->prepare('UPDATE notas SET ' . implode(', ', $campos) . ' WHERE id = ? AND usuario_id = ?')
            ->execute($params);
    }

    /** @return bool false si la nota no existe o no es del usuario */
    public static function eliminar(int $id, int $usuarioId): bool
    {
        $stmt = Database::connection()->prepare('DELETE FROM notas WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, $usuarioId]);

        return $stmt->rowCount() > 0;
    }
}
