<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

final class Espacio
{
    public static function listar(?int $institucionId = null, int $pagina = 1, int $porPagina = 50): array
    {
        $sql = 'SELECT e.*,
                       (SELECT GROUP_CONCAT(CONCAT(u.nombres, " ", u.apellidos) SEPARATOR ", ")
                        FROM espacio_responsables er JOIN usuarios u ON u.id = er.usuario_id
                        WHERE er.espacio_id = e.id) AS responsables_nombres
                FROM espacios e';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' WHERE e.institucion_id = ?';
            $params[] = $institucionId;
        }

        $sql .= ' ORDER BY e.created_at DESC, e.id DESC' . Paginador::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function contarListado(?int $institucionId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM espacios e';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' WHERE e.institucion_id = ?';
            $params[] = $institucionId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public static function responsablesDe(int $espacioId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.nombres, u.apellidos
             FROM espacio_responsables er
             JOIN usuarios u ON u.id = er.usuario_id
             WHERE er.espacio_id = ?
             ORDER BY u.nombres'
        );
        $stmt->execute([$espacioId]);

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM espacios WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function listadoParaSelect(int $institucionId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nombre, codigo FROM espacios WHERE institucion_id = ? AND activo = 1 ORDER BY codigo'
        );
        $stmt->execute([$institucionId]);

        return $stmt->fetchAll();
    }

    /**
     * Todos los espacios de la institución (activos e inactivos, igual alcance que
     * buscarPorCodigoInstitucion) con solo id+codigo — pensado para armar en memoria un
     * mapa código→id una sola vez, en vez de una consulta por cada fila de un archivo
     * de carga masiva.
     */
    /**
     * Espacios donde $usuarioId es responsable — usado para el docente al reportar un
     * hallazgo (bien físico no registrado): solo puede reportarlo en su(s) propio(s)
     * espacio(s), igual que solo ve "sus" bienes en Bien::listarPropios().
     */
    public static function propiosDe(int $usuarioId, int $institucionId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.id, e.nombre, e.codigo
             FROM espacios e
             JOIN espacio_responsables er ON er.espacio_id = e.id
             WHERE er.usuario_id = ? AND e.institucion_id = ? AND e.activo = 1
             ORDER BY e.codigo'
        );
        $stmt->execute([$usuarioId, $institucionId]);

        return $stmt->fetchAll();
    }

    public static function listadoCodigos(int $institucionId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, codigo FROM espacios WHERE institucion_id = ?'
        );
        $stmt->execute([$institucionId]);

        return $stmt->fetchAll();
    }

    public static function create(array $datos): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO espacios (institucion_id, nombre, codigo)
             VALUES (:institucion_id, :nombre, :codigo)'
        );
        $stmt->execute([
            'institucion_id' => $datos['institucion_id'],
            'nombre' => $datos['nombre'],
            'codigo' => $datos['codigo'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $datos): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE espacios SET nombre = :nombre, codigo = :codigo WHERE id = :id'
        );
        $stmt->execute([
            'nombre' => $datos['nombre'],
            'codigo' => $datos['codigo'],
            'id' => $id,
        ]);
    }

    /**
     * Reemplaza por completo la lista de responsables de un espacio (siempre uno o varios).
     */
    public static function sincronizarResponsables(int $espacioId, array $usuarioIds): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM espacio_responsables WHERE espacio_id = ?')->execute([$espacioId]);

            $stmt = $pdo->prepare('INSERT INTO espacio_responsables (espacio_id, usuario_id) VALUES (?, ?)');
            foreach (array_unique($usuarioIds) as $usuarioId) {
                $stmt->execute([$espacioId, $usuarioId]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function setActivo(int $id, bool $activo): void
    {
        Database::connection()->prepare('UPDATE espacios SET activo = ? WHERE id = ?')->execute([(int) $activo, $id]);
    }

    public static function estaEnUso(int $id): bool
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT id FROM asignaciones WHERE espacio_id = ? LIMIT 1');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn()) {
            return true;
        }

        $stmt = $pdo->prepare('SELECT id FROM movimientos WHERE espacio_destino_id = ? LIMIT 1');
        $stmt->execute([$id]);

        return (bool) $stmt->fetchColumn();
    }

    public static function eliminar(int $id): void
    {
        Database::connection()->prepare('DELETE FROM espacios WHERE id = ?')->execute([$id]);
    }

    public static function buscarPorCodigoInstitucion(int $institucionId, string $codigo): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM espacios WHERE institucion_id = ? AND codigo = ?'
        );
        $stmt->execute([$institucionId, $codigo]);

        return $stmt->fetch() ?: null;
    }

    public static function existeCodigo(int $institucionId, string $codigo, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM espacios WHERE institucion_id = ? AND codigo = ?';
        $params = [$institucionId, $codigo];

        if ($exceptId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }
}
