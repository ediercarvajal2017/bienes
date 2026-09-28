<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Bien
{
    /**
     * $excluirLotes: no incluye bienes que pertenecen a un lote (bienes.lote no nulo,
     * ver crearLoteIdentico()) — usado por /bienes para no saturar la cartera con
     * decenas de filas casi idénticas (ej. 250 sillas); esas se muestran agrupadas por
     * separado (listarLotes()) y solo aparecen aquí individualmente si el usuario busca
     * algo. El resto de llamadores (reportes, exportaciones, selects) no la pasan y
     * siguen viendo el listado completo como siempre.
     *
     * La misma bandera también excluye los bienes reintegrados y dados de baja de esa
     * vista sin filtrar — se muestran agrupados aparte como "Bodega Reintegro"/"Bodega
     * de Baja" (ver BienController::index()), y siguen apareciendo aquí si el usuario
     * busca algo o filtra por Estado explícitamente.
     */
    public static function listar(?int $institucionId = null, ?string $busqueda = null, int $pagina = 1, int $porPagina = 50, bool $excluirLotes = false, ?int $categoriaId = null, ?string $estado = null, ?int $espacioId = null): array
    {
        [$whereSql, $params] = self::condicionesListado($institucionId, $busqueda, $excluirLotes, $categoriaId, $estado, $espacioId);

        $sql = 'SELECT b.*, c.nombre AS categoria_nombre, CONCAT(e.codigo, " - ", e.nombre) AS espacio_nombre,
                       ' . self::sqlResponsablesEspacio('e.id') . ' AS responsables_nombres
                FROM bienes b
                LEFT JOIN categorias_bienes c ON c.id = b.categoria_id
                LEFT JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
                LEFT JOIN espacios e ON e.id = a.espacio_id'
               . $whereSql
               . ' ORDER BY b.created_at DESC, b.id DESC'
               . self::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function contarListado(?int $institucionId = null, ?string $busqueda = null, bool $excluirLotes = false, ?int $categoriaId = null, ?string $estado = null, ?int $espacioId = null): int
    {
        [$whereSql, $params] = self::condicionesListado($institucionId, $busqueda, $excluirLotes, $categoriaId, $estado, $espacioId);

        $sql = 'SELECT COUNT(*)
                FROM bienes b
                LEFT JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
                LEFT JOIN espacios e ON e.id = a.espacio_id'
               . $whereSql;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca por código, descripción, responsable/ubicación, estado (admite "en reparacion"
     * con o sin guion bajo), valor y lote — las mismas columnas visibles en /bienes. El
     * responsable ahora es el espacio (y sus responsables), no una persona asignada
     * directamente al bien.
     */
    private static function condicionesListado(?int $institucionId, ?string $busqueda, bool $excluirLotes = false, ?int $categoriaId = null, ?string $estado = null, ?int $espacioId = null): array
    {
        $condiciones = [];
        $params = [];

        if ($institucionId !== null) {
            $condiciones[] = 'b.institucion_id = ?';
            $params[] = $institucionId;
        }

        if ($excluirLotes) {
            $condiciones[] = 'b.lote IS NULL';
        }

        if ($excluirLotes && $estado === null) {
            $condiciones[] = 'b.estado NOT IN ("reintegrado", "dado_de_baja")';
        }

        if ($categoriaId !== null) {
            $condiciones[] = 'b.categoria_id = ?';
            $params[] = $categoriaId;
        }

        if ($estado !== null) {
            $condiciones[] = 'b.estado = ?';
            $params[] = $estado;
        }

        if ($espacioId !== null) {
            $condiciones[] = 'e.id = ?';
            $params[] = $espacioId;
        }

        if ($busqueda !== null && $busqueda !== '') {
            $termino = '%' . $busqueda . '%';
            $condiciones[] = '(b.codigo_identificacion LIKE ? OR b.descripcion LIKE ? OR e.nombre LIKE ?
                OR EXISTS (SELECT 1 FROM espacio_responsables er JOIN usuarios u ON u.id = er.usuario_id
                           WHERE er.espacio_id = e.id AND CONCAT(u.nombres, " ", u.apellidos) LIKE ?)
                OR REPLACE(b.estado, "_", " ") LIKE ? OR CAST(b.valor AS CHAR) LIKE ? OR b.lote LIKE ?)';
            array_push($params, $termino, $termino, $termino, $termino, $termino, $termino, $termino);
        }

        $sql = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';

        return [$sql, $params];
    }

    /**
     * Igual que listar(), pero solo los bienes cuya asignación activa está en un espacio
     * donde $usuarioId figura como responsable (usado para el rol docente: solo ve "sus"
     * bienes, no todo el inventario de la institución). Un bien sin asignación activa, o
     * asignado a un espacio donde el usuario no es responsable, queda fuera.
     */
    public static function listarPropios(int $usuarioId, ?int $institucionId = null, ?string $busqueda = null, int $pagina = 1, int $porPagina = 50, ?int $categoriaId = null, ?string $estado = null, ?int $espacioId = null): array
    {
        [$whereSql, $params] = self::condicionesPropios($institucionId, $busqueda, $categoriaId, $estado, $espacioId);

        $sql = 'SELECT b.*, c.nombre AS categoria_nombre, CONCAT(e.codigo, " - ", e.nombre) AS espacio_nombre,
                       ' . self::sqlResponsablesEspacio('e.id') . ' AS responsables_nombres
                FROM bienes b
                LEFT JOIN categorias_bienes c ON c.id = b.categoria_id
                JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
                JOIN espacios e ON e.id = a.espacio_id
                JOIN espacio_responsables er ON er.espacio_id = e.id AND er.usuario_id = ?'
               . $whereSql
               . ' ORDER BY b.created_at DESC, b.id DESC'
               . self::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array_merge([$usuarioId], $params));

        return $stmt->fetchAll();
    }

    public static function contarPropios(int $usuarioId, ?int $institucionId = null, ?string $busqueda = null, ?int $categoriaId = null, ?string $estado = null, ?int $espacioId = null): int
    {
        [$whereSql, $params] = self::condicionesPropios($institucionId, $busqueda, $categoriaId, $estado, $espacioId);

        $sql = 'SELECT COUNT(*)
                FROM bienes b
                JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
                JOIN espacios e ON e.id = a.espacio_id
                JOIN espacio_responsables er ON er.espacio_id = e.id AND er.usuario_id = ?'
               . $whereSql;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array_merge([$usuarioId], $params));

        return (int) $stmt->fetchColumn();
    }

    private static function condicionesPropios(?int $institucionId, ?string $busqueda, ?int $categoriaId = null, ?string $estado = null, ?int $espacioId = null): array
    {
        $condiciones = [];
        $params = [];

        if ($institucionId !== null) {
            $condiciones[] = 'b.institucion_id = ?';
            $params[] = $institucionId;
        }

        if ($categoriaId !== null) {
            $condiciones[] = 'b.categoria_id = ?';
            $params[] = $categoriaId;
        }

        if ($estado !== null) {
            $condiciones[] = 'b.estado = ?';
            $params[] = $estado;
        }

        if ($espacioId !== null) {
            $condiciones[] = 'e.id = ?';
            $params[] = $espacioId;
        }

        if ($busqueda !== null && $busqueda !== '') {
            $termino = '%' . $busqueda . '%';
            $condiciones[] = '(b.codigo_identificacion LIKE ? OR b.descripcion LIKE ? OR e.nombre LIKE ?
                OR REPLACE(b.estado, "_", " ") LIKE ? OR CAST(b.valor AS CHAR) LIKE ?)';
            array_push($params, $termino, $termino, $termino, $termino, $termino);
        }

        $sql = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';

        return [$sql, $params];
    }

    /**
     * Subconsulta correlacionada con los nombres de los responsables de un espacio (puede
     * haber varios). $columnaEspacioId es la columna del espacio en la consulta externa.
     */
    private static function sqlResponsablesEspacio(string $columnaEspacioId): string
    {
        return "(SELECT GROUP_CONCAT(CONCAT(u.nombres, ' ', u.apellidos) SEPARATOR ', ')
                 FROM espacio_responsables er JOIN usuarios u ON u.id = er.usuario_id
                 WHERE er.espacio_id = {$columnaEspacioId})";
    }

    /**
     * LIMIT/OFFSET se interpolan directamente (no como parámetros ligados): con
     * PDO::ATTR_EMULATE_PREPARES=false, MySQL rechaza LIMIT/OFFSET ligados como
     * string. Son enteros validados aquí mismo, nunca texto del usuario, así que
     * interpolarlos es seguro. $porPagina = 0 es un sentinel especial: "sin límite"
     * (usado por ReporteService, que necesita exportar todas las filas, no una página).
     */
    private static function limitSql(int $pagina, int $porPagina): string
    {
        if ($porPagina <= 0) {
            return '';
        }

        $porPagina = max(1, min(200, $porPagina));
        $offset = (max(1, $pagina) - 1) * $porPagina;

        return " LIMIT {$porPagina} OFFSET {$offset}";
    }

    /**
     * Trae bienes puntuales por id, siempre acotado a una institución (defensa en
     * profundidad: aunque alguien manipule los ids enviados, nunca trae bienes de
     * otra institución). Usado para generar QR masivo de una selección puntual.
     */
    public static function listarPorIds(int $institucionId, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT id, codigo_identificacion, qr_token
             FROM bienes
             WHERE institucion_id = ? AND id IN ({$placeholders})
             ORDER BY codigo_identificacion"
        );
        $stmt->execute([$institucionId, ...$ids]);

        return $stmt->fetchAll();
    }

    /** Fases de la Bodega de impresión de QR (condición SQL sobre la tabla `b`). */
    private const FASES_QR = [
        // Pedido y sin imprimir desde que se pidió (un bien que se vuelve a pedir, por
        // ejemplo porque el sticker se dañó, regresa aquí aunque tenga una impresión vieja).
        'por_imprimir' => '(b.qr_impreso_en IS NULL OR b.qr_impreso_en < b.qr_solicitado_en)',
        // Ya impreso para esta solicitud: falta pegar el sticker y confirmarlo.
        'por_pegar' => 'b.qr_impreso_en >= b.qr_solicitado_en',
    ];

    /**
     * Bodega de impresión de QR: bienes que alguien marcó con la casilla "Imprimir QR" en
     * el formulario y todavía no se confirmó que el sticker quedó pegado (confirmar limpia
     * qr_solicitado_en). Con $fase se separan los que faltan por imprimir de los ya
     * impresos que faltan por pegar (ver FASES_QR).
     */
    public static function solicitadosQr(int $institucionId, ?string $fase = null): array
    {
        $condicion = $fase !== null ? ' AND ' . self::FASES_QR[$fase] : '';
        $stmt = Database::connection()->prepare(
            "SELECT b.id, b.codigo_identificacion, b.descripcion, b.qr_token, b.qr_impreso_en, b.qr_solicitado_en,
                    CONCAT(u.nombres, ' ', u.apellidos) AS solicitado_por_nombre
             FROM bienes b
             LEFT JOIN usuarios u ON u.id = b.qr_solicitado_por
             WHERE b.institucion_id = ? AND b.qr_solicitado_en IS NOT NULL{$condicion}
             ORDER BY b.codigo_identificacion"
        );
        $stmt->execute([$institucionId]);

        return $stmt->fetchAll();
    }

    public static function contarSolicitadosQr(int $institucionId, ?string $fase = null): int
    {
        $condicion = $fase !== null ? ' AND ' . self::FASES_QR[$fase] : '';
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM bienes b WHERE b.institucion_id = ? AND b.qr_solicitado_en IS NOT NULL{$condicion}"
        );
        $stmt->execute([$institucionId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Confirma desde la bodega que los stickers ya quedaron pegados: solo sobre bienes de
     * la institución que estén "impresos, por pegar". Marca la confirmación y retira la
     * solicitud, con lo que salen de la bodega. Devuelve los bienes confirmados.
     *
     * @param int[] $ids
     * @return array<int, array{id: int|string, codigo_identificacion: string}>
     */
    public static function confirmarPegados(array $ids, int $institucionId, int $usuarioId): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT b.id, b.codigo_identificacion FROM bienes b
             WHERE b.institucion_id = ? AND b.id IN ({$marcadores})
               AND b.qr_solicitado_en IS NOT NULL AND " . self::FASES_QR['por_pegar']
        );
        $stmt->execute([$institucionId, ...$ids]);
        $bienes = $stmt->fetchAll();
        if ($bienes === []) {
            return [];
        }

        $confirmados = array_map('intval', array_column($bienes, 'id'));
        $marcadores = implode(',', array_fill(0, count($confirmados), '?'));
        Database::connection()->prepare(
            "UPDATE bienes SET qr_confirmado_en = NOW(), qr_confirmado_por = ?, qr_solicitado_en = NULL, qr_solicitado_por = NULL
             WHERE id IN ({$marcadores})"
        )->execute([$usuarioId, ...$confirmados]);

        return $bienes;
    }

    /**
     * Cifras del panel principal. "En circulación" = activo o en reparación (lo que la
     * institución tiene hoy); los reintegrados y dados de baja se cuentan aparte.
     *
     * @return array{total: int, en_circulacion: int, valor: float, asignados: int, qr_confirmados: int, por_estado: array<string, int>}
     */
    public static function resumenPanel(?int $institucionId): array
    {
        $filtro = $institucionId !== null ? ' WHERE b.institucion_id = ?' : '';
        $params = $institucionId !== null ? [$institucionId] : [];

        $stmt = Database::connection()->prepare(
            "SELECT b.estado,
                    COUNT(*) AS cantidad,
                    COALESCE(SUM(b.valor), 0) AS valor,
                    SUM(a.id IS NOT NULL) AS asignados,
                    SUM(b.qr_confirmado_en IS NOT NULL) AS qr_confirmados
             FROM bienes b
             LEFT JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
             {$filtro}
             GROUP BY b.estado"
        );
        $stmt->execute($params);

        $resumen = ['total' => 0, 'en_circulacion' => 0, 'valor' => 0.0, 'asignados' => 0, 'qr_confirmados' => 0,
            'por_estado' => array_fill_keys(array_keys(self::TRANSICIONES), 0)];

        foreach ($stmt->fetchAll() as $fila) {
            $cantidad = (int) $fila['cantidad'];
            $resumen['total'] += $cantidad;
            $resumen['por_estado'][$fila['estado']] = $cantidad;

            if (in_array($fila['estado'], ['activo', 'en_reparacion'], true)) {
                $resumen['en_circulacion'] += $cantidad;
                $resumen['valor'] += (float) $fila['valor'];
                $resumen['asignados'] += (int) $fila['asignados'];
                $resumen['qr_confirmados'] += (int) $fila['qr_confirmados'];
            }
        }

        return $resumen;
    }

    /**
     * Bienes en circulación por categoría (las $limite con más bienes; el resto se suma
     * en "Otras categorías").
     *
     * @return list<array{nombre: string, cantidad: int}>
     */
    public static function porCategoriaPanel(?int $institucionId, int $limite = 6): array
    {
        $filtro = $institucionId !== null ? ' AND b.institucion_id = ?' : '';
        $params = $institucionId !== null ? [$institucionId] : [];

        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(c.nombre, 'Sin categoría') AS nombre, COUNT(*) AS cantidad
             FROM bienes b
             LEFT JOIN categorias_bienes c ON c.id = b.categoria_id
             WHERE b.estado IN ('activo', 'en_reparacion'){$filtro}
             GROUP BY nombre
             ORDER BY cantidad DESC, nombre"
        );
        $stmt->execute($params);
        $filas = array_values(array_map(
            static fn (array $f): array => ['nombre' => (string) $f['nombre'], 'cantidad' => (int) $f['cantidad']],
            $stmt->fetchAll()
        ));

        if (count($filas) <= $limite) {
            return $filas;
        }

        $resto = array_sum(array_column(array_slice($filas, $limite), 'cantidad'));

        return [...array_slice($filas, 0, $limite), ['nombre' => 'Otras categorías', 'cantidad' => $resto]];
    }

    /** Para el indicador del panel principal: bienes activos que hoy no están asignados a ningún espacio. */
    public static function contarSinAsignar(?int $institucionId = null): int
    {
        $condiciones = ['b.estado = "activo"', 'a.id IS NULL'];
        $params = [];

        if ($institucionId !== null) {
            $condiciones[] = 'b.institucion_id = ?';
            $params[] = $institucionId;
        }

        $sql = 'SELECT COUNT(*)
                FROM bienes b
                LEFT JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
                WHERE ' . implode(' AND ', $condiciones);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Siguiente código de 10 dígitos para un bien nuevo en la categoría "Sin cartera" de
     * esta institución: busca, solo entre los bienes de esa institución+categoría, el
     * código más alto que sea puramente numérico de 10 dígitos, y sugiere el consecutivo
     * — comparando como número, no como texto (para que "9" no "gane" sobre "10").
     * Si todavía no hay ninguno, sugiere "0000000001".
     */
    public static function siguienteCodigoSinCartera(int $institucionId, int $categoriaId): string
    {
        $stmt = Database::connection()->prepare(
            "SELECT codigo_identificacion FROM bienes
             WHERE institucion_id = ? AND categoria_id = ? AND codigo_identificacion REGEXP '^[0-9]{10}$'
             ORDER BY CAST(codigo_identificacion AS UNSIGNED) DESC
             LIMIT 1"
        );
        $stmt->execute([$institucionId, $categoriaId]);
        $ultimo = $stmt->fetchColumn();

        $siguiente = $ultimo !== false ? ((int) $ultimo + 1) : 1;

        return str_pad((string) $siguiente, 10, '0', STR_PAD_LEFT);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT b.*, c.nombre AS categoria_nombre
             FROM bienes b
             LEFT JOIN categorias_bienes c ON c.id = b.categoria_id
             WHERE b.id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function findPorToken(string $token): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT b.*, c.nombre AS categoria_nombre, i.nombre AS institucion_nombre,
                    CONCAT(u.nombres, " ", u.apellidos) AS qr_confirmado_por_nombre
             FROM bienes b
             LEFT JOIN categorias_bienes c ON c.id = b.categoria_id
             JOIN instituciones i ON i.id = b.institucion_id
             LEFT JOIN usuarios u ON u.id = b.qr_confirmado_por
             WHERE b.qr_token = ?'
        );
        $stmt->execute([$token]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Marca la fecha de "impreso" para un lote de bienes recién incluidos en una hoja/
     * etiqueta de /bienes/qr-masivo. Solo escribe sobre los que todavía no tenían fecha
     * (no pisa la primera impresión real si alguien vuelve a generar el mismo lote) o cuya
     * impresión es anterior a una nueva solicitud (así pasan a "impresos, por pegar").
     */
    public static function marcarQrImpreso(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            return;
        }

        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        Database::connection()->prepare(
            "UPDATE bienes SET qr_impreso_en = NOW() WHERE id IN ({$marcadores})
               AND (qr_impreso_en IS NULL OR (qr_solicitado_en IS NOT NULL AND qr_impreso_en < qr_solicitado_en))"
        )->execute($ids);
    }

    /**
     * Confirma que la etiqueta física fue escaneada y coincide con este bien (botón
     * "Confirmar etiquetado" en /qr/{token}). El llamador (QrController::confirmarEtiqueta)
     * ya garantizó que qr_impreso_en no es null antes de llegar aquí — no se puede
     * confirmar una etiqueta que el sistema no registra como impresa.
     *
     * También cancela la solicitud de impresión (si la había): confirmar el etiquetado es
     * la señal de que ya se resolvió del todo, así que el bien sale solo de la "Bodega de
     * impresión de QR" sin que nadie tenga que ir a limpiarla a mano.
     */
    public static function confirmarEtiqueta(int $id, int $usuarioId): void
    {
        Database::connection()->prepare(
            'UPDATE bienes SET qr_confirmado_en = NOW(), qr_confirmado_por = ?, qr_solicitado_en = NULL, qr_solicitado_por = NULL WHERE id = ?'
        )->execute([$usuarioId, $id]);
    }

    /**
     * Marca el bien como "pedido para imprimir" (casilla "Imprimir QR" en el formulario de
     * crear/editar) — lo agrega a la Bodega de impresión de QR. Se sobreescribe sin
     * problema si ya estaba solicitado (ej. otro usuario lo vuelve a marcar): siempre
     * queda la fecha y el solicitante más recientes.
     */
    public static function solicitarQr(int $id, int $usuarioId): void
    {
        Database::connection()->prepare(
            'UPDATE bienes SET qr_solicitado_en = NOW(), qr_solicitado_por = ? WHERE id = ?'
        )->execute([$usuarioId, $id]);
    }

    /**
     * Quita el bien de la Bodega de impresión de QR (casilla "Imprimir QR" desmarcada al
     * guardar el formulario) — no borra qr_impreso_en/qr_confirmado_en, solo retira la
     * solicitud activa.
     */
    public static function cancelarSolicitudQr(int $id): void
    {
        Database::connection()->prepare(
            'UPDATE bienes SET qr_solicitado_en = NULL, qr_solicitado_por = NULL WHERE id = ?'
        )->execute([$id]);
    }

    /**
     * Ciclo de vida del bien: a qué estados puede pasar desde cada estado.
     *
     *   activo ⇄ en_reparacion ──reintegro──→ reintegrado ──reactivar (rector)──→ activo
     *     └──────────┴────────baja aprobada────→ dado_de_baja (estado final)
     *
     * Toda transición pasa por cambiarEstado(), que la valida contra esta tabla.
     */
    public const TRANSICIONES = [
        'activo' => ['en_reparacion', 'reintegrado', 'dado_de_baja'],
        'en_reparacion' => ['activo', 'reintegrado', 'dado_de_baja'],
        'reintegrado' => ['activo'],
        'dado_de_baja' => [],
    ];

    /** Estados desde los que un bien se puede reintegrar. */
    public const ESTADOS_REINTEGRABLES = ['activo', 'en_reparacion'];

    /**
     * Cambia el estado del bien solo si la transición está permitida (TRANSICIONES). La
     * condición va en el propio UPDATE, así que dos peticiones simultáneas no pueden
     * aplicar dos transiciones incompatibles. Lanza DomainException si no se permite.
     */
    public static function cambiarEstado(int $id, string $nuevo): void
    {
        $origenes = array_keys(array_filter(
            self::TRANSICIONES,
            static fn (array $destinos): bool => in_array($nuevo, $destinos, true)
        ));
        if ($origenes === []) {
            throw new \DomainException("Estado de destino no válido: {$nuevo}.");
        }

        $marcadores = implode(', ', array_fill(0, count($origenes), '?'));
        $stmt = Database::connection()->prepare("UPDATE bienes SET estado = ? WHERE id = ? AND estado IN ({$marcadores})");
        $stmt->execute([$nuevo, $id, ...$origenes]);

        if ($stmt->rowCount() === 0) {
            $actual = self::find($id)['estado'] ?? 'inexistente';
            throw new \DomainException(
                'El bien está "' . self::etiquetaEstado($actual) . '" y no puede pasar a "' . self::etiquetaEstado($nuevo) . '".'
            );
        }
    }

    public static function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            'activo' => 'Activo',
            'reintegrado' => 'Reintegrado',
            'en_reparacion' => 'En reparación',
            'dado_de_baja' => 'Dado de baja',
            default => $estado,
        };
    }

    /**
     * Regla ÚNICA de reintegro (individual, masivo y escáner): null si el bien se puede
     * reintegrar, o el motivo si no. Antes el reintegro individual y el masivo aplicaban
     * reglas distintas (estados aceptados y categoría "Sin cartera").
     */
    public static function motivoNoReintegrable(array $bien, bool $tieneAsignacionActiva): ?string
    {
        if (!in_array($bien['estado'], self::ESTADOS_REINTEGRABLES, true)) {
            return 'el bien está "' . self::etiquetaEstado($bien['estado']) . '"';
        }
        if (!$tieneAsignacionActiva) {
            return 'el bien no tiene una asignación activa';
        }
        if ($bien['categoria_id'] === null) {
            return 'el bien no tiene categoría asignada';
        }
        if (($bien['categoria_nombre'] ?? null) === Categoria::NOMBRE_CATEGORIA_PROTEGIDA) {
            return 'los bienes de la categoría "' . Categoria::NOMBRE_CATEGORIA_PROTEGIDA . '" no admiten reintegro, solo baja';
        }

        return null;
    }

    /**
     * Si el bien tiene una asignación activa, ¿$usuarioId es uno de los responsables de
     * ese espacio? Usado para que un docente solo pueda verificar (jornada de
     * verificación física) los bienes a su cargo, igual que el filtro de "solo mis
     * bienes" en /bienes.
     */
    public static function esResponsableDe(int $bienId, int $usuarioId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1
             FROM asignaciones a
             JOIN espacio_responsables er ON er.espacio_id = a.espacio_id
             WHERE a.bien_id = ? AND a.activa = 1 AND er.usuario_id = ?
             LIMIT 1'
        );
        $stmt->execute([$bienId, $usuarioId]);

        return (bool) $stmt->fetchColumn();
    }

    public static function create(array $datos): int
    {
        $datos['qr_token'] = self::generarUuid();
        $datos['lote'] ??= null;

        $stmt = Database::connection()->prepare(
            'INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, lote, marca, categoria_id, fecha_ingreso, valor, tiene_factura, estado, qr_token, created_by)
             VALUES (:institucion_id, :codigo_identificacion, :descripcion, :lote, :marca, :categoria_id, :fecha_ingreso, :valor, :tiene_factura, :estado, :qr_token, :created_by)'
        );
        $stmt->execute($datos);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Crea varios bienes con un único INSERT multi-fila, en vez de uno por unidad (usado
     * por BienController::crearBienesEnLote() para el alta masiva de bienes idénticos,
     * hasta 500 por lote). $codigos ya viene validado por el llamador (existeAlgunCodigo()
     * antes de esto) -- aquí no se vuelve a comprobar.
     *
     * @param string[] $codigos
     */
    public static function crearVarios(int $institucionId, string $lote, array $codigos, string $descripcion, ?string $marca, ?int $categoriaId, string $fechaIngreso, float $valor, ?int $creadoPor): void
    {
        if ($codigos === []) {
            return;
        }

        $filas = [];
        $params = [];
        foreach ($codigos as $codigo) {
            $filas[] = '(?, ?, ?, ?, ?, ?, ?, ?, 0, "activo", ?, ?)';
            array_push($params, $institucionId, $codigo, $descripcion, $lote, $marca, $categoriaId, $fechaIngreso, $valor, self::generarUuid(), $creadoPor);
        }

        $sql = 'INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, lote, marca, categoria_id, fecha_ingreso, valor, tiene_factura, estado, qr_token, created_by)
                VALUES ' . implode(', ', $filas);

        Database::connection()->prepare($sql)->execute($params);
    }

    /**
     * Igual que existeCodigo(), pero comprueba una lista completa de códigos en una sola
     * consulta -- devuelve el primero que ya exista, o null si ninguno choca.
     *
     * @param string[] $codigos
     */
    public static function existeAlgunCodigo(int $institucionId, array $codigos): ?string
    {
        if ($codigos === []) {
            return null;
        }

        $marcadores = implode(',', array_fill(0, count($codigos), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT codigo_identificacion FROM bienes WHERE institucion_id = ? AND codigo_identificacion IN ({$marcadores}) LIMIT 1"
        );
        $stmt->execute([$institucionId, ...$codigos]);

        $resultado = $stmt->fetchColumn();

        return is_string($resultado) ? $resultado : null;
    }

    public static function update(int $id, array $datos): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE bienes SET codigo_identificacion = :codigo_identificacion, descripcion = :descripcion, marca = :marca,
             categoria_id = :categoria_id, fecha_ingreso = :fecha_ingreso, valor = :valor, tiene_factura = :tiene_factura,
             estado = :estado
             WHERE id = :id'
        );
        $stmt->execute([
            'codigo_identificacion' => $datos['codigo_identificacion'],
            'descripcion' => $datos['descripcion'],
            'marca' => $datos['marca'],
            'categoria_id' => $datos['categoria_id'],
            'fecha_ingreso' => $datos['fecha_ingreso'],
            'valor' => $datos['valor'],
            'tiene_factura' => $datos['tiene_factura'],
            'estado' => $datos['estado'],
            'id' => $id,
        ]);
    }

    /**
     * Reactiva un bien reintegrado — usado solo por la acción restringida
     * MovimientoController::reactivar() (rector/superusuario, con motivo obligatorio),
     * nunca por el formulario normal de edición: estadoPermitidoDesdeFormulario() bloquea
     * a propósito cualquier cambio de estado ahí para un bien reintegrado.
     */

    /**
     * Cambia el dueño (institucion_id) de un bien — usado solo por el traslado entre
     * sedes de una misma familia (MovimientoController::trasladarSede()), nunca por el
     * formulario normal de edición.
     */
    public static function cambiarInstitucion(int $id, int $institucionId): void
    {
        Database::connection()
            ->prepare('UPDATE bienes SET institucion_id = ? WHERE id = ?')
            ->execute([$institucionId, $id]);
    }

    /**
     * Al cambiar la foto se borra la huella de la búsqueda por foto (foto_vector): así el
     * bien vuelve a quedar "pendiente de indexar" (BienFotoVector::pendientesDeIndexar) y
     * se calcula la huella de la foto NUEVA. Sin esto, la búsqueda seguía encontrando el
     * bien por su foto anterior.
     */
    public static function updateFoto(int $id, string $path): void
    {
        Database::connection()->prepare('UPDATE bienes SET foto_path = ?, foto_vector = NULL WHERE id = ?')->execute([$path, $id]);
    }

    public static function updateFactura(int $id, string $path): void
    {
        Database::connection()->prepare('UPDATE bienes SET factura_pdf_path = ? WHERE id = ?')->execute([$path, $id]);
    }

    public static function buscarPorCodigoInstitucion(int $institucionId, string $codigo): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM bienes WHERE institucion_id = ? AND codigo_identificacion = ?'
        );
        $stmt->execute([$institucionId, $codigo]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Igual que buscarPorCodigoInstitucion(), pero para varios códigos a la vez (usado por
     * CargaMasivaService::analizar() para no hacer una consulta por fila del Excel).
     *
     * @param string[] $codigos
     * @return array<string, array<string, mixed>> indexado por codigo_identificacion
     */
    public static function buscarPorCodigosInstitucion(int $institucionId, array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }

        $codigos = array_values(array_unique($codigos));
        $marcadores = implode(',', array_fill(0, count($codigos), '?'));

        $stmt = Database::connection()->prepare(
            "SELECT * FROM bienes WHERE institucion_id = ? AND codigo_identificacion IN ({$marcadores})"
        );
        $stmt->execute([$institucionId, ...$codigos]);

        $resultado = [];
        foreach ($stmt->fetchAll() as $fila) {
            $resultado[$fila['codigo_identificacion']] = $fila;
        }

        return $resultado;
    }

    public static function pendientesDeReintegro(
        ?int $institucionId = null,
        ?string $busqueda = null,
        int $pagina = 1,
        int $porPagina = 50
    ): array {
        [$whereSql, $params] = self::condicionesPendientesDeReintegro($institucionId, $busqueda);

        $sql = 'SELECT b.*, a.fecha_asignacion, CONCAT(e.codigo, " - ", e.nombre) AS espacio_nombre, i.nombre AS institucion_nombre,
                       c.nombre AS categoria_nombre, ' . self::sqlResponsablesEspacio('e.id') . ' AS responsables_nombres
                FROM bienes b
                JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
                LEFT JOIN espacios e ON e.id = a.espacio_id
                LEFT JOIN categorias_bienes c ON c.id = b.categoria_id
                JOIN instituciones i ON i.id = b.institucion_id'
               . $whereSql
               . ' ORDER BY a.fecha_asignacion ASC, a.id ASC'
               . self::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Busca por código, descripción, responsable y ubicación — las columnas visibles
     * en la tabla de "pendientes de reintegro".
     */
    private static function condicionesPendientesDeReintegro(?int $institucionId, ?string $busqueda): array
    {
        $condiciones = ['b.estado = "activo"'];
        $params = [];

        if ($institucionId !== null) {
            $condiciones[] = 'b.institucion_id = ?';
            $params[] = $institucionId;
        }

        if ($busqueda !== null && $busqueda !== '') {
            $termino = '%' . $busqueda . '%';
            $condiciones[] = '(b.codigo_identificacion LIKE ? OR b.descripcion LIKE ? OR e.nombre LIKE ?
                OR EXISTS (SELECT 1 FROM espacio_responsables er JOIN usuarios u ON u.id = er.usuario_id
                           WHERE er.espacio_id = e.id AND CONCAT(u.nombres, " ", u.apellidos) LIKE ?)
                OR CAST(b.valor AS CHAR) LIKE ?)';
            array_push($params, $termino, $termino, $termino, $termino, $termino);
        }

        return [' WHERE ' . implode(' AND ', $condiciones), $params];
    }

    /**
     * Bienes operables desde /asignaciones: un bien que no esté dado de baja ni
     * reintegrado puede Asignarse o Reasignarse a un espacio, tenga ya uno o no. Ninguno
     * de los dos ya está físicamente en la institución (ver
     * MovimientoController::verificarAsignable()), así que tampoco tiene sentido que
     * aparezcan aquí como candidatos. (Los candidatos a Reintegrar se listan aparte, ver
     * reintegrables(), en la pantalla dedicada /reintegros.)
     */
    public static function operables(?int $institucionId = null, ?string $busqueda = null, int $pagina = 1, int $porPagina = 50): array
    {
        [$sql, $params] = self::sqlOperables($institucionId, $busqueda);

        $sql .= ' ORDER BY asignado ASC, b.codigo_identificacion ASC, b.id ASC' . self::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function contarOperables(?int $institucionId = null, ?string $busqueda = null): int
    {
        [$whereSql, $params] = self::condicionesOperables($institucionId, $busqueda);

        $sql = 'SELECT COUNT(*)
                FROM bienes b
                LEFT JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
                LEFT JOIN espacios e ON e.id = a.espacio_id'
               . $whereSql;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private static function sqlOperables(?int $institucionId, ?string $busqueda): array
    {
        [$whereSql, $params] = self::condicionesOperables($institucionId, $busqueda);

        $sql = 'SELECT b.id, b.codigo_identificacion, b.descripcion, b.valor,
                       CONCAT(e.codigo, " - ", e.nombre) AS espacio_nombre, ' . self::sqlResponsablesEspacio('e.id') . ' AS responsables_nombres,
                       CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END AS asignado
                FROM bienes b
                LEFT JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
                LEFT JOIN espacios e ON e.id = a.espacio_id'
               . $whereSql;

        return [$sql, $params];
    }

    /**
     * Busca por código, descripción, espacio, responsables del espacio y valor — las
     * columnas visibles en /asignaciones. Excluye los bienes dados de baja y los
     * reintegrados (ver operables() arriba).
     */
    private static function condicionesOperables(?int $institucionId, ?string $busqueda): array
    {
        $condiciones = ['b.estado NOT IN ("dado_de_baja", "reintegrado")'];
        $params = [];

        if ($institucionId !== null) {
            $condiciones[] = 'b.institucion_id = ?';
            $params[] = $institucionId;
        }

        if ($busqueda !== null && $busqueda !== '') {
            $termino = '%' . $busqueda . '%';
            $condiciones[] = '(b.codigo_identificacion LIKE ? OR b.descripcion LIKE ? OR e.nombre LIKE ?
                OR EXISTS (SELECT 1 FROM espacio_responsables er JOIN usuarios u ON u.id = er.usuario_id
                           WHERE er.espacio_id = e.id AND CONCAT(u.nombres, " ", u.apellidos) LIKE ?)
                OR CAST(b.valor AS CHAR) LIKE ?)';
            array_push($params, $termino, $termino, $termino, $termino, $termino);
        }

        return [' WHERE ' . implode(' AND ', $condiciones), $params];
    }

    /**
     * Bienes reintegrables desde /reintegros: 'activo' y con una asignación vigente
     * (a diferencia de operables(), aquí sí se filtra desde la BD — la pantalla de
     * reintegro solo debe listar lo que realmente se puede reintegrar).
     */
    public static function reintegrables(?int $institucionId = null, ?string $busqueda = null, int $pagina = 1, int $porPagina = 50, ?array $soloIds = null, ?int $categoriaId = null): array
    {
        if ($soloIds !== null && empty($soloIds)) {
            return [];
        }

        [$whereSql, $params] = self::condicionesReintegrables($institucionId, $busqueda, $soloIds, $categoriaId);

        $sql = 'SELECT b.id, b.codigo_identificacion, b.descripcion, b.valor, b.foto_path,
                       c.nombre AS categoria_nombre,
                       CONCAT(e.codigo, " - ", e.nombre) AS espacio_nombre, ' . self::sqlResponsablesEspacio('e.id') . ' AS responsables_nombres
                FROM bienes b
                JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
                LEFT JOIN espacios e ON e.id = a.espacio_id
                LEFT JOIN categorias_bienes c ON c.id = b.categoria_id'
               . $whereSql
               . ' ORDER BY b.codigo_identificacion ASC, b.id ASC' . self::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Busca un bien por su qr_token (escaneado desde /reintegros) verificando que sea
     * reintegrable: misma institución y con una asignación activa. Devuelve null tanto
     * si el token no existe como si el bien no cumple esas condiciones — el llamador no
     * necesita distinguir el motivo, solo informar que ese bien no se puede reintegrar.
     */
    public static function buscarReintegrablePorToken(string $token, int $institucionId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT b.id, b.codigo_identificacion, b.descripcion,
                    CONCAT(e.codigo, " - ", e.nombre) AS espacio_nombre, ' . self::sqlResponsablesEspacio('e.id') . ' AS responsables_nombres
             FROM bienes b
             JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
             LEFT JOIN espacios e ON e.id = a.espacio_id
             WHERE b.qr_token = ? AND b.institucion_id = ?' . self::sqlReglaReintegrable()
        );
        $stmt->execute([$token, $institucionId, Categoria::NOMBRE_CATEGORIA_PROTEGIDA]);

        return $stmt->fetch() ?: null;
    }

    public static function contarReintegrables(?int $institucionId = null, ?string $busqueda = null, ?array $soloIds = null, ?int $categoriaId = null): int
    {
        if ($soloIds !== null && empty($soloIds)) {
            return 0;
        }

        [$whereSql, $params] = self::condicionesReintegrables($institucionId, $busqueda, $soloIds, $categoriaId);

        $sql = 'SELECT COUNT(*)
                FROM bienes b
                JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
                LEFT JOIN espacios e ON e.id = a.espacio_id'
               . $whereSql;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * $soloIds filtra por un conjunto puntual de bienes (usado por "Ver solo
     * seleccionados" en /reintegros, donde la selección vive en sessionStorage del
     * navegador y abarca varias páginas — el servidor no la conoce hasta que se le pasa).
     */
    private static function condicionesReintegrables(?int $institucionId, ?string $busqueda, ?array $soloIds = null, ?int $categoriaId = null): array
    {
        $condiciones = ['1 = 1' . self::sqlReglaReintegrable()];
        $params = [Categoria::NOMBRE_CATEGORIA_PROTEGIDA];

        if ($institucionId !== null) {
            $condiciones[] = 'b.institucion_id = ?';
            $params[] = $institucionId;
        }

        if ($categoriaId !== null) {
            $condiciones[] = 'b.categoria_id = ?';
            $params[] = $categoriaId;
        }

        if ($soloIds !== null && !empty($soloIds)) {
            $marcadores = implode(',', array_fill(0, count($soloIds), '?'));
            $condiciones[] = "b.id IN ({$marcadores})";
            array_push($params, ...$soloIds);
        }

        if ($busqueda !== null && $busqueda !== '') {
            $termino = '%' . $busqueda . '%';
            $condiciones[] = '(b.codigo_identificacion LIKE ? OR b.descripcion LIKE ? OR e.nombre LIKE ?
                OR EXISTS (SELECT 1 FROM espacio_responsables er JOIN usuarios u ON u.id = er.usuario_id
                           WHERE er.espacio_id = e.id AND CONCAT(u.nombres, " ", u.apellidos) LIKE ?)
                OR CAST(b.valor AS CHAR) LIKE ?)';
            array_push($params, $termino, $termino, $termino, $termino, $termino);
        }

        return [' WHERE ' . implode(' AND ', $condiciones), $params];
    }

    public static function existeCodigo(int $institucionId, string $codigo, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM bienes WHERE institucion_id = ? AND codigo_identificacion = ?';
        $params = [$institucionId, $codigo];

        if ($exceptId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public static function existeLote(int $institucionId, string $lote): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM bienes WHERE institucion_id = ? AND lote = ? LIMIT 1');
        $stmt->execute([$institucionId, $lote]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Un resumen por lote (conteo total y por estado, valor total) para la sección
     * "Lotes" de /bienes — evita mostrar de a una las decenas/cientos de filas casi
     * idénticas que genera crearLoteIdentico(). MIN(descripcion)/MIN(categoria) porque
     * todos los bienes de un mismo lote comparten esos datos (son el mismo artículo).
     */
    public static function listarLotes(int $institucionId, ?string $busqueda = null, ?int $categoriaId = null): array
    {
        $condiciones = ['b.institucion_id = ?', 'b.lote IS NOT NULL'];
        $params = [$institucionId];

        if ($categoriaId !== null) {
            $condiciones[] = 'b.categoria_id = ?';
            $params[] = $categoriaId;
        }

        if ($busqueda !== null && $busqueda !== '') {
            $termino = '%' . $busqueda . '%';
            $condiciones[] = '(b.lote LIKE ? OR b.descripcion LIKE ?)';
            array_push($params, $termino, $termino);
        }

        $stmt = Database::connection()->prepare(
            'SELECT b.lote, MIN(b.descripcion) AS descripcion, MIN(c.nombre) AS categoria_nombre,
                    COUNT(*) AS total,
                    SUM(CASE WHEN b.estado = "activo" THEN 1 ELSE 0 END) AS activos,
                    SUM(CASE WHEN b.estado = "reintegrado" THEN 1 ELSE 0 END) AS reintegrados,
                    SUM(CASE WHEN b.estado = "en_reparacion" THEN 1 ELSE 0 END) AS en_reparacion,
                    SUM(CASE WHEN b.estado = "dado_de_baja" THEN 1 ELSE 0 END) AS dados_de_baja,
                    SUM(b.valor) AS valor_total
             FROM bienes b
             LEFT JOIN categorias_bienes c ON c.id = b.categoria_id
             WHERE ' . implode(' AND ', $condiciones) . '
             GROUP BY b.lote
             ORDER BY b.lote'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private static function generarUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Condición SQL de motivoNoReintegrable() (sin la asignación activa, que los listados
     * ya exigen con su JOIN). Espera un parámetro: el nombre de la categoría protegida.
     */
    private static function sqlReglaReintegrable(): string
    {
        return " AND b.estado IN ('activo', 'en_reparacion') AND b.categoria_id IS NOT NULL"
            . ' AND b.categoria_id NOT IN (SELECT id FROM categorias_bienes WHERE nombre = ?)';
    }
}
