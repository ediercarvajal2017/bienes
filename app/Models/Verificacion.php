<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

final class Verificacion
{
    /**
     * Registra el resultado de verificar un bien dentro de una jornada. Si el bien ya
     * había sido verificado en esta misma jornada (re-escaneo), se actualiza el registro
     * en vez de duplicarlo — gana la verificación más reciente.
     */
    public static function registrar(int $jornadaId, int $bienId, int $usuarioId, string $resultado, ?string $observaciones): void
    {
        Database::connection()->prepare(
            'INSERT INTO verificaciones_bienes (jornada_id, bien_id, usuario_id, resultado, observaciones)
             VALUES (:jornada_id, :bien_id, :usuario_id, :resultado, :observaciones)
             ON DUPLICATE KEY UPDATE usuario_id = VALUES(usuario_id), resultado = VALUES(resultado),
                                     observaciones = VALUES(observaciones), updated_at = CURRENT_TIMESTAMP'
        )->execute([
            'jornada_id' => $jornadaId,
            'bien_id' => $bienId,
            'usuario_id' => $usuarioId,
            'resultado' => $resultado,
            'observaciones' => $observaciones,
        ]);
    }

    public static function deBienEnJornada(int $jornadaId, int $bienId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT v.*, u.nombres, u.apellidos
             FROM verificaciones_bienes v
             JOIN usuarios u ON u.id = v.usuario_id
             WHERE v.jornada_id = ? AND v.bien_id = ?'
        );
        $stmt->execute([$jornadaId, $bienId]);

        return $stmt->fetch() ?: null;
    }

    public static function contarPorResultado(int $jornadaId, string $resultado): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM verificaciones_bienes WHERE jornada_id = ? AND resultado = ?'
        );
        $stmt->execute([$jornadaId, $resultado]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Universo de bienes que la jornada debe cubrir: todos los de la institución que no
     * estén dados de baja (los dados de baja ya salieron de circulación, no aplica
     * verificarlos físicamente).
     */
    public static function contarUniverso(int $institucionId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM bienes WHERE institucion_id = ? AND estado != 'dado_de_baja'"
        );
        $stmt->execute([$institucionId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Detalle de las verificaciones de una jornada con un resultado dado ('ok' o
     * 'discrepancia'): código, descripción, ubicación actual, responsable(s) del espacio
     * y quién hizo la verificación — para el reporte detallado de la jornada.
     */
    public static function listarPorResultado(int $jornadaId, string $resultado): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT v.*, b.codigo_identificacion, b.descripcion,
                    CONCAT(e.codigo, ' - ', e.nombre) AS espacio_nombre,
                    (SELECT GROUP_CONCAT(CONCAT(u2.nombres, ' ', u2.apellidos) SEPARATOR ', ')
                     FROM espacio_responsables er JOIN usuarios u2 ON u2.id = er.usuario_id
                     WHERE er.espacio_id = e.id) AS responsables_nombres,
                    u.nombres, u.apellidos
             FROM verificaciones_bienes v
             JOIN bienes b ON b.id = v.bien_id
             LEFT JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
             LEFT JOIN espacios e ON e.id = a.espacio_id
             JOIN usuarios u ON u.id = v.usuario_id
             WHERE v.jornada_id = ? AND v.resultado = ?
             ORDER BY v.updated_at DESC"
        );
        $stmt->execute([$jornadaId, $resultado]);

        return $stmt->fetchAll();
    }

    /**
     * Bienes del universo de la institución que todavía no tienen ningún registro de
     * verificación en esta jornada. Admite búsqueda y paginación (mismo patrón usado en
     * Usuario::listar()/Bien::listar(): porPagina <= 0 significa "ver todos").
     */
    public static function listarPendientes(int $jornadaId, int $institucionId, ?string $busqueda = null, int $pagina = 1, int $porPagina = 50): array
    {
        [$whereSql, $params] = self::condicionesPendientes($institucionId, $busqueda);

        $sql = "SELECT b.id, b.codigo_identificacion, b.descripcion, b.estado,
                       CONCAT(e.codigo, ' - ', e.nombre) AS espacio_nombre
                FROM bienes b
                LEFT JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
                LEFT JOIN espacios e ON e.id = a.espacio_id
                LEFT JOIN verificaciones_bienes v ON v.bien_id = b.id AND v.jornada_id = ?"
               . $whereSql
               . ' ORDER BY b.codigo_identificacion'
               . Paginador::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array_merge([$jornadaId], $params));

        return $stmt->fetchAll();
    }

    public static function contarPendientes(int $jornadaId, int $institucionId, ?string $busqueda = null): int
    {
        [$whereSql, $params] = self::condicionesPendientes($institucionId, $busqueda);

        $sql = 'SELECT COUNT(*)
                FROM bienes b
                LEFT JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
                LEFT JOIN espacios e ON e.id = a.espacio_id
                LEFT JOIN verificaciones_bienes v ON v.bien_id = b.id AND v.jornada_id = ?'
               . $whereSql;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array_merge([$jornadaId], $params));

        return (int) $stmt->fetchColumn();
    }

    private static function condicionesPendientes(int $institucionId, ?string $busqueda): array
    {
        $condiciones = ['b.institucion_id = ?', "b.estado != 'dado_de_baja'", 'v.id IS NULL'];
        $params = [$institucionId];

        if ($busqueda !== null && $busqueda !== '') {
            $termino = '%' . $busqueda . '%';
            $condiciones[] = '(b.codigo_identificacion LIKE ? OR b.descripcion LIKE ? OR e.nombre LIKE ?)';
            array_push($params, $termino, $termino, $termino);
        }

        return [' WHERE ' . implode(' AND ', $condiciones), $params];
    }
}
