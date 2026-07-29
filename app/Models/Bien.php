<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Bien
{
    public static function listar(?int $institucionId = null, ?string $busqueda = null, int $pagina = 1, int $porPagina = 50): array
    {
        [$whereSql, $params] = self::condicionesListado($institucionId, $busqueda);

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

    public static function contarListado(?int $institucionId = null, ?string $busqueda = null): int
    {
        [$whereSql, $params] = self::condicionesListado($institucionId, $busqueda);

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
     * con o sin guion bajo) y valor — las mismas columnas visibles en /bienes. El responsable
     * ahora es el espacio (y sus responsables), no una persona asignada directamente al bien.
     */
    private static function condicionesListado(?int $institucionId, ?string $busqueda): array
    {
        $condiciones = [];
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
                OR REPLACE(b.estado, "_", " ") LIKE ? OR CAST(b.valor AS CHAR) LIKE ?)';
            array_push($params, $termino, $termino, $termino, $termino, $termino, $termino);
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
    public static function listarPropios(int $usuarioId, ?int $institucionId = null, ?string $busqueda = null, int $pagina = 1, int $porPagina = 50): array
    {
        [$whereSql, $params] = self::condicionesPropios($institucionId, $busqueda);

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

    public static function contarPropios(int $usuarioId, ?int $institucionId = null, ?string $busqueda = null): int
    {
        [$whereSql, $params] = self::condicionesPropios($institucionId, $busqueda);

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

    private static function condicionesPropios(?int $institucionId, ?string $busqueda): array
    {
        $condiciones = [];
        $params = [];

        if ($institucionId !== null) {
            $condiciones[] = 'b.institucion_id = ?';
            $params[] = $institucionId;
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
            'SELECT b.*, c.nombre AS categoria_nombre, i.nombre AS institucion_nombre
             FROM bienes b
             LEFT JOIN categorias_bienes c ON c.id = b.categoria_id
             JOIN instituciones i ON i.id = b.institucion_id
             WHERE b.qr_token = ?'
        );
        $stmt->execute([$token]);

        return $stmt->fetch() ?: null;
    }

    public static function marcarDadoDeBaja(int $id): void
    {
        Database::connection()->prepare("UPDATE bienes SET estado = 'dado_de_baja' WHERE id = ?")->execute([$id]);
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

        $stmt = Database::connection()->prepare(
            'INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, marca, categoria_id, fecha_ingreso, valor, tiene_factura, estado, qr_token, created_by)
             VALUES (:institucion_id, :codigo_identificacion, :descripcion, :marca, :categoria_id, :fecha_ingreso, :valor, :tiene_factura, :estado, :qr_token, :created_by)'
        );
        $stmt->execute($datos);

        return (int) Database::connection()->lastInsertId();
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

    public static function updateFoto(int $id, string $path): void
    {
        Database::connection()->prepare('UPDATE bienes SET foto_path = ? WHERE id = ?')->execute([$path, $id]);
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

    public static function pendientesDeReintegro(
        ?int $institucionId = null,
        ?string $busqueda = null,
        int $pagina = 1,
        int $porPagina = 50
    ): array {
        [$whereSql, $params] = self::condicionesPendientesDeReintegro($institucionId, $busqueda);

        $sql = 'SELECT b.*, a.fecha_asignacion, CONCAT(e.codigo, " - ", e.nombre) AS espacio_nombre, i.nombre AS institucion_nombre,
                       ' . self::sqlResponsablesEspacio('e.id') . ' AS responsables_nombres
                FROM bienes b
                JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
                LEFT JOIN espacios e ON e.id = a.espacio_id
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
     * Bienes operables desde /asignaciones: todo bien no dado de baja puede Asignarse o
     * Reasignarse a un espacio, tenga ya uno o no. (Los candidatos a Reintegrar se listan
     * aparte, ver reintegrables(), en la pantalla dedicada /reintegros.)
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
     * columnas visibles en /asignaciones. Excluye solo los bienes dados de baja.
     */
    private static function condicionesOperables(?int $institucionId, ?string $busqueda): array
    {
        $condiciones = ['b.estado != "dado_de_baja"'];
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
    public static function reintegrables(?int $institucionId = null, ?string $busqueda = null, int $pagina = 1, int $porPagina = 50): array
    {
        [$whereSql, $params] = self::condicionesReintegrables($institucionId, $busqueda);

        $sql = 'SELECT b.id, b.codigo_identificacion, b.descripcion, b.valor,
                       CONCAT(e.codigo, " - ", e.nombre) AS espacio_nombre, ' . self::sqlResponsablesEspacio('e.id') . ' AS responsables_nombres
                FROM bienes b
                JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
                LEFT JOIN espacios e ON e.id = a.espacio_id'
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
             WHERE b.qr_token = ? AND b.institucion_id = ? AND b.estado = "activo"'
        );
        $stmt->execute([$token, $institucionId]);

        return $stmt->fetch() ?: null;
    }

    public static function contarReintegrables(?int $institucionId = null, ?string $busqueda = null): int
    {
        [$whereSql, $params] = self::condicionesReintegrables($institucionId, $busqueda);

        $sql = 'SELECT COUNT(*)
                FROM bienes b
                JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
                LEFT JOIN espacios e ON e.id = a.espacio_id'
               . $whereSql;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private static function condicionesReintegrables(?int $institucionId, ?string $busqueda): array
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

    private static function generarUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
