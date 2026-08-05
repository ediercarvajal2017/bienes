<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

/**
 * Bitácora de quién hizo qué: crear, editar, activar/desactivar, eliminar (enviar a
 * la papelera), restaurar y purgar. La tabla `auditoria` ya existía en el esquema
 * desde el inicio del proyecto pero nunca se conectó a ningún código — este modelo
 * es lo que la pone en uso.
 */
final class Auditoria
{
    public static function registrar(
        ?int $usuarioId,
        ?int $institucionId,
        string $accion,
        string $entidad,
        int $entidadId,
        ?array $datosAntes = null,
        ?array $datosDespues = null
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO auditoria (usuario_id, institucion_id, accion, entidad, entidad_id, datos_antes, datos_despues, ip)
             VALUES (:usuario_id, :institucion_id, :accion, :entidad, :entidad_id, :datos_antes, :datos_despues, :ip)'
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'institucion_id' => $institucionId,
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'datos_antes' => $datosAntes !== null ? json_encode($datosAntes, JSON_UNESCAPED_UNICODE) : null,
            'datos_despues' => $datosDespues !== null ? json_encode($datosDespues, JSON_UNESCAPED_UNICODE) : null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    public static function listar(array $filtros = [], int $pagina = 1, int $porPagina = 50): array
    {
        [$whereSql, $params] = self::condiciones($filtros);

        $sql = 'SELECT a.*, u.nombres AS usuario_nombres, u.apellidos AS usuario_apellidos, i.nombre AS institucion_nombre
                FROM auditoria a
                LEFT JOIN usuarios u ON u.id = a.usuario_id
                LEFT JOIN instituciones i ON i.id = a.institucion_id'
               . $whereSql
               . ' ORDER BY a.created_at DESC' . Paginador::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function contar(array $filtros = []): int
    {
        [$whereSql, $params] = self::condiciones($filtros);

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM auditoria a' . $whereSql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * $filtros admite: institucion_id, entidad, accion, desde (Y-m-d), hasta (Y-m-d).
     * Cualquier clave ausente o vacía no se aplica.
     */
    private static function condiciones(array $filtros): array
    {
        $condiciones = [];
        $params = [];

        if (!empty($filtros['institucion_id'])) {
            $condiciones[] = 'a.institucion_id = ?';
            $params[] = (int) $filtros['institucion_id'];
        }

        if (!empty($filtros['entidad'])) {
            $condiciones[] = 'a.entidad = ?';
            $params[] = $filtros['entidad'];
        }

        if (!empty($filtros['accion'])) {
            $condiciones[] = 'a.accion = ?';
            $params[] = $filtros['accion'];
        }

        if (!empty($filtros['desde'])) {
            $condiciones[] = 'a.created_at >= ?';
            $params[] = $filtros['desde'] . ' 00:00:00';
        }

        if (!empty($filtros['hasta'])) {
            $condiciones[] = 'a.created_at <= ?';
            $params[] = $filtros['hasta'] . ' 23:59:59';
        }

        $sql = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';

        return [$sql, $params];
    }
}
