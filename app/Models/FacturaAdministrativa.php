<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

/**
 * Biblioteca digital de evidencia: facturas relacionadas con procesos administrativos
 * (distinta de la factura de compra puntual que se adjunta al registrar un bien).
 */
final class FacturaAdministrativa
{
    public static function create(array $datos): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO facturas_administrativas (institucion_id, fecha_factura, descripcion, archivo_path, registrado_por)
             VALUES (:institucion_id, :fecha_factura, :descripcion, :archivo_path, :registrado_por)'
        );
        $stmt->execute($datos);

        return (int) Database::connection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM facturas_administrativas WHERE id = ?');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public static function actualizar(int $id, array $datos): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE facturas_administrativas
                SET fecha_factura = :fecha_factura, descripcion = :descripcion, archivo_path = :archivo_path
              WHERE id = :id'
        );
        $stmt->execute($datos + ['id' => $id]);
    }

    public static function eliminar(int $id): void
    {
        Database::connection()->prepare('DELETE FROM facturas_administrativas WHERE id = ?')->execute([$id]);
    }

    public static function listar(?int $institucionId = null, int $pagina = 1, int $porPagina = 50): array
    {
        $sql = 'SELECT f.*, i.nombre AS institucion_nombre,
                       u.nombres AS registrado_por_nombres, u.apellidos AS registrado_por_apellidos
                FROM facturas_administrativas f
                JOIN instituciones i ON i.id = f.institucion_id
                LEFT JOIN usuarios u ON u.id = f.registrado_por';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' WHERE f.institucion_id = ?';
            $params[] = $institucionId;
        }

        $sql .= ' ORDER BY f.fecha_factura DESC, f.id DESC' . Paginador::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function contarListado(?int $institucionId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM facturas_administrativas f';
        $params = [];

        if ($institucionId !== null) {
            $sql .= ' WHERE f.institucion_id = ?';
            $params[] = $institucionId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
