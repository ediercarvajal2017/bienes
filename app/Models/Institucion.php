<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Institucion
{
    /**
     * id => nombre de TODAS las instituciones — ver Categoria::mapaIdNombre() para el
     * motivo (resolver referencias históricas en auditoría).
     */
    public static function mapaIdNombre(): array
    {
        return Database::connection()->query('SELECT id, nombre FROM instituciones')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function all(): array
    {
        return Database::connection()->query(
            'SELECT i.*, p.nombre AS institucion_padre_nombre
             FROM instituciones i
             LEFT JOIN instituciones p ON p.id = i.institucion_padre_id
             ORDER BY i.nombre'
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM instituciones WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function listadoParaSelect(bool $soloActivas = false): array
    {
        $sql = 'SELECT id, nombre, activo FROM instituciones';
        if ($soloActivas) {
            $sql .= ' WHERE activo = 1';
        }
        $sql .= ' ORDER BY nombre';

        return Database::connection()->query($sql)->fetchAll();
    }

    /**
     * La sede principal de $institucionId (ella misma si ya es la principal) más todas
     * sus secciones — el conjunto de sedes que un rector puede tener activas a la vez.
     * La principal siempre queda primero, luego el resto por nombre.
     */
    public static function familiaDe(int $institucionId): array
    {
        $actual = self::find($institucionId);
        if (!$actual) {
            return [];
        }

        $raizId = ($actual['tipo_sede'] === 'seccion' && $actual['institucion_padre_id'])
            ? (int) $actual['institucion_padre_id']
            : (int) $actual['id'];

        $stmt = Database::connection()->prepare(
            "SELECT * FROM instituciones WHERE id = ? OR institucion_padre_id = ?
             ORDER BY (tipo_sede = 'principal') DESC, nombre"
        );
        $stmt->execute([$raizId, $raizId]);

        return $stmt->fetchAll();
    }

    public static function setActivo(int $id, bool $activo): void
    {
        Database::connection()->prepare('UPDATE instituciones SET activo = ? WHERE id = ?')->execute([(int) $activo, $id]);
    }

    public static function create(array $datos): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO instituciones (codigo_dane, nombre, direccion, tipo_sede, institucion_padre_id, email_institucional)
             VALUES (:codigo_dane, :nombre, :direccion, :tipo_sede, :institucion_padre_id, :email_institucional)'
        );
        $stmt->execute($datos);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $datos): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE instituciones SET codigo_dane = :codigo_dane, nombre = :nombre, direccion = :direccion,
             tipo_sede = :tipo_sede, institucion_padre_id = :institucion_padre_id, email_institucional = :email_institucional
             WHERE id = :id'
        );
        $stmt->execute([
            'codigo_dane' => $datos['codigo_dane'],
            'nombre' => $datos['nombre'],
            'direccion' => $datos['direccion'],
            'tipo_sede' => $datos['tipo_sede'],
            'institucion_padre_id' => $datos['institucion_padre_id'],
            'email_institucional' => $datos['email_institucional'],
            'id' => $id,
        ]);
    }

    public static function updateLogo(int $id, string $logoPath): void
    {
        Database::connection()->prepare('UPDATE instituciones SET logo_path = ? WHERE id = ?')->execute([$logoPath, $id]);
    }

    public static function existeCodigoDane(string $codigo, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM instituciones WHERE codigo_dane = ?';
        $params = [$codigo];

        if ($exceptId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }
}
