<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\AuditoriaFormato;
use App\Helpers\LibroXlsxStreaming;
use App\Models\Cargo;
use App\Models\Categoria;
use App\Models\Institucion;
use App\Models\Usuario;
use PDO;

/**
 * Reportes en Excel para el control del trabajo de los funcionarios (Reportes > "Actividad de
 * los funcionarios"): resumen por funcionario, registros nuevos, registros actualizados y
 * movimientos de un período, o todo en un solo libro.
 *
 * Fuentes: la auditoría (quién, qué, cuándo, y el antes/después de cada cambio) y, para los
 * bienes nuevos, bienes.created_by/created_at (así cuentan también los de la carga masiva,
 * que la auditoría registra por lote y no por bien).
 *
 * Las fechas se filtran por instante exacto (FROM_UNIXTIME / UNIX_TIMESTAMP) y se muestran en
 * hora de Colombia desde PHP: el período es correcto aunque la base esté en otra zona horaria.
 */
final class ReportesControl
{
    public const TIPOS_ACTIVIDAD = [
        'resumen' => 'Resumen por funcionario',
        'nuevos' => 'Registros nuevos',
        'actualizados' => 'Registros actualizados',
        'movimientos' => 'Movimientos',
        'todo' => 'Actividad completa',
    ];

    public const PERIODOS = [
        'hoy' => 'Hoy',
        'ayer' => 'Ayer',
        '7dias' => 'Últimos 7 días',
        'mes' => 'Este mes',
        'rango' => 'Rango de fechas',
    ];

    private const ENTIDADES = [
        'usuario' => 'Usuario', 'espacio' => 'Espacio', 'categoria' => 'Categoría', 'cargo' => 'Cargo', 'bien' => 'Bien',
        'institucion' => 'Institución', 'factura_administrativa' => 'Factura', 'formato_reintegro' => 'Formato de reintegro',
        'formato_plaqueteo' => 'Formato de plaqueteo', 'cartera_envio' => 'Cartera', 'baja' => 'Baja',
        'solicitud_reintegro' => 'Solicitud de reintegro', 'lote_reintegro' => 'Lote de reintegro',
        'carga_masiva' => 'Carga masiva', 'jornada_verificacion' => 'Verificación física', 'hallazgo' => 'Hallazgo',
    ];

    /** Acción de la auditoría => nombre del movimiento (hoja "Movimientos"). */
    private const MOVIMIENTOS = [
        'asignar' => 'Asignación', 'trasladar' => 'Traslado', 'trasladar_sede' => 'Traslado de sede',
        'reintegrar' => 'Reintegro', 'reactivar' => 'Reactivación',
        'reportar' => 'Baja reportada', 'aprobar' => 'Baja aprobada', 'rechazar' => 'Baja rechazada',
    ];

    private const ACCIONES_ACTUALIZACION = ['editar', 'activar', 'desactivar', 'restaurar'];

    private const COLUMNAS_RESUMEN = ['Registros nuevos', 'Actualizaciones', 'Enviados a la papelera', 'Movimientos',
        'QR pegados', 'Bajas reportadas', 'Bajas resueltas', 'Verificaciones', 'Otras acciones', 'Ingresos al sistema'];

    /**
     * Período en instantes exactos [desde, hasta), en hora de Colombia (la de la app).
     *
     * @return array{desde: int, hasta: int, etiqueta: string}
     */
    public static function periodo(string $clave, string $desde = '', string $hasta = ''): array
    {
        $hoy = strtotime('today');
        [$inicio, $fin] = match ($clave) {
            'ayer' => [strtotime('-1 day', $hoy), $hoy],
            '7dias' => [strtotime('-6 days', $hoy), strtotime('+1 day', $hoy)],
            'mes' => [strtotime(date('Y-m-01', $hoy)), strtotime('+1 day', $hoy)],
            'rango' => self::rango($desde, $hasta, $hoy),
            default => [$hoy, strtotime('+1 day', $hoy)],
        };
        $inicio = (int) $inicio;
        $fin = (int) $fin;
        $ultimoDia = $fin - 86400;
        $etiqueta = date('d/m/Y', $inicio) . ($ultimoDia > $inicio ? ' al ' . date('d/m/Y', $ultimoDia) : '');

        return ['desde' => $inicio, 'hasta' => $fin, 'etiqueta' => $etiqueta];
    }

