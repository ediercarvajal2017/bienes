<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

final class LoteReintegro
{
    /**
     * Estados del lote (migración 039):
     *  - pendiente:   todavía no se ha entregado a la Alcaldía (estado inicial).
     *  - reintegrado: el usuario confirmó la entrega; queda bloqueado.
     *  - unificado:   sus bienes pasaron a lotes nuevos (uno por categoría).
     */
    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'reintegrado' => 'Reintegrado',
        'unificado' => 'Unificado',
    ];

    /** Valor del filtro de categoría para los bienes que no tienen ninguna. */
    public const FILTRO_SIN_CATEGORIA = 'sin-categoria';
    public const NOMBRE_SIN_CATEGORIA = 'Sin categoría';

    public static function create(array $datos): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO lotes_reintegro (institucion_id, fecha, destino_texto, observaciones, registrado_por)
             VALUES (:institucion_id, :fecha, :destino_texto, :observaciones, :registrado_por)'
        );
        $stmt->execute($datos);

        return (int) Database::connection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT l.*, i.nombre AS institucion_nombre,
                    u.nombres AS registrado_por_nombres, u.apellidos AS registrado_por_apellidos,
                    CONCAT(ur.nombres, " ", ur.apellidos) AS reintegrado_por_nombre,
                    CONCAT(uu.nombres, " ", uu.apellidos) AS unificado_por_nombre
             FROM lotes_reintegro l
             JOIN instituciones i ON i.id = l.institucion_id
             JOIN usuarios u ON u.id = l.registrado_por
             LEFT JOIN usuarios ur ON ur.id = l.reintegrado_por
             LEFT JOIN usuarios uu ON uu.id = l.unificado_por
             WHERE l.id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * @param array{estado?: ?string, categoria?: ?string, desde?: ?string, hasta?: ?string} $filtros
     *        estado null = todos; categoria = nombre exacto o FILTRO_SIN_CATEGORIA;
     *        desde/hasta (Y-m-d) sobre la fecha del lote.
     */
    public static function listar(?int $institucionId = null, int $pagina = 1, int $porPagina = 50, array $filtros = []): array
    {
        [$whereSql, $params] = self::condiciones($institucionId, $filtros);

        // Lo que más importa en cada vista: los pendientes del más viejo al más nuevo (lo
        // primero que hay que entregar); los reintegrados por fecha de entrega reciente.
        $orden = match ($filtros['estado'] ?? null) {
            'pendiente' => 'l.fecha ASC, l.id ASC',
            'reintegrado' => 'l.reintegrado_fecha DESC, l.id DESC',
            default => 'l.fecha DESC, l.id DESC',
        };

        $sql = 'SELECT l.*, i.nombre AS institucion_nombre,
                       u.nombres AS registrado_por_nombres, u.apellidos AS registrado_por_apellidos,
                       COUNT(m.id) AS total_bienes,
                       GROUP_CONCAT(DISTINCT CASE WHEN m.id IS NULL THEN NULL ELSE COALESCE(c.nombre, ?) END
                                    ORDER BY c.nombre SEPARATOR "|") AS categorias,
                       MIN(m.fecha) AS reintegro_desde, MAX(m.fecha) AS reintegro_hasta,
                       (SELECT GROUP_CONCAT(lu.lote_destino_id ORDER BY lu.lote_destino_id)
                          FROM lotes_reintegro_unificaciones lu WHERE lu.lote_origen_id = l.id) AS lotes_destino,
                       (SELECT GROUP_CONCAT(lu.lote_origen_id ORDER BY lu.lote_origen_id)
                          FROM lotes_reintegro_unificaciones lu WHERE lu.lote_destino_id = l.id) AS lotes_origen
                FROM lotes_reintegro l
                JOIN instituciones i ON i.id = l.institucion_id
                JOIN usuarios u ON u.id = l.registrado_por
                LEFT JOIN movimientos m ON m.lote_reintegro_id = l.id
                LEFT JOIN bienes b ON b.id = m.bien_id
                LEFT JOIN categorias_bienes c ON c.id = b.categoria_id'
               . $whereSql
               . ' GROUP BY l.id ORDER BY ' . $orden . Paginador::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([self::NOMBRE_SIN_CATEGORIA, ...$params]);

        return $stmt->fetchAll();
    }

    /** @param array{estado?: ?string, categoria?: ?string, desde?: ?string, hasta?: ?string} $filtros */
    public static function contarListado(?int $institucionId = null, array $filtros = []): int
    {
        [$whereSql, $params] = self::condiciones($institucionId, $filtros);

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM lotes_reintegro l' . $whereSql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Cuántos lotes hay en cada estado (para las pestañas), con los mismos filtros de
     * categoría y fechas que la lista.
     *
     * @param array{categoria?: ?string, desde?: ?string, hasta?: ?string} $filtros
     * @return array<string, int>
     */
    public static function contarPorEstado(?int $institucionId = null, array $filtros = []): array
    {
        [$whereSql, $params] = self::condiciones($institucionId, ['estado' => null] + $filtros);

        $stmt = Database::connection()->prepare('SELECT l.estado, COUNT(*) FROM lotes_reintegro l' . $whereSql . ' GROUP BY l.estado');
        $stmt->execute($params);

        $conteos = array_fill_keys(array_keys(self::ESTADOS), 0);
        foreach ($stmt->fetchAll(\PDO::FETCH_KEY_PAIR) as $estado => $cantidad) {
            $conteos[$estado] = (int) $cantidad;
        }

        return $conteos;
    }

    /** Lotes que todavía no se han entregado (contador del menú). */
    public static function contarPendientes(?int $institucionId = null): int
    {
        return self::contarListado($institucionId, ['estado' => 'pendiente']);
    }

    /**
     * Categorías que aparecen en los lotes (opciones del filtro): nombres, más
     * NOMBRE_SIN_CATEGORIA si hay bienes sin categoría.
     *
     * @return list<string>
     */
    public static function categoriasUsadas(?int $institucionId = null): array
    {
        $sql = 'SELECT DISTINCT COALESCE(c.nombre, ?) AS nombre
                FROM lotes_reintegro l
                JOIN movimientos m ON m.lote_reintegro_id = l.id
                JOIN bienes b ON b.id = m.bien_id
                LEFT JOIN categorias_bienes c ON c.id = b.categoria_id';
        $params = [self::NOMBRE_SIN_CATEGORIA];
        if ($institucionId !== null) {
            $sql .= ' WHERE l.institucion_id = ?';
            $params[] = $institucionId;
        }

        $stmt = Database::connection()->prepare($sql . ' ORDER BY nombre');
        $stmt->execute($params);

        return array_values(array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN)));
    }

    /**
     * @param array{estado?: ?string, categoria?: ?string, desde?: ?string, hasta?: ?string} $filtros
     * @return array{0: string, 1: list<mixed>}
     */
    private static function condiciones(?int $institucionId, array $filtros): array
    {
        $condiciones = [];
        $params = [];

        if ($institucionId !== null) {
            $condiciones[] = 'l.institucion_id = ?';
            $params[] = $institucionId;
        }
        if (!empty($filtros['estado']) && isset(self::ESTADOS[$filtros['estado']])) {
            $condiciones[] = 'l.estado = ?';
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['desde'])) {
            $condiciones[] = 'l.fecha >= ?';
            $params[] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $condiciones[] = 'l.fecha <= ?';
            $params[] = $filtros['hasta'];
        }
        if (!empty($filtros['categoria'])) {
            // Un lote "tiene" la categoría si al menos uno de sus bienes es de ella (los
            // lotes viejos pueden mezclar varias).
            if ($filtros['categoria'] === self::FILTRO_SIN_CATEGORIA) {
                $condiciones[] = 'EXISTS (SELECT 1 FROM movimientos mf JOIN bienes bf ON bf.id = mf.bien_id
                                          WHERE mf.lote_reintegro_id = l.id AND bf.categoria_id IS NULL)';
            } else {
                $condiciones[] = 'EXISTS (SELECT 1 FROM movimientos mf JOIN bienes bf ON bf.id = mf.bien_id
                                          JOIN categorias_bienes cf ON cf.id = bf.categoria_id
                                          WHERE mf.lote_reintegro_id = l.id AND cf.nombre = ?)';
                $params[] = $filtros['categoria'];
            }
        }

        return [$condiciones === [] ? '' : ' WHERE ' . implode(' AND ', $condiciones), $params];
    }

    /**
     * Lee y BLOQUEA (FOR UPDATE) los lotes indicados, para cambiarles el estado sin que
     * otra petición los toque a la vez. Debe llamarse dentro de una transacción.
     *
     * @param list<int> $ids
     */
    public static function bloquear(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT * FROM lotes_reintegro WHERE id IN ({$marcadores}) ORDER BY id FOR UPDATE"
        );
        $stmt->execute($ids);

        return $stmt->fetchAll();
    }

    /**
     * Los lotes indicados, sin bloquearlos (para la vista previa de una unificación).
     *
     * @param list<int> $ids
     */
    public static function buscarVarios(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare("SELECT * FROM lotes_reintegro WHERE id IN ({$marcadores}) ORDER BY id");
        $stmt->execute($ids);

        return $stmt->fetchAll();
    }

    /**
     * Movimientos de reintegro de los lotes indicados, con la categoría de su bien (para
     * repartirlos por categoría al unificar).
     *
     * @param list<int> $ids
     */
    public static function movimientosDeLotes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT m.id, m.lote_reintegro_id, b.categoria_id, c.nombre AS categoria_nombre
             FROM movimientos m
             JOIN bienes b ON b.id = m.bien_id
             LEFT JOIN categorias_bienes c ON c.id = b.categoria_id
             WHERE m.tipo = 'reintegro' AND m.lote_reintegro_id IN ({$marcadores})
             ORDER BY m.id"
        );
        $stmt->execute($ids);

        return $stmt->fetchAll();
    }

    /**
     * Pasa a 'unificado' solo los lotes que siguen 'pendiente' y devuelve cuántos cambió.
     *
     * @param list<int> $ids
     */
    public static function marcarUnificados(array $ids, int $usuarioId): int
    {
        if ($ids === []) {
            return 0;
        }

        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "UPDATE lotes_reintegro SET estado = 'unificado', unificado_por = ?, unificado_en = NOW()
             WHERE id IN ({$marcadores}) AND estado = 'pendiente'"
        );
        $stmt->execute([$usuarioId, ...$ids]);

        return $stmt->rowCount();
    }

    /** Lotes de los que salió este lote (si nació de una unificación). */
    public static function origenesDe(int $loteId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT l.id, l.fecha FROM lotes_reintegro_unificaciones u
             JOIN lotes_reintegro l ON l.id = u.lote_origen_id
             WHERE u.lote_destino_id = ? ORDER BY l.id'
        );
        $stmt->execute([$loteId]);

        return $stmt->fetchAll();
    }

    /** Lotes nuevos a los que pasaron los bienes de este lote (si se unificó), con su categoría. */
    public static function destinosDe(int $loteId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT l.id, l.fecha, l.estado,
                    (SELECT GROUP_CONCAT(DISTINCT COALESCE(c.nombre, ?) ORDER BY c.nombre SEPARATOR ", ")
                       FROM movimientos m JOIN bienes b ON b.id = m.bien_id
                       LEFT JOIN categorias_bienes c ON c.id = b.categoria_id
                      WHERE m.lote_reintegro_id = l.id) AS categorias
             FROM lotes_reintegro_unificaciones u
             JOIN lotes_reintegro l ON l.id = u.lote_destino_id
             WHERE u.lote_origen_id = ? ORDER BY l.id'
        );
        $stmt->execute([self::NOMBRE_SIN_CATEGORIA, $loteId]);

        return $stmt->fetchAll();
    }

    /**
     * Pasa a 'reintegrado' solo los lotes que siguen 'pendiente' y devuelve cuántos
     * cambió; el llamador lo compara con lo pedido.
     *
     * @param list<int> $ids
     */
    public static function marcarReintegrados(array $ids, string $fecha, ?string $nota, int $usuarioId): int
    {
        if ($ids === []) {
            return 0;
        }

        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "UPDATE lotes_reintegro
             SET estado = 'reintegrado', reintegrado_fecha = ?, reintegrado_nota = ?,
                 reintegrado_por = ?, reintegrado_registrado_en = NOW()
             WHERE id IN ({$marcadores}) AND estado = 'pendiente'"
        );
        $stmt->execute([$fecha, $nota, $usuarioId, ...$ids]);

        return $stmt->rowCount();
    }

    /** Devuelve un lote 'reintegrado' a 'pendiente' (corrección); 1 si lo cambió. */
    public static function reabrir(int $id): int
    {
        $stmt = Database::connection()->prepare(
            "UPDATE lotes_reintegro
             SET estado = 'pendiente', reintegrado_fecha = NULL, reintegrado_nota = NULL,
                 reintegrado_por = NULL, reintegrado_registrado_en = NULL
             WHERE id = ? AND estado = 'reintegrado'"
        );
        $stmt->execute([$id]);

        return $stmt->rowCount();
    }

    /**
     * Asigna al lote solo los movimientos que todavía NO tienen lote, y devuelve cuántos
     * asignó. Antes reasignaba cualquiera: dos solicitudes simultáneas podían mover los
     * mismos reintegros de un lote a otro. El llamador compara el resultado con lo pedido.
     */
    public static function asignarMovimientos(int $loteId, array $movimientoIds): int
    {
        if (empty($movimientoIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($movimientoIds), '?'));
        $stmt = Database::connection()->prepare(
            "UPDATE movimientos SET lote_reintegro_id = ?
             WHERE id IN ({$placeholders}) AND tipo = 'reintegro' AND lote_reintegro_id IS NULL"
        );
        $stmt->execute(array_merge([$loteId], $movimientoIds));

        return $stmt->rowCount();
    }

    /**
     * Pasa movimientos de los lotes de origen al lote nuevo (al unificar). Solo mueve los
     * que siguen en alguno de esos lotes; el llamador compara el resultado con lo pedido.
     *
     * @param list<int> $movimientoIds
     * @param list<int> $lotesOrigen
     */
    public static function moverMovimientos(int $loteDestinoId, array $movimientoIds, array $lotesOrigen): int
    {
        if ($movimientoIds === [] || $lotesOrigen === []) {
            return 0;
        }

        $marcadoresMovimientos = implode(',', array_fill(0, count($movimientoIds), '?'));
        $marcadoresLotes = implode(',', array_fill(0, count($lotesOrigen), '?'));
        $stmt = Database::connection()->prepare(
            "UPDATE movimientos SET lote_reintegro_id = ?
             WHERE id IN ({$marcadoresMovimientos}) AND tipo = 'reintegro' AND lote_reintegro_id IN ({$marcadoresLotes})"
        );
        $stmt->execute([$loteDestinoId, ...$movimientoIds, ...$lotesOrigen]);

        return $stmt->rowCount();
    }

    /**
     * Anota de qué lotes salió un lote nuevo (al unificar).
     *
     * @param list<int> $lotesOrigen
     */
    public static function registrarOrigenes(int $loteDestinoId, array $lotesOrigen): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT IGNORE INTO lotes_reintegro_unificaciones (lote_origen_id, lote_destino_id) VALUES (?, ?)'
        );
        foreach ($lotesOrigen as $origenId) {
            $stmt->execute([$origenId, $loteDestinoId]);
        }
    }

    public static function bienesDe(int $loteId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT m.fecha AS fecha_reintegro, m.destino_texto, m.observaciones,
                    b.codigo_identificacion, b.descripcion, b.marca, b.valor, b.categoria_id, c.nombre AS categoria_nombre,
                    CONCAT(eo.codigo, " - ", eo.nombre) AS espacio_origen_nombre
             FROM movimientos m
             JOIN bienes b ON b.id = m.bien_id
             LEFT JOIN categorias_bienes c ON c.id = b.categoria_id
             LEFT JOIN espacios eo ON eo.id = m.espacio_origen_id
             WHERE m.lote_reintegro_id = ?
             ORDER BY c.nombre, b.codigo_identificacion'
        );
        $stmt->execute([$loteId]);

        return $stmt->fetchAll();
    }
}
