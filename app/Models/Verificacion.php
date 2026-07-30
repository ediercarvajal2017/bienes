<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

final class Verificacion
{
    /**
     * Motivos estructurados de una discrepancia (además del texto libre en
     * observaciones), para poder filtrar y contar por tipo en vez de depender de leer
     * cada observación una por una.
     */
    public const MOTIVOS = ['no_se_encuentra', 'otra_ubicacion', 'danado', 'responsable_incorrecto', 'otro'];

    private const ETIQUETAS_MOTIVO = [
        'no_se_encuentra' => 'No se encuentra',
        'otra_ubicacion' => 'Está en otro salón',
        'danado' => 'Dañado',
        'responsable_incorrecto' => 'El responsable no es el correcto',
        'otro' => 'Otro',
    ];

    public static function etiquetaMotivo(?string $motivo): string
    {
        return self::ETIQUETAS_MOTIVO[$motivo] ?? '—';
    }

    /**
     * Registra el resultado de verificar un bien dentro de una jornada. Si el bien ya
     * había sido verificado en esta misma jornada (re-escaneo), se actualiza el registro
     * en vez de duplicarlo — gana la verificación más reciente. Un re-escaneo también
     * reinicia "revisada" a 0: si un admin ya había marcado como atendida una discrepancia
     * y luego llega un reporte nuevo (con otra observación), ese reporte nuevo todavía no
     * ha sido revisado por nadie, aunque el anterior sí lo estuviera. $motivo solo aplica
     * a discrepancias — para 'ok' siempre se guarda null.
     */
    public static function registrar(int $jornadaId, int $bienId, int $usuarioId, string $resultado, ?string $motivo, ?string $observaciones): void
    {
        Database::connection()->prepare(
            'INSERT INTO verificaciones_bienes (jornada_id, bien_id, usuario_id, resultado, motivo, observaciones)
             VALUES (:jornada_id, :bien_id, :usuario_id, :resultado, :motivo, :observaciones)
             ON DUPLICATE KEY UPDATE usuario_id = VALUES(usuario_id), resultado = VALUES(resultado),
                                     motivo = VALUES(motivo), observaciones = VALUES(observaciones), revisada = 0,
                                     revisada_por = NULL, revisada_en = NULL, updated_at = CURRENT_TIMESTAMP'
        )->execute([
            'jornada_id' => $jornadaId,
            'bien_id' => $bienId,
            'usuario_id' => $usuarioId,
            'resultado' => $resultado,
            'motivo' => $resultado === 'discrepancia' ? $motivo : null,
            'observaciones' => $observaciones,
        ]);
    }

    /**
     * Una verificación puntual (fila de la tabla, no un bien) con datos suficientes para
     * validar accesos: institucion del bien y jornada a la que pertenece. Usada para
     * "marcar como revisada" y para vincular un reporte de baja con la discrepancia que
     * lo originó.
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT v.*, b.institucion_id
             FROM verificaciones_bienes v
             JOIN bienes b ON b.id = v.bien_id
             WHERE v.id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Marca una discrepancia como atendida por el administrador — no cambia el resultado
     * ('discrepancia' sigue siendo el hecho reportado), solo su seguimiento: queda quién
     * la revisó y cuándo. Reescribe updated_at con su propio valor a propósito: sin eso,
     * MySQL refresca automáticamente esa columna con cualquier UPDATE a la fila (por el
     * ON UPDATE CURRENT_TIMESTAMP de la tabla), y la vista usa updated_at como "fecha del
     * reporte" — sin este truco, marcar como revisada correría esa fecha al momento de la
     * revisión en vez de conservar el momento real en que se reportó la discrepancia.
     */
    public static function marcarRevisada(int $id, int $usuarioId): void
    {
        $verificacion = self::find($id);
        if ($verificacion === null) {
            return;
        }

        Database::connection()->prepare(
            'UPDATE verificaciones_bienes
             SET revisada = 1, revisada_por = :revisada_por, revisada_en = NOW(), updated_at = :updated_at
             WHERE id = :id'
        )->execute([
            'revisada_por' => $usuarioId,
            'updated_at' => $verificacion['updated_at'],
            'id' => $id,
        ]);
    }