    /** @return array{0: int, 1: int} */
    private static function rango(string $desde, string $hasta, int $hoy): array
    {
        $valida = static fn (string $f): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) === 1 && strtotime($f) !== false;
        $inicio = $valida($desde) ? (int) strtotime($desde) : $hoy;
        $fin = $valida($hasta) ? (int) strtotime($hasta) : $inicio;
        if ($fin < $inicio) {
            [$inicio, $fin] = [$fin, $inicio];
        }
        // Máximo un año, para que un rango enorme no tumbe el servidor.
        $fin = min($fin, (int) strtotime('+366 days', $inicio));

        return [$inicio, (int) strtotime('+1 day', $fin)];
    }

    /**
     * Genera el Excel pedido en $carpeta y devuelve su ruta y nombre de descarga.
     *
     * @param list<int>|null $institucionIds null = todas (solo superusuario)
     * @param array{desde: int, hasta: int, etiqueta: string} $periodo
     * @return array{ruta: string, nombre: string}
     */
    public static function actividadXlsx(string $tipo, ?array $institucionIds, ?int $usuarioId, array $periodo,
        string $alcance, string $generadoPor, string $carpeta): array
    {
        if (!isset(self::TIPOS_ACTIVIDAD[$tipo])) {
            throw new \InvalidArgumentException('Tipo de reporte no válido.');
        }
        if (!is_dir($carpeta) && !mkdir($carpeta, 0700, true) && !is_dir($carpeta)) {
            throw new \RuntimeException('No se pudo crear la carpeta temporal.');
        }
        $trabajo = $carpeta . '/actividad-' . bin2hex(random_bytes(8));
        mkdir($trabajo, 0700);

        $filtro = new FiltroActividad($institucionIds, $usuarioId, $periodo['desde'], $periodo['hasta']);
        $libro = new LibroXlsxStreaming($trabajo);

        $funcionario = $usuarioId !== null ? (Usuario::mapaIdNombreCompleto()[$usuarioId] ?? "#{$usuarioId}") : 'Todos';
        $libro->agregarHojaDesdeFilas('Información', ['Dato', 'Valor'], [
            ['Reporte', self::TIPOS_ACTIVIDAD[$tipo]],
            ['Institución', $alcance],
            ['Período', $periodo['etiqueta']],
            ['Funcionario', $funcionario],
            ['Generado por', $generadoPor],
            ['Generado el', date('d/m/Y H:i')],
            ['Nota', 'Horas en hora de Colombia. Los bienes de la carga masiva cuentan como registros nuevos de quien la aplicó.'],
        ]);

        if ($tipo === 'resumen' || $tipo === 'todo') {
            $libro->agregarHojaDesdeFilas('Resumen por funcionario',
                ['Funcionario', 'Rol', 'Sede', ...self::COLUMNAS_RESUMEN, 'Primera actividad', 'Última actividad'],
                self::filasResumen($filtro), self::COLUMNAS_RESUMEN);
        }
        if ($tipo === 'nuevos' || $tipo === 'todo') {
            $libro->agregarHojaDesdeFilas('Registros nuevos',
                ['Fecha y hora', 'Funcionario', 'Tipo', 'Código', 'Descripción o nombre', 'Categoría', 'Espacio', 'Sede'],
                self::filasNuevos($filtro));
        }
        if ($tipo === 'actualizados' || $tipo === 'todo') {
            $libro->agregarHojaDesdeFilas('Registros actualizados',
                ['Fecha y hora', 'Funcionario', 'Acción', 'Tipo de registro', 'Registro', 'Campo', 'Valor anterior', 'Valor nuevo', 'Sede'],
                self::filasActualizados($filtro));
        }
        if ($tipo === 'movimientos' || $tipo === 'todo') {
            $libro->agregarHojaDesdeFilas('Movimientos',
                ['Fecha y hora', 'Funcionario', 'Movimiento', 'Código del bien', 'Bien', 'De', 'A', 'Observaciones', 'Sede'],
                self::filasMovimientos($filtro));
        }

        $ruta = $trabajo . '.xlsx';
        $libro->guardar($ruta);
        @rmdir($trabajo);

        return [
            'ruta' => $ruta,
            'nombre' => 'MIA_actividad_' . $tipo . '_' . date('Y-m-d', $periodo['desde'])
                . ($periodo['hasta'] - $periodo['desde'] > 86400 ? '_a_' . date('Y-m-d', $periodo['hasta'] - 86400) : '') . '.xlsx',
        ];
    }

    /** @return list<array<int, mixed>> */
    private static function filasResumen(FiltroActividad $f): array
    {
        $pdo = Database::connection();
        $porUsuario = [];
        $sumar = static function (int $usuario, string $columna, int $n, int $primera, int $ultima) use (&$porUsuario): void {
            $porUsuario[$usuario] ??= array_fill_keys(self::COLUMNAS_RESUMEN, 0) + ['primera' => PHP_INT_MAX, 'ultima' => 0];
            $porUsuario[$usuario][$columna] += $n;
            $porUsuario[$usuario]['primera'] = min($porUsuario[$usuario]['primera'], $primera);
            $porUsuario[$usuario]['ultima'] = max($porUsuario[$usuario]['ultima'], $ultima);
        };

        [$donde, $params] = $f->auditoria('a');
        $stmt = $pdo->prepare(
            "SELECT a.usuario_id, a.accion, a.entidad, COUNT(*) AS n,
                    MIN(UNIX_TIMESTAMP(a.created_at)) AS primera, MAX(UNIX_TIMESTAMP(a.created_at)) AS ultima
               FROM auditoria a WHERE a.usuario_id IS NOT NULL AND {$donde}
              GROUP BY a.usuario_id, a.accion, a.entidad"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $accion = (string) $r['accion'];
            $entidad = (string) $r['entidad'];
            $columna = match (true) {
                $accion === 'crear' && $entidad === 'bien' => null, // se cuentan desde la tabla bienes (abajo)
                $accion === 'crear' => 'Registros nuevos',
                in_array($accion, self::ACCIONES_ACTUALIZACION, true) => 'Actualizaciones',
                $accion === 'eliminar' => 'Enviados a la papelera',
                $accion === 'confirmar_qr' => 'QR pegados',
                $accion === 'reportar' && $entidad === 'baja' => 'Bajas reportadas',
                in_array($accion, ['aprobar', 'rechazar'], true) && $entidad === 'baja' => 'Bajas resueltas',
                isset(self::MOVIMIENTOS[$accion]) => 'Movimientos',
                $accion === 'login_ok' => 'Ingresos al sistema',
                default => 'Otras acciones',
            };
            if ($columna !== null) {
                $sumar((int) $r['usuario_id'], $columna, (int) $r['n'], (int) $r['primera'], (int) $r['ultima']);
            }
        }

        [$donde, $params] = $f->tabla('b.created_at', 'b.institucion_id', 'b.created_by');
        $stmt = $pdo->prepare(
            "SELECT b.created_by AS usuario_id, COUNT(*) AS n,
                    MIN(UNIX_TIMESTAMP(b.created_at)) AS primera, MAX(UNIX_TIMESTAMP(b.created_at)) AS ultima
               FROM bienes b WHERE b.created_by IS NOT NULL AND {$donde} GROUP BY b.created_by"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $sumar((int) $r['usuario_id'], 'Registros nuevos', (int) $r['n'], (int) $r['primera'], (int) $r['ultima']);
        }

        [$donde, $params] = $f->tabla('v.created_at', 'b.institucion_id', 'v.usuario_id');
        $stmt = $pdo->prepare(
            "SELECT v.usuario_id, COUNT(*) AS n,
                    MIN(UNIX_TIMESTAMP(v.created_at)) AS primera, MAX(UNIX_TIMESTAMP(v.created_at)) AS ultima
               FROM verificaciones_bienes v JOIN bienes b ON b.id = v.bien_id
              WHERE v.usuario_id IS NOT NULL AND {$donde} GROUP BY v.usuario_id"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $sumar((int) $r['usuario_id'], 'Verificaciones', (int) $r['n'], (int) $r['primera'], (int) $r['ultima']);
        }

        if ($porUsuario === []) {
            return [];
        }
        $marcadores = implode(',', array_fill(0, count($porUsuario), '?'));
        $stmt = $pdo->prepare(
            "SELECT u.id, TRIM(CONCAT_WS(' ', u.nombres, u.apellidos)) AS nombre, r.nombre AS rol, i.nombre AS sede
               FROM usuarios u JOIN roles r ON r.id = u.rol_id JOIN instituciones i ON i.id = u.institucion_id
              WHERE u.id IN ({$marcadores})"
        );
        $stmt->execute(array_keys($porUsuario));
        $datos = [];
        foreach ($stmt->fetchAll() as $u) {
            $datos[(int) $u['id']] = $u;
        }

        $filas = [];
        foreach ($porUsuario as $id => $c) {
            $u = $datos[$id] ?? ['nombre' => "#{$id}", 'rol' => '', 'sede' => ''];
            $fila = [$u['nombre'], ucfirst((string) $u['rol']), $u['sede']];
            foreach (self::COLUMNAS_RESUMEN as $columna) {
                $fila[] = $c[$columna];
            }
            $fila[] = date('Y-m-d H:i', (int) $c['primera']);
            $fila[] = date('Y-m-d H:i', (int) $c['ultima']);
            $filas[] = $fila;
        }
        usort($filas, static fn (array $a, array $b): int => strcasecmp((string) $a[0], (string) $b[0]));

        return $filas;
    }

    /** @return list<array<int, mixed>> */
    private static function filasNuevos(FiltroActividad $f): array
    {
        $pdo = Database::connection();
        $filas = [];

        [$donde, $params] = $f->tabla('b.created_at', 'b.institucion_id', 'b.created_by');
        $stmt = $pdo->prepare(
            "SELECT UNIX_TIMESTAMP(b.created_at) AS t, TRIM(CONCAT_WS(' ', u.nombres, u.apellidos)) AS funcionario,
                    b.codigo_identificacion, b.descripcion, c.nombre AS categoria, i.nombre AS sede,
                    (SELECT e.nombre FROM asignaciones a JOIN espacios e ON e.id = a.espacio_id
                      WHERE a.bien_id = b.id AND a.activa = 1 ORDER BY a.id DESC LIMIT 1) AS espacio
               FROM bienes b
               JOIN instituciones i ON i.id = b.institucion_id
               LEFT JOIN usuarios u ON u.id = b.created_by
               LEFT JOIN categorias_bienes c ON c.id = b.categoria_id
              WHERE {$donde}"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $filas[] = [(int) $r['t'], [date('Y-m-d H:i:s', (int) $r['t']), $r['funcionario'], 'Bien', $r['codigo_identificacion'],
                $r['descripcion'], $r['categoria'], $r['espacio'], $r['sede']]];
        }

        [$donde, $params] = $f->auditoria('a');
        $stmt = $pdo->prepare(
            "SELECT UNIX_TIMESTAMP(a.created_at) AS t, TRIM(CONCAT_WS(' ', u.nombres, u.apellidos)) AS funcionario,
                    a.entidad, a.entidad_id, a.datos_despues, i.nombre AS sede
               FROM auditoria a
               LEFT JOIN usuarios u ON u.id = a.usuario_id
               LEFT JOIN instituciones i ON i.id = a.institucion_id
              WHERE a.accion = 'crear' AND a.entidad <> 'bien' AND {$donde}"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $datos = json_decode((string) $r['datos_despues'], true);
            $datos = is_array($datos) ? $datos : [];
            $codigo = (string) ($datos['codigo'] ?? $datos['documento'] ?? $datos['codigo_dane'] ?? '');
            $nombre = trim((string) ($datos['nombre'] ?? trim(($datos['nombres'] ?? '') . ' ' . ($datos['apellidos'] ?? '')) ?: ($datos['descripcion'] ?? '')));
            $filas[] = [(int) $r['t'], [date('Y-m-d H:i:s', (int) $r['t']), $r['funcionario'],
                self::ENTIDADES[$r['entidad']] ?? (string) $r['entidad'], $codigo, $nombre !== '' ? $nombre : '#' . $r['entidad_id'], '', '', $r['sede']]];
        }

        usort($filas, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_map(static fn (array $f): array => $f[1], $filas);
    }

    /** @return \Generator<array<int, mixed>> */
    private static function filasActualizados(FiltroActividad $f): \Generator
    {
        $mapas = self::mapas();
        [$donde, $params] = $f->auditoria('a');
        $marcadores = implode(',', array_fill(0, count(self::ACCIONES_ACTUALIZACION), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT UNIX_TIMESTAMP(a.created_at) AS t, TRIM(CONCAT_WS(' ', u.nombres, u.apellidos)) AS funcionario,
                    a.accion, a.entidad, a.entidad_id, a.datos_antes, a.datos_despues, i.nombre AS sede,
                    b.codigo_identificacion, b.descripcion
               FROM auditoria a
               LEFT JOIN usuarios u ON u.id = a.usuario_id
               LEFT JOIN instituciones i ON i.id = a.institucion_id
               LEFT JOIN bienes b ON a.entidad = 'bien' AND b.id = a.entidad_id
              WHERE a.accion IN ({$marcadores}) AND {$donde}
              ORDER BY a.created_at, a.id"
        );
        $stmt->execute([...self::ACCIONES_ACTUALIZACION, ...$params]);
        $acciones = ['editar' => 'Editó', 'activar' => 'Activó', 'desactivar' => 'Desactivó', 'restaurar' => 'Restauró'];

        foreach ($stmt->fetchAll() as $r) {
            $antes = json_decode((string) $r['datos_antes'], true);
            $despues = json_decode((string) $r['datos_despues'], true);
            $registro = $r['entidad'] === 'bien' && $r['codigo_identificacion'] !== null
                ? $r['codigo_identificacion'] . ' · ' . $r['descripcion']
                : self::nombreRegistro(is_array($antes) ? $antes : (is_array($despues) ? $despues : []), (int) $r['entidad_id']);
            $cambios = AuditoriaFormato::resumen(is_array($antes) ? $antes : null, is_array($despues) ? $despues : null, $mapas);
            $base = [date('Y-m-d H:i:s', (int) $r['t']), $r['funcionario'], $acciones[$r['accion']] ?? $r['accion'],
                self::ENTIDADES[$r['entidad']] ?? (string) $r['entidad'], $registro];
            if ($cambios === [] || $r['accion'] !== 'editar') {
                yield [...$base, '', '', '', $r['sede']];
                continue;
            }
            foreach ($cambios as $c) {
                yield [...$base, $c['etiqueta'], $c['antes'], $c['despues'], $r['sede']];
            }
        }
    }

    /** @return \Generator<array<int, mixed>> */
    private static function filasMovimientos(FiltroActividad $f): \Generator
    {
        $espacios = self::mapaEspacios();
        [$donde, $params] = $f->auditoria('a');
        $marcadores = implode(',', array_fill(0, count(self::MOVIMIENTOS), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT UNIX_TIMESTAMP(a.created_at) AS t, TRIM(CONCAT_WS(' ', u.nombres, u.apellidos)) AS funcionario,
                    a.accion, a.entidad, a.datos_antes, a.datos_despues, i.nombre AS sede,
                    COALESCE(b.codigo_identificacion, bb_b.codigo_identificacion) AS codigo,
                    COALESCE(b.descripcion, bb_b.descripcion) AS descripcion
               FROM auditoria a
               LEFT JOIN usuarios u ON u.id = a.usuario_id
               LEFT JOIN instituciones i ON i.id = a.institucion_id
               LEFT JOIN bienes b ON a.entidad = 'bien' AND b.id = a.entidad_id
               LEFT JOIN bajas_bienes bb ON a.entidad = 'baja' AND bb.id = a.entidad_id
               LEFT JOIN bienes bb_b ON bb_b.id = bb.bien_id
              WHERE a.accion IN ({$marcadores}) AND a.entidad IN ('bien', 'baja') AND {$donde}
              ORDER BY a.created_at, a.id"
        );
        $stmt->execute([...array_keys(self::MOVIMIENTOS), ...$params]);

        foreach ($stmt->fetchAll() as $r) {
            $antes = json_decode((string) $r['datos_antes'], true);
            $despues = json_decode((string) $r['datos_despues'], true);
            $antes = is_array($antes) ? $antes : [];
            $despues = is_array($despues) ? $despues : [];
            $espacio = static fn (mixed $id): string => $id !== null && $id !== '' ? ($espacios[(int) $id] ?? "#{$id}") : '';
            $de = $espacio($antes['espacio_id'] ?? null);
            $a = $espacio($despues['espacio_id'] ?? null) ?: (string) ($despues['destino'] ?? $despues['institucion'] ?? '');
            $observaciones = (string) ($despues['observaciones'] ?? $despues['motivo'] ?? $despues['descripcion'] ?? '');
            yield [date('Y-m-d H:i:s', (int) $r['t']), $r['funcionario'], self::MOVIMIENTOS[$r['accion']] ?? $r['accion'],
                $r['codigo'], $r['descripcion'], $de, $a, $observaciones, $r['sede']];
        }
    }

    public const TIPOS_CONTROL = [
        'calidad' => 'Calidad del inventario',
        'valor' => 'Valor por espacio y categoría',
        'inactivos' => 'Funcionarios inactivos',
    ];

    /** Días sin ingresar a partir de los cuales un funcionario cuenta como inactivo. */
    public const DIAS_INACTIVO = 30;

    /** Solo los bienes que siguen en el inventario (ni reintegrados ni dados de baja). */
    private const EN_INVENTARIO = "b.estado IN ('activo', 'en_reparacion')";

    /**
     * Excel de control del inventario: calidad, valor por espacio y categoría, o funcionarios
     * inactivos.
     *
     * @param list<int>|null $institucionIds null = todas (solo superusuario)
     * @return array{ruta: string, nombre: string}
     */
    public static function controlXlsx(string $tipo, ?array $institucionIds, string $alcance, string $generadoPor, string $carpeta): array
    {
        if (!isset(self::TIPOS_CONTROL[$tipo])) {
            throw new \InvalidArgumentException('Tipo de reporte no válido.');
        }
        if (!is_dir($carpeta) && !mkdir($carpeta, 0700, true) && !is_dir($carpeta)) {
            throw new \RuntimeException('No se pudo crear la carpeta temporal.');
        }
        $trabajo = $carpeta . '/control-' . bin2hex(random_bytes(8));
        mkdir($trabajo, 0700);
        $libro = new LibroXlsxStreaming($trabajo);
        $pdo = Database::connection();
        $en = static fn (string $columna): string => $institucionIds === null
            ? '1 = 1'
            : ($institucionIds === [] ? '1 = 0' : "{$columna} IN (" . implode(',', array_map('intval', $institucionIds)) . ')');

        $notas = [
            'calidad' => 'Solo bienes en el inventario (activos o en reparación). "Sin asignar": sin espacio ni persona responsable. "Individual (sin espacio)": a cargo de una persona, sin espacio.',
            'valor' => 'Solo bienes en el inventario (activos o en reparación). Valores en pesos.',
            'inactivos' => 'Usuarios activos que no ingresan hace ' . self::DIAS_INACTIVO . ' días o más, o que nunca han ingresado.',
        ];
        $libro->agregarHojaDesdeFilas('Información', ['Dato', 'Valor'], [
            ['Reporte', self::TIPOS_CONTROL[$tipo]],
            ['Institución', $alcance],
            ['Generado por', $generadoPor],
            ['Generado el', date('d/m/Y H:i')],
            ['Nota', $notas[$tipo]],
        ]);

        // Espacio actual de cada bien (su asignación activa). Sin asignación: "Sin asignar";
        // a cargo de una persona sin espacio: "Individual (sin espacio)".
        $ubicacion = 'LEFT JOIN (SELECT bien_id, MAX(espacio_id) AS espacio_id FROM asignaciones WHERE activa = 1 GROUP BY bien_id) ua
                        ON ua.bien_id = b.id
                      LEFT JOIN espacios e ON e.id = ua.espacio_id';
        $nombreEspacio = "CASE WHEN ua.bien_id IS NULL THEN 'Sin asignar' WHEN e.id IS NULL THEN 'Individual (sin espacio)'
                               ELSE CONCAT_WS(' - ', NULLIF(e.codigo, ''), e.nombre) END";

        if ($tipo === 'calidad') {
            $stmt = $pdo->query(
                "SELECT i.nombre AS `Sede`, {$nombreEspacio} AS `Espacio`, COUNT(*) AS `Bienes`,
                        SUM(b.foto_path IS NULL OR b.foto_path = '') AS `Sin foto`,
                        SUM(b.categoria_id IS NULL) AS `Sin categoría`,
                        SUM(ua.bien_id IS NULL) AS `Sin asignar`,
                        SUM(b.qr_confirmado_en IS NULL) AS `Sin QR pegado`
                   FROM bienes b JOIN instituciones i ON i.id = b.institucion_id {$ubicacion}
                  WHERE " . self::EN_INVENTARIO . ' AND ' . $en('b.institucion_id') . "
                  GROUP BY i.nombre, (ua.bien_id IS NULL), e.id, e.codigo, e.nombre
                  ORDER BY i.nombre, (e.id IS NULL), (ua.bien_id IS NULL), e.nombre"
            );
            $libro->agregarHoja('Resumen por espacio', self::sinFalla($stmt), ['Bienes', 'Sin foto', 'Sin categoría', 'Sin asignar', 'Sin QR pegado']);

            $listas = [
                'Sin foto' => "(b.foto_path IS NULL OR b.foto_path = '')",
                'Sin categoría' => 'b.categoria_id IS NULL',
                'Sin asignar' => 'ua.bien_id IS NULL',
                'Sin QR pegado' => 'b.qr_confirmado_en IS NULL',
            ];
            foreach ($listas as $titulo => $condicion) {
                $stmt = $pdo->query(
                    "SELECT b.codigo_identificacion AS `Código`, b.descripcion AS `Descripción`, c.nombre AS `Categoría`,
                            {$nombreEspacio} AS `Espacio`, i.nombre AS `Sede`,
                            CASE b.estado WHEN 'activo' THEN 'Activo' ELSE 'En reparación' END AS `Estado`
                       FROM bienes b JOIN instituciones i ON i.id = b.institucion_id
                       LEFT JOIN categorias_bienes c ON c.id = b.categoria_id {$ubicacion}
                      WHERE " . self::EN_INVENTARIO . " AND {$condicion} AND " . $en('b.institucion_id') . '
                      ORDER BY i.nombre, b.codigo_identificacion'
                );
                $libro->agregarHoja($titulo, self::sinFalla($stmt));
            }
        }

        if ($tipo === 'valor') {
            $stmt = $pdo->query(
                "SELECT i.nombre AS sede, {$nombreEspacio} AS espacio, COUNT(*) AS bienes, SUM(b.valor) AS valor
                   FROM bienes b JOIN instituciones i ON i.id = b.institucion_id {$ubicacion}
                  WHERE " . self::EN_INVENTARIO . ' AND ' . $en('b.institucion_id') . '
                  GROUP BY i.nombre, (ua.bien_id IS NULL), e.id, e.codigo, e.nombre ORDER BY i.nombre, (e.id IS NULL), (ua.bien_id IS NULL), e.nombre'
            );
            $libro->agregarHojaDesdeFilas('Por espacio', ['Sede', 'Espacio', 'Bienes', 'Valor total'],
                self::conTotal(self::sinFalla($stmt)->fetchAll(PDO::FETCH_NUM), 2), ['Bienes', 'Valor total']);

            $stmt = $pdo->query(
                "SELECT COALESCE(c.nombre, 'Sin categoría') AS categoria, COUNT(*) AS bienes, SUM(b.valor) AS valor
                   FROM bienes b LEFT JOIN categorias_bienes c ON c.id = b.categoria_id
                  WHERE " . self::EN_INVENTARIO . ' AND ' . $en('b.institucion_id') . "
                  GROUP BY COALESCE(c.nombre, 'Sin categoría') ORDER BY valor DESC"
            );
            $libro->agregarHojaDesdeFilas('Por categoría', ['Categoría', 'Bienes', 'Valor total'],
                self::conTotal(self::sinFalla($stmt)->fetchAll(PDO::FETCH_NUM), 1), ['Bienes', 'Valor total']);

            $stmt = $pdo->query(
                "SELECT i.nombre AS `Sede`, {$nombreEspacio} AS `Espacio`, COALESCE(c.nombre, 'Sin categoría') AS `Categoría`,
                        COUNT(*) AS `Bienes`, SUM(b.valor) AS `Valor total`
                   FROM bienes b JOIN instituciones i ON i.id = b.institucion_id
                   LEFT JOIN categorias_bienes c ON c.id = b.categoria_id {$ubicacion}
                  WHERE " . self::EN_INVENTARIO . ' AND ' . $en('b.institucion_id') . "
                  GROUP BY i.nombre, (ua.bien_id IS NULL), e.id, e.codigo, e.nombre, COALESCE(c.nombre, 'Sin categoría')
                  ORDER BY i.nombre, (e.id IS NULL), (ua.bien_id IS NULL), e.nombre, `Categoría`"
            );
            $libro->agregarHoja('Espacio × categoría', self::sinFalla($stmt), ['Bienes', 'Valor total']);
        }

        if ($tipo === 'inactivos') {
            $stmt = $pdo->prepare(
                "SELECT TRIM(CONCAT_WS(' ', u.nombres, u.apellidos)) AS `Funcionario`, u.documento AS `Documento`,
                        u.email AS `Correo`, r.nombre AS `Rol`, ca.nombre AS `Cargo`, i.nombre AS `Sede`,
                        COALESCE(DATE_FORMAT(u.ultimo_login, '%Y-%m-%d %H:%i'), 'Nunca ha ingresado') AS `Último ingreso`,
                        DATEDIFF(NOW(), u.ultimo_login) AS `Días sin ingresar`
                   FROM usuarios u
                   JOIN roles r ON r.id = u.rol_id
                   JOIN instituciones i ON i.id = u.institucion_id
                   LEFT JOIN cargos ca ON ca.id = u.cargo_id
                  WHERE u.activo = 1 AND u.eliminado_en IS NULL AND r.nombre <> 'superusuario'
                    AND (u.ultimo_login IS NULL OR u.ultimo_login < NOW() - INTERVAL ? DAY)
                    AND " . $en('u.institucion_id') . '
                  ORDER BY (u.ultimo_login IS NULL) DESC, u.ultimo_login, u.apellidos'
            );
            $stmt->execute([self::DIAS_INACTIVO]);
            $libro->agregarHoja('Funcionarios inactivos', $stmt, ['Días sin ingresar']);
        }

        $ruta = $trabajo . '.xlsx';
        $libro->guardar($ruta);
        @rmdir($trabajo);

        return ['ruta' => $ruta, 'nombre' => 'MIA_' . $tipo . '_' . date('Y-m-d') . '.xlsx'];
    }

    private static function sinFalla(\PDOStatement|false $stmt): \PDOStatement
    {
        if ($stmt === false) {
            throw new \RuntimeException('No se pudo leer la información.');
        }

        return $stmt;
    }

    /**
     * Agrega la fila TOTAL sumando las columnas numéricas desde $primeraNumerica.
     *
     * @param array<int, array<int, mixed>> $filas
     * @return array<int, array<int, mixed>>
     */
    private static function conTotal(array $filas, int $primeraNumerica): array
    {
        if ($filas === []) {
            return [];
        }
        $total = array_fill(0, count(reset($filas)), '');
        $total[0] = 'TOTAL';
        foreach ($filas as $fila) {
            foreach (array_values($fila) as $i => $valor) {
                if ($i >= $primeraNumerica) {
                    $total[$i] = (float) ($total[$i] === '' ? 0 : $total[$i]) + (float) $valor;
                }
            }
        }
        $filas[] = $total;

        return $filas;
    }

    private static function nombreRegistro(array $datos, int $id): string
    {
        $nombre = trim((string) ($datos['nombre'] ?? trim(($datos['nombres'] ?? '') . ' ' . ($datos['apellidos'] ?? ''))));
        $codigo = (string) ($datos['codigo'] ?? $datos['codigo_identificacion'] ?? $datos['documento'] ?? '');

        return trim(($codigo !== '' ? $codigo . ' · ' : '') . $nombre) ?: "#{$id}";
    }

    /** Catálogos para mostrar nombres en vez de ids en "Valor anterior / nuevo". */
    private static function mapas(): array
    {
        $usuarios = Usuario::mapaIdNombreCompleto();

        return [
            'categoria_id' => Categoria::mapaIdNombre(),
            'cargo_id' => Cargo::mapaIdNombre(),
            'rol_id' => Usuario::mapaRoles(),
            'institucion_id' => Institucion::mapaIdNombre(),
            'institucion_padre_id' => Institucion::mapaIdNombre(),
            'registrado_por' => $usuarios,
            'created_by' => $usuarios,
            'responsables' => $usuarios,
            'usuario_responsable_id' => $usuarios,
            'espacio_id' => self::mapaEspacios(),
        ];
    }

    /** @return array<int, string> */
    private static function mapaEspacios(): array
    {
        $mapa = [];
        foreach (Database::connection()->query('SELECT id, codigo, nombre FROM espacios')->fetchAll() as $e) {
            $mapa[(int) $e['id']] = trim($e['codigo'] . ' - ' . $e['nombre'], ' -');
        }

        return $mapa;
    }
}
