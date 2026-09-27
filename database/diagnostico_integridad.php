<?php

declare(strict_types=1);

/**
 * Diagnóstico de integridad de los datos — SOLO LECTURA.
 *
 * Busca inconsistencias que pudieron dejar en la base de datos los defectos encontrados
 * en la revisión de seguridad/ciclo de vida (plan de preparación para la presentación):
 * secciones asignadas por un rector, bienes dados de baja que siguen asignados, bajas
 * aprobadas dos veces, asignaciones a espacios de otra institución, etc.
 *
 * No corrige nada: todo corre dentro de una transacción READ ONLY (si alguna consulta
 * intentara escribir, MySQL/MariaDB la rechazaría). El informe se revisa con el
 * responsable del sistema y cada corrección se hace después con su propio script
 * (database/correcciones/*.php, con --simular por defecto).
 *
 * Uso: php database/diagnostico_integridad.php [--csv=/ruta/informe.csv] [--muestra=20]
 * Por defecto el CSV queda en storage/diagnosticos/diagnostico-AAAAMMDD-HHMMSS.csv
 * Código de salida: 0 si no hay hallazgos de severidad ALTA, 2 si los hay.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::cargar();

$config = require __DIR__ . '/../config/app.php';
date_default_timezone_set($config['timezone']);
$opciones = getopt('', ['csv:', 'muestra:']) ?: [];
$muestra = max(1, (int) ($opciones['muestra'] ?? 10));

$rutaCsv = is_string($opciones['csv'] ?? null)
    ? $opciones['csv']
    : $config['storage_path'] . '/diagnosticos/diagnostico-' . date('Ymd-His') . '.csv';
if (!is_dir(dirname($rutaCsv))) {
    mkdir(dirname($rutaCsv), 0775, true);
}

$pdo = Database::connection();
$pdo->exec('START TRANSACTION READ ONLY');

$idRolSuper = (int) $pdo->query("SELECT id FROM roles WHERE nombre = 'superusuario'")->fetchColumn();

// Desde la migración 031 las bajas tienen "estado" (pendiente/aprobada/rechazada); antes
// solo "aprobada" (0/1). El diagnóstico debe funcionar con ambas versiones del esquema.
$bajasConEstado = (bool) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bajas_bienes' AND COLUMN_NAME = 'estado'"
)->fetchColumn();
$bajaPendiente = $bajasConEstado ? "bb.estado = 'pendiente'" : 'bb.aprobada = 0';

/**
 * Cada revisión: [código, severidad, título, qué significa / qué hacer, SQL].
 * Severidad: ALTA = dato incorrecto o posible acceso indebido; MEDIA = inconsistencia
 * de proceso; INFO = para conocimiento, no necesariamente un error.
 */