    /**
     * Valida que un id de verificación recibido de un formulario/URL (query string o
     * campo oculto) realmente pertenezca al bien indicado, antes de usarlo para vincular
     * una acción correctiva (baja, traslado, asignación) con la discrepancia que la
     * originó. Devuelve null ante cualquier duda — vacío, no numérico, inexistente, o de
     * OTRO bien (alguien manipulando la URL a mano) — en vez de lanzar error: el llamador
     * simplemente continúa sin vincular nada, no es una condición que deba tumbar la
     * petición.
     */
    public static function idValidoParaBien(int $bienId, string $crudo): ?int
    {
        if ($crudo === '' || !ctype_digit($crudo)) {
            return null;
        }

        $verificacion = self::find((int) $crudo);

        return ($verificacion && (int) $verificacion['bien_id'] === $bienId) ? (int) $verificacion['id'] : null;
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

    /**
     * $busqueda y $revisada son opcionales: sin ellos, es el conteo total (usado en las
     * tarjetas resumen de arriba); con ellos, es el conteo ya filtrado que necesita la
     * paginación de la tabla de detalle (ver listarPorResultado()).
     */
    public static function contarPorResultado(int $jornadaId, string $resultado, ?string $busqueda = null, ?bool $revisada = null): int
    {
        [$whereSql, $params] = self::condicionesPorResultado($jornadaId, $resultado, $busqueda, $revisada);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM verificaciones_bienes v
             JOIN bienes b ON b.id = v.bien_id
             LEFT JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
             LEFT JOIN espacios e ON e.id = a.espacio_id'
            . $whereSql
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Desglose de discrepancias por motivo, para un resumen tipo "12 no se encuentran,
     * 5 en otro salón, 3 dañados" sin tener que leer observación por observación.
     * Solo cuenta discrepancias sin atender — una vez resuelta, ya no aporta al panorama
     * de "qué falta por hacer" (el detalle histórico completo sigue en el Excel exportado).
     *
     * @return array<string, int> motivo => cantidad, en el mismo orden que MOTIVOS,
     *                             incluyendo los motivos en 0 (para no tener que verificar
     *                             existencia de la clave en la vista).
     */
    public static function contarPorMotivo(int $jornadaId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT motivo, COUNT(*) AS total
             FROM verificaciones_bienes
             WHERE jornada_id = ? AND resultado = "discrepancia" AND revisada = 0 AND motivo IS NOT NULL
             GROUP BY motivo'
        );
        $stmt->execute([$jornadaId]);
        $conteos = array_column($stmt->fetchAll(), 'total', 'motivo');

        $resultado = [];
        foreach (self::MOTIVOS as $motivo) {
            $resultado[$motivo] = (int) ($conteos[$motivo] ?? 0);
        }

        return $resultado;
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
     * y quién hizo la verificación — para el reporte detallado de la jornada. Admite
     * búsqueda, paginación (mismo patrón que listarPendientes()) y, para discrepancias,
     * filtrar por estado de revisión ($revisada: true/false/null = todas).
     */
    public static function listarPorResultado(int $jornadaId, string $resultado, ?string $busqueda = null, int $pagina = 1, int $porPagina = 50, ?bool $revisada = null): array
    {
        [$whereSql, $params] = self::condicionesPorResultado($jornadaId, $resultado, $busqueda, $revisada);

        $sql = "SELECT v.*, b.codigo_identificacion, b.descripcion, b.qr_token,
                       CONCAT(e.codigo, ' - ', e.nombre) AS espacio_nombre,
                       (SELECT GROUP_CONCAT(CONCAT(u2.nombres, ' ', u2.apellidos) SEPARATOR ', ')
                        FROM espacio_responsables er JOIN usuarios u2 ON u2.id = er.usuario_id
                        WHERE er.espacio_id = e.id) AS responsables_nombres,
                       u.nombres, u.apellidos,
                       ur.nombres AS revisor_nombres, ur.apellidos AS revisor_apellidos
                FROM verificaciones_bienes v
                JOIN bienes b ON b.id = v.bien_id
                LEFT JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
                LEFT JOIN espacios e ON e.id = a.espacio_id
                JOIN usuarios u ON u.id = v.usuario_id
                LEFT JOIN usuarios ur ON ur.id = v.revisada_por"
               . $whereSql
               . ' ORDER BY v.updated_at DESC'
               . Paginador::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private static function condicionesPorResultado(int $jornadaId, string $resultado, ?string $busqueda, ?bool $revisada): array
    {
        $condiciones = ['v.jornada_id = ?', 'v.resultado = ?'];
        $params = [$jornadaId, $resultado];

        if ($revisada !== null) {
            $condiciones[] = 'v.revisada = ?';
            $params[] = $revisada ? 1 : 0;
        }

        if ($busqueda !== null && $busqueda !== '') {
            $termino = '%' . $busqueda . '%';
            $condiciones[] = '(b.codigo_identificacion LIKE ? OR b.descripcion LIKE ? OR e.nombre LIKE ?)';
            array_push($params, $termino, $termino, $termino);
        }

        return [' WHERE ' . implode(' AND ', $condiciones), $params];
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
