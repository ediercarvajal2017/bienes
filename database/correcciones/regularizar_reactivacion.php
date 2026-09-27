<?php

declare(strict_types=1);

/**
 * Corrección R12 del diagnóstico: un bien que se reintegró y después volvió a quedar activo
 * (la asignación masiva lo reactivaba en silencio) SIN un movimiento de reactivación. Si la
 * institución confirma que el bien sigue en uso, se REGULARIZA: se registra el movimiento de
 * reactivación que faltó (con su motivo) y el bien queda como está. No cambia su estado ni
 * su asignación.
 *
 *   php database/correcciones/regularizar_reactivacion.php --bien=ID --responsable=ID_USUARIO            (SIMULACIÓN)
 *   php database/correcciones/regularizar_reactivacion.php --bien=ID --responsable=ID_USUARIO --aplicar
 *
 * Queda en la auditoría con la acción "correccion_datos".
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Models\Auditoria;

Env::cargar();
$opciones = getopt('', ['bien:', 'responsable:', 'aplicar']) ?: [];
$bienId = (int) ($opciones['bien'] ?? 0);
$responsableId = (int) ($opciones['responsable'] ?? 0);
$aplicar = array_key_exists('aplicar', $opciones);
$pdo = Database::connection();

$st = $pdo->prepare(
    "SELECT b.id, b.codigo_identificacion, b.descripcion, b.estado, b.institucion_id,
            (SELECT m.id FROM movimientos m WHERE m.bien_id = b.id ORDER BY m.id DESC LIMIT 1) AS ultimo_mov,
            (SELECT m.tipo FROM movimientos m WHERE m.bien_id = b.id ORDER BY m.id DESC LIMIT 1) AS ultimo_tipo,
            (SELECT a.espacio_id FROM asignaciones a WHERE a.bien_id = b.id AND a.activa = 1 LIMIT 1) AS espacio_id,
            (SELECT a.fecha_asignacion FROM asignaciones a WHERE a.bien_id = b.id AND a.activa = 1 LIMIT 1) AS fecha_asignacion
       FROM bienes b WHERE b.id = ?"
);
$st->execute([$bienId]);
$bien = $st->fetch();
if (!$bien || $bien['estado'] !== 'activo' || $bien['ultimo_tipo'] !== 'reintegro') {
    fwrite(STDERR, "El bien {$bienId} no está en el caso R12 (debe estar activo y con un reintegro como último movimiento).\n");
    exit(1);
}
$st = $pdo->prepare('SELECT id FROM usuarios WHERE id = ? AND activo = 1 AND eliminado_en IS NULL');
$st->execute([$responsableId]);
if (!$st->fetchColumn()) {
    fwrite(STDERR, "El usuario responsable {$responsableId} no existe o está inactivo.\n");
    exit(1);
}

$fecha = (string) ($bien['fecha_asignacion'] ?: date('Y-m-d'));
echo "Bien {$bien['id']} ({$bien['codigo_identificacion']}, {$bien['descripcion']}): se registra la reactivación del {$fecha}"
    . ($aplicar ? "\n" : "  (SIMULACIÓN; use --aplicar)\n");
if (!$aplicar) {
    exit(0);
}

Database::transaccion(function (PDO $pdo) use ($bien, $responsableId, $fecha): void {
    $pdo->prepare(
        "INSERT INTO movimientos (bien_id, tipo, fecha, responsable_id, espacio_destino_id, observaciones)
         VALUES (?, 'reactivacion', ?, ?, ?, ?)"
    )->execute([
        (int) $bien['id'], $fecha, $responsableId, $bien['espacio_id'] !== null ? (int) $bien['espacio_id'] : null,
        'Regularización: la asignación masiva lo reactivó sin registro; la institución confirmó que sigue en uso',
    ]);
    $movimientoId = (int) $pdo->lastInsertId();
    Auditoria::registrar($responsableId, (int) $bien['institucion_id'], 'correccion_datos', 'bien', (int) $bien['id'],
        ['ultimo_movimiento' => 'reintegro'],
        ['movimiento_reactivacion_id' => $movimientoId, 'motivo' => 'Corrección R12: reactivación sin registro, regularizada']);
});
echo "Listo.\n";
