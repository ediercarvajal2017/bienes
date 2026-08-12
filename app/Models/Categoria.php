<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Categoria
{
    /**
     * Set base con el que arranca cualquier institución nueva (ver
     * InstitucionController::guardar()) — las mismas 4 categorías que ya estaban en uso
     * real por las instituciones existentes al migrar de un catálogo global a uno propio
     * por institución.
     */
    public const CATEGORIAS_POR_DEFECTO = ['Muebles', 'Sin cartera', 'Otra IE', 'Tecnología'];

    /**
     * Nombre exacto de la categoría protegida (ver esProtegida()) — sin excepción,
     * ni siquiera para el superusuario: nadie puede renombrarla, desactivarla ni
     * eliminarla desde la pantalla normal de categorías, en ninguna institución.
     */
    public const NOMBRE_CATEGORIA_PROTEGIDA = 'Sin cartera';

    public static function esProtegida(array $categoria): bool
    {
        return $categoria['nombre'] === self::NOMBRE_CATEGORIA_PROTEGIDA;
    }

    /**
     * Idempotente a propósito (se salta las que ya existan) — así se puede llamar tanto al
     * crear una institución nueva como desde database/seeders/seed.php, que se espera que
     * se pueda correr más de una vez sin duplicar nada.
     */
    public static function sembrarPorDefecto(int $institucionId): void
    {
        foreach (self::CATEGORIAS_POR_DEFECTO as $nombre) {
            if (!self::existeNombre($institucionId, $nombre)) {
                self::create($institucionId, $nombre);
            }
        }
    }

    public static function all(int $institucionId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM categorias_bienes WHERE institucion_id = ? AND eliminado_en IS NULL ORDER BY nombre'
        );
        $stmt->execute([$institucionId]);

        return $stmt->fetchAll();
    }

    /**
     * id => "nombre (institución)" de TODAS las categorías de TODAS las instituciones,
     * incluidas inactivas/eliminadas — usado por la auditoría, que es la única pantalla
     * que cruza información de todas las instituciones a la vez. El nombre de la
     * institución se agrega para no confundir dos categorías con el mismo nombre en
     * colegios distintos (ej. "Muebles" existe por separado en cada uno).
     */
    public static function mapaIdNombre(): array
    {
        return Database::connection()->query(
            'SELECT c.id, CONCAT(c.nombre, " (", i.nombre, ")") AS etiqueta
             FROM categorias_bienes c
             JOIN instituciones i ON i.id = c.institucion_id'
        )->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function activas(int $institucionId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM categorias_bienes WHERE institucion_id = ? AND activo = 1 AND eliminado_en IS NULL ORDER BY nombre'
        );
        $stmt->execute([$institucionId]);

        return $stmt->fetchAll();
    }

    /**
     * Sin filtro por institución: el id ya identifica una única fila. El llamador es
     * responsable de verificar que pertenece a la institución esperada cuando haga
     * falta (ver CategoriaController::verificarPertenencia()).
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM categorias_bienes WHERE id = ? AND eliminado_en IS NULL');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function create(int $institucionId, string $nombre): int
    {
        $stmt = Database::connection()->prepare('INSERT INTO categorias_bienes (institucion_id, nombre) VALUES (?, ?)');
        $stmt->execute([$institucionId, $nombre]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function renombrar(int $id, string $nombre): void
    {
        Database::connection()->prepare('UPDATE categorias_bienes SET nombre = ? WHERE id = ?')->execute([$nombre, $id]);
    }

    public static function setActivo(int $id, bool $activo): void
    {
        Database::connection()->prepare('UPDATE categorias_bienes SET activo = ? WHERE id = ?')->execute([(int) $activo, $id]);
    }

    public static function estaEnUso(int $id): bool
    {
        $stmt = Database::connection()->prepare('SELECT id FROM bienes WHERE categoria_id = ? LIMIT 1');
        $stmt->execute([$id]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Borrado suave: la fila sigue en la base de datos (recuperable desde la papelera
     * de superusuario), solo deja de aparecer en los listados normales.
     */
    public static function eliminar(int $id, int $eliminadoPor): void
    {
        Database::connection()
            ->prepare('UPDATE categorias_bienes SET eliminado_en = NOW(), eliminado_por = ? WHERE id = ?')
            ->execute([$eliminadoPor, $id]);
    }

    public static function restaurar(int $id): void
    {
        Database::connection()
            ->prepare('UPDATE categorias_bienes SET eliminado_en = NULL, eliminado_por = NULL WHERE id = ?')
            ->execute([$id]);
    }

    public static function existeNombre(int $institucionId, string $nombre, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM categorias_bienes WHERE institucion_id = ? AND nombre = ?';
        $params = [$institucionId, $nombre];

        if ($exceptId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }
}
