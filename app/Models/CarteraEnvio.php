<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

/**
 * Biblioteca digital de evidencia: registro manual de cada cartera de bienes que la
 * institución remitió por correo (fuera del sistema) a la Alcaldía. El sistema no
 * envía el correo; solo conserva el soporte para que la información no pueda
 * alterarse ni perderse.
 */
final class CarteraEnvio
{
    public static function create(array $datos): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cartera_envios
                (institucion_id, archivo_path, correo_remitente, nombre_funcionario, fecha_envio, registrado_por)
             VALUES
                (:institucion_id, :archivo_path, :correo_remitente, :nombre_funcionario, :fecha_envio, :registrado_por)'
        );
        $stmt->execute($datos);

        return (int) Database::connection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM cartera_envios WHERE id = ?');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public static function actualizar(int $id, array $datos): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE cartera_envios
                SET correo_remitente = :correo_remitente, nombre_funcionario = :nombre_funcionario,
                    fecha_envio = :fecha_envio, archivo_path = :archivo_path
              WHERE id = :id'
        );
        $stmt->execute($datos + ['id' => $id]);
    }

    public static function eliminar(int $id): void
    {
        Database::connection()->prepare('DELETE FROM cartera_envios WHERE id = ?')->execute([$id]);
    }

    public static function listar(?int $institucionId = null, int $pagina = 1, int $porPagina = 50): array
    {
        $sql = 'SELECT ce.*, i.nombre AS institucion_nombre,
                       u.nombres AS registrado_por_nombres, u.apellidos AS registrado_por_apellidos
                FROM cartera_envios ce
                JOIN instituciones i ON i.id = ce.institucion_id
                LEFT JOIN usuarios u ON u.id = ce.registrado_por';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' WHERE ce.institucion_id = ?';
            $params[] = $institucionId;
        }

        $sql .= ' ORDER BY ce.fecha_envio DESC, ce.id DESC' . Paginador::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function contarListado(?int $institucionId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM cartera_envios ce';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' WHERE ce.institucion_id = ?';
            $params[] = $institucionId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
