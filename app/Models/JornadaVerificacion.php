<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

final class JornadaVerificacion
{
    /**
     * Solo puede haber una jornada "en_progreso" a la vez por institución (se valida
     * antes de crear una nueva), así que esto identifica sin ambigüedad la jornada activa.
     */
    public static function activaPara(int $institucionId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM jornadas_verificacion WHERE institucion_id = ? AND estado = 'en_progreso' LIMIT 1"
        );
        $stmt->execute([$institucionId]);

        return $stmt->fetch() ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT jv.*, u.nombres AS creador_nombres, u.apellidos AS creador_apellidos, i.nombre AS institucion_nombre
             FROM jornadas_verificacion jv
             JOIN usuarios u ON u.id = jv.creada_por
             JOIN instituciones i ON i.id = jv.institucion_id
             WHERE jv.id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function listar(?int $institucionId, int $pagina = 1, int $porPagina = 25): array
    {
        [$whereSql, $params] = self::condiciones($institucionId);

        $sql = 'SELECT jv.*, u.nombres AS creador_nombres, u.apellidos AS creador_apellidos
                FROM jornadas_verificacion jv
                JOIN usuarios u ON u.id = jv.creada_por'
               . $whereSql
               . ' ORDER BY jv.created_at DESC, jv.id DESC'
               . Paginador::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function contarListado(?int $institucionId): int
    {
        [$whereSql, $params] = self::condiciones($institucionId);

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM jornadas_verificacion jv' . $whereSql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private static function condiciones(?int $institucionId): array
    {
        if ($institucionId === null) {
            return ['', []];
        }

        return [' WHERE jv.institucion_id = ?', [$institucionId]];
    }

    public static function crear(array $datos): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO jornadas_verificacion (institucion_id, nombre, fecha_inicio, creada_por)
             VALUES (:institucion_id, :nombre, :fecha_inicio, :creada_por)'
        );
        $stmt->execute($datos);

        return (int) Database::connection()->lastInsertId();
    }

    public static function cerrar(int $id, ?string $observaciones): void
    {
        Database::connection()->prepare(
            "UPDATE jornadas_verificacion SET estado = 'cerrada', fecha_cierre = ?, observaciones_cierre = ? WHERE id = ?"
        )->execute([date('Y-m-d H:i:s'), $observaciones, $id]);
    }
}