$revisiones = [
    [
        'R01', 'ALTA', 'Secciones cuya institución padre la cambió un usuario que NO es superusuario',
        'Posible uso del escalamiento de privilegios (un rector se unió a otra institución). Revisar cada caso con el superusuario antes de corregir.',
        "SELECT i.id AS institucion_id, i.nombre AS institucion, i.institucion_padre_id, p.nombre AS padre,
                a.id AS auditoria_id, a.created_at AS fecha, u.email AS modificado_por, r.nombre AS rol
         FROM auditoria a
         JOIN instituciones i ON i.id = a.entidad_id
         LEFT JOIN instituciones p ON p.id = i.institucion_padre_id
         JOIN usuarios u ON u.id = a.usuario_id
         JOIN roles r ON r.id = u.rol_id
         WHERE a.entidad = 'institucion' AND a.accion = 'editar' AND u.rol_id <> {$idRolSuper}
           AND (COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.datos_despues, '$.tipo_sede')), '') <> COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.datos_antes, '$.tipo_sede')), '')
             OR COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.datos_despues, '$.institucion_padre_id')), 'null') <> COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.datos_antes, '$.institucion_padre_id')), 'null')
             OR COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.datos_despues, '$.codigo_dane')), '') <> COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.datos_antes, '$.codigo_dane')), ''))
         ORDER BY a.created_at",
    ],
    [
        'R02', 'INFO', 'Todas las secciones y su institución principal (para revisión visual)',
        'Confirmar con el responsable que cada sección pertenece de verdad a esa institución principal.',
        "SELECT s.id, s.nombre AS seccion, s.codigo_dane, p.id AS padre_id, p.nombre AS principal, p.activo AS principal_activa
         FROM instituciones s LEFT JOIN instituciones p ON p.id = s.institucion_padre_id
         WHERE s.tipo_sede = 'seccion' ORDER BY p.nombre, s.nombre",
    ],
    [
        'R03', 'ALTA', 'Secciones con institución padre inválida (inexistente, ella misma, o que a su vez es sección)',
        'Estructura de sedes rota: familiaDe() puede dar resultados inesperados.',
        "SELECT s.id, s.nombre, s.institucion_padre_id, p.tipo_sede AS tipo_padre
         FROM instituciones s LEFT JOIN instituciones p ON p.id = s.institucion_padre_id
         WHERE s.tipo_sede = 'seccion'
           AND (s.institucion_padre_id IS NULL OR p.id IS NULL OR p.id = s.id OR p.tipo_sede = 'seccion')",
    ],
    [
        'R04', 'ALTA', 'Cuentas de superusuario modificadas por un usuario que NO es superusuario',
        'Posible uso del defecto que permitía a un rector editar/desactivar al superusuario. Verificar contraseña y estado de esas cuentas.',
        "SELECT a.id AS auditoria_id, a.created_at AS fecha, a.accion, su.email AS cuenta_superusuario, u.email AS modificado_por, r.nombre AS rol
         FROM auditoria a
         JOIN usuarios su ON su.id = a.entidad_id AND su.rol_id = {$idRolSuper}
         JOIN usuarios u ON u.id = a.usuario_id
         JOIN roles r ON r.id = u.rol_id
         WHERE a.entidad = 'usuario' AND u.rol_id <> {$idRolSuper}
         ORDER BY a.created_at",
    ],
    [
        'R05', 'MEDIA', 'Superusuarios que comparten institución con rectores/secretarios',
        'Recomendado: mover al superusuario a una institución técnica propia (solo con aprobación).',
        "SELECT su.id, su.email AS superusuario, i.nombre AS institucion,
                (SELECT COUNT(*) FROM usuarios o WHERE o.institucion_id = su.institucion_id AND o.rol_id <> {$idRolSuper} AND o.eliminado_en IS NULL) AS otros_usuarios
         FROM usuarios su JOIN instituciones i ON i.id = su.institucion_id
         WHERE su.rol_id = {$idRolSuper} AND su.eliminado_en IS NULL
         HAVING otros_usuarios > 0",
    ],
    [
        'R06', 'ALTA', 'Bienes DADOS DE BAJA que siguen con una asignación activa',
        'La aprobación de la baja no cerraba la asignación: el bien aparece en un espacio y con responsable aunque ya no existe.',
        "SELECT b.id AS bien_id, b.codigo_identificacion, b.descripcion, i.nombre AS institucion, a.id AS asignacion_id, e.nombre AS espacio, a.fecha_asignacion
         FROM bienes b
         JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
         LEFT JOIN espacios e ON e.id = a.espacio_id
         JOIN instituciones i ON i.id = b.institucion_id
         WHERE b.estado = 'dado_de_baja'",
    ],
    [
        'R07', 'MEDIA', 'Bienes con más de un reporte de baja aprobado',
        'Se podía aprobar la misma baja dos veces o varios reportes del mismo bien.',
        "SELECT bb.bien_id, b.codigo_identificacion, COUNT(*) AS bajas_aprobadas, GROUP_CONCAT(bb.id) AS ids_bajas
         FROM bajas_bienes bb JOIN bienes b ON b.id = bb.bien_id
         WHERE bb.aprobada = 1 GROUP BY bb.bien_id, b.codigo_identificacion HAVING COUNT(*) > 1",
    ],
    [
        'R08', 'MEDIA', 'Bienes con varios reportes de baja pendientes, o pendientes sobre un bien ya dado de baja',
        'Reportes duplicados o que ya no tienen sentido; conviene resolverlos desde /bajas.',
        "SELECT bb.bien_id, b.codigo_identificacion, b.estado, COUNT(*) AS pendientes, GROUP_CONCAT(bb.id) AS ids_bajas
         FROM bajas_bienes bb JOIN bienes b ON b.id = bb.bien_id
         WHERE {$bajaPendiente}
         GROUP BY bb.bien_id, b.codigo_identificacion, b.estado
         HAVING COUNT(*) > 1 OR b.estado = 'dado_de_baja'",
    ],
    [
        'R09', 'ALTA', 'Asignaciones ACTIVAS a un espacio de OTRA institución',
        'El formulario aceptaba cualquier espacio_id. El bien figura en un espacio que no es de su institución.',
        "SELECT a.id AS asignacion_id, b.id AS bien_id, b.codigo_identificacion, ib.nombre AS institucion_bien,
                e.id AS espacio_id, e.nombre AS espacio, ie.nombre AS institucion_espacio, a.fecha_asignacion
         FROM asignaciones a
         JOIN bienes b ON b.id = a.bien_id
         JOIN espacios e ON e.id = a.espacio_id
         JOIN instituciones ib ON ib.id = b.institucion_id
         JOIN instituciones ie ON ie.id = e.institucion_id
         WHERE a.activa = 1 AND e.institucion_id <> b.institucion_id",
    ],
    [
        'R10', 'MEDIA', 'Asignaciones activas a espacios desactivados o en la papelera',
        'El bien figura en un espacio que ya no está en uso.',
        "SELECT a.id AS asignacion_id, b.codigo_identificacion, e.nombre AS espacio, e.activo, e.eliminado_en
         FROM asignaciones a JOIN bienes b ON b.id = a.bien_id JOIN espacios e ON e.id = a.espacio_id
         WHERE a.activa = 1 AND (e.activo = 0 OR e.eliminado_en IS NOT NULL)",
    ],
    [
        'R11', 'ALTA', 'Bienes con MÁS DE UNA asignación activa',
        'Cerrar y crear asignación no iba en una transacción; un bien debe tener como máximo una activa.',
        "SELECT a.bien_id, b.codigo_identificacion, COUNT(*) AS activas, GROUP_CONCAT(a.id ORDER BY a.id) AS ids_asignaciones
         FROM asignaciones a JOIN bienes b ON b.id = a.bien_id
         WHERE a.activa = 1 GROUP BY a.bien_id, b.codigo_identificacion HAVING COUNT(*) > 1",
    ],
    [
        'R12', 'ALTA', 'Bienes reintegrados que volvieron a estar activos SIN una reactivación registrada',
        'La asignación masiva los reactivaba en silencio, sin motivo ni autorización del rector.',
        "SELECT b.id AS bien_id, b.codigo_identificacion, b.estado, m.id AS ultimo_movimiento_id, m.fecha AS fecha_reintegro
         FROM bienes b
         JOIN movimientos m ON m.id = (SELECT m2.id FROM movimientos m2 WHERE m2.bien_id = b.id ORDER BY m2.fecha DESC, m2.id DESC LIMIT 1)
         WHERE m.tipo = 'reintegro' AND b.estado IN ('activo', 'en_reparacion')",
    ],
    [
        'R13', 'INFO', 'Movimientos hacia un espacio de otra institución (historial)',
        'Puede venir del defecto de espacio_id sin validar, pero también es normal si el bien se trasladó de sede después (sus movimientos viejos apuntan a la institución anterior). Revisar caso a caso.',
        "SELECT m.id AS movimiento_id, m.tipo, m.fecha, b.codigo_identificacion, e.nombre AS espacio_destino,
                ib.nombre AS institucion_bien, ie.nombre AS institucion_espacio
         FROM movimientos m
         JOIN bienes b ON b.id = m.bien_id
         JOIN espacios e ON e.id = m.espacio_destino_id
         JOIN instituciones ib ON ib.id = b.institucion_id
         JOIN instituciones ie ON ie.id = e.institucion_id
         WHERE e.institucion_id <> b.institucion_id",
    ],
    [
        'R14', 'MEDIA', 'Lotes de reintegro sin ningún movimiento',
        'Lotes vacíos (por una carrera entre dos peticiones o un error a mitad de camino).',
        "SELECT l.id AS lote_id, l.fecha, i.nombre AS institucion, l.created_at
         FROM lotes_reintegro l JOIN instituciones i ON i.id = l.institucion_id
         WHERE NOT EXISTS (SELECT 1 FROM movimientos m WHERE m.lote_reintegro_id = l.id)",
    ],
    [
        'R15', 'ALTA', 'Instituciones con más de una jornada de verificación EN PROGRESO',
        'Debe haber como máximo una. Hay que cerrar las sobrantes ANTES de crear el índice único de la migración 031.',
        "SELECT j.institucion_id, i.nombre AS institucion, COUNT(*) AS jornadas_abiertas, GROUP_CONCAT(j.id ORDER BY j.id) AS ids
         FROM jornadas_verificacion j JOIN instituciones i ON i.id = j.institucion_id
         WHERE j.estado = 'en_progreso' GROUP BY j.institucion_id, i.nombre HAVING COUNT(*) > 1",
    ],
    [
        'R16', 'MEDIA', 'Hallazgos todavía pendientes en jornadas ya cerradas',
        'Quedaron sin registrar ni descartar.',
        "SELECT h.id AS hallazgo_id, h.descripcion, j.id AS jornada_id, j.nombre AS jornada, j.fecha_cierre
         FROM hallazgos_verificacion h JOIN jornadas_verificacion j ON j.id = h.jornada_id
         WHERE h.estado = 'pendiente' AND j.estado = 'cerrada'",
    ],
    [
        'R17', 'MEDIA', 'Bienes cuya categoría pertenece a otra institución',
        'Las categorías son por institución; el bien quedó con una categoría ajena.',
        "SELECT b.id AS bien_id, b.codigo_identificacion, ib.nombre AS institucion_bien, c.nombre AS categoria, ic.nombre AS institucion_categoria
         FROM bienes b
         JOIN categorias_bienes c ON c.id = b.categoria_id
         JOIN instituciones ib ON ib.id = b.institucion_id
         JOIN instituciones ic ON ic.id = c.institucion_id
         WHERE c.institucion_id <> b.institucion_id",
    ],
    [
        'R18', 'MEDIA', 'Responsables de espacio que pertenecen a otra institución',
        'El responsable asignado a un espacio no es de la institución del espacio.',
        "SELECT er.espacio_id, e.nombre AS espacio, ie.nombre AS institucion_espacio, u.email AS responsable, iu.nombre AS institucion_usuario
         FROM espacio_responsables er
         JOIN espacios e ON e.id = er.espacio_id
         JOIN usuarios u ON u.id = er.usuario_id
         JOIN instituciones ie ON ie.id = e.institucion_id
         JOIN instituciones iu ON iu.id = u.institucion_id
         WHERE u.institucion_id <> e.institucion_id",
    ],
    [
        'R19', 'INFO', 'Cargas masivas analizadas pero nunca confirmadas (más de 7 días)',
        'No es un error; puede indicar cargas abandonadas o aplicadas a medias por un error. Revisar las más recientes.',
        "SELECT c.id, c.tipo, i.nombre AS institucion, c.total_filas, c.nuevos, c.modificados, c.created_at
         FROM cargas_masivas c JOIN instituciones i ON i.id = c.institucion_id
         WHERE c.aplicada = 0 AND c.created_at < NOW() - INTERVAL 7 DAY ORDER BY c.created_at DESC",
    ],
    [
        'R20', 'INFO', 'Usuarios activos en instituciones desactivadas',
        'No pueden iniciar sesión (salvo superusuario), pero si ya tenían la sesión abierta seguían dentro.',
        "SELECT u.id, u.email, r.nombre AS rol, i.nombre AS institucion
         FROM usuarios u JOIN instituciones i ON i.id = u.institucion_id JOIN roles r ON r.id = u.rol_id
         WHERE u.activo = 1 AND u.eliminado_en IS NULL AND i.activo = 0 AND u.rol_id <> {$idRolSuper}",
    ],
    [
        'R21', 'INFO', 'Bienes con foto e índice de búsqueda por foto calculado',
        'Hasta esta corrección, cambiar la foto no borraba el índice: algunos pueden corresponder a la foto anterior. Recalcularlos es seguro (dato derivado).',
        "SELECT COUNT(*) AS bienes_con_indice,
                SUM(b.updated_at > COALESCE((SELECT MAX(a.created_at) FROM auditoria a WHERE a.entidad = 'bien' AND a.entidad_id = b.id AND a.accion = 'crear'), b.created_at)) AS modificados_despues_de_crear
         FROM bienes b WHERE b.foto_path IS NOT NULL AND b.foto_vector IS NOT NULL",
    ],
];

$csv = fopen($rutaCsv, 'w');
fwrite($csv, "\xEF\xBB\xBF"); // BOM para que Excel abra bien las tildes
fputcsv($csv, ['codigo', 'severidad', 'revision', 'fila'], ';', '"', '');

$resumen = [];
$hayAltas = false;

echo "Diagnóstico de integridad — " . date('Y-m-d H:i:s') . " — base: " . Env::get('DB_DATABASE', 'sigebi') . "\n";
echo str_repeat('=', 100) . "\n";

foreach ($revisiones as [$codigo, $severidad, $titulo, $explicacion, $sql]) {
    try {
        $filas = $pdo->query($sql)->fetchAll();
    } catch (PDOException $e) {
        echo "[{$codigo}] {$titulo}\n    NO SE PUDO EJECUTAR: {$e->getMessage()}\n\n";
        $resumen[] = [$codigo, $severidad, $titulo, 'error'];
        continue;
    }

    // R21 es un conteo (una sola fila con números): se informa tal cual.
    $cantidad = $codigo === 'R21' ? (int) ($filas[0]['bienes_con_indice'] ?? 0) : count($filas);
    $resumen[] = [$codigo, $severidad, $titulo, $cantidad];

    if ($cantidad > 0 && $severidad === 'ALTA') {
        $hayAltas = true;
    }

    $marca = $cantidad === 0 ? 'OK ' : ($severidad === 'ALTA' ? '!!!' : ($severidad === 'MEDIA' ? ' ! ' : ' i '));
    echo "[{$marca}] {$codigo} ({$severidad}) {$titulo}: {$cantidad}\n";

    if ($cantidad > 0) {
        echo "      {$explicacion}\n";
        foreach (array_slice($filas, 0, $muestra) as $fila) {
            echo '      - ' . implode(' | ', array_map(
                static fn ($k, $v) => "{$k}=" . mb_strimwidth((string) $v, 0, 40, '…'),
                array_keys($fila),
                $fila
            )) . "\n";
        }
        if (count($filas) > $muestra) {
            echo '      … y ' . (count($filas) - $muestra) . " más (ver CSV)\n";
        }
        echo "\n";
    }

    foreach ($filas as $fila) {
        fputcsv($csv, [$codigo, $severidad, $titulo, json_encode($fila, JSON_UNESCAPED_UNICODE)], ';', '"', '');
    }
}

fclose($csv);
$pdo->exec('ROLLBACK');

echo str_repeat('=', 100) . "\n";
echo "Informe completo: {$rutaCsv}\n";
echo $hayAltas
    ? "HAY hallazgos de severidad ALTA. No corregir nada sin revisarlos antes con el responsable del sistema.\n"
    : "Sin hallazgos de severidad ALTA.\n";

exit($hayAltas ? 2 : 0);
