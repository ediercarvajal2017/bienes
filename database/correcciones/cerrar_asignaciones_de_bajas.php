<?php

declare(strict_types=1);

/**
 * Corrección R06 del diagnóstico: bienes DADOS DE BAJA que siguen con una asignación activa
 * (antes, aprobar la baja no cerraba la asignación). Cierra esas asignaciones; no cambia el
 * estado del bien ni borra nada.
 *
 *   php database/correcciones/cerrar_asignaciones_de_bajas.php            (SIMULACIÓN: solo muestra)
 *   php database/correcciones/cerrar_asignaciones_de_bajas.php --aplicar  (aplica, en una transacción)
 *
 * Cada asignación cerrada queda en la auditoría con la acción "correccion_datos".
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
$aplicar = in_array('--aplicar', $argv, true);
$pdo = Database::connection();

$filas = $pdo->query(
    "SELECT a.id AS asignacion_id, a.bien_id, a.espacio_id, a.fecha_asignacion, b.codigo_identificacion,
            b.descripcion, b.institucion_id, e.nombre AS espacio
       FROM asignaciones a
       JOIN bienes b ON b.id = a.bien_id
       LEFT JOIN espacios e ON e.id = a.espacio_id
      WHERE a.activa = 1 AND b.estado = 'dado_de_baja'
      ORDER BY a.id"
)->fetchAll();

echo count($filas) . ' asignación(es) activa(s) de bienes dados de baja' . ($aplicar ? ":\n" : " (SIMULACIÓN; use --aplicar para corregir):\n");
foreach ($filas as $f) {
    echo "  asignación {$f['asignacion_id']} · bien {$f['bien_id']} ({$f['codigo_identificacion']}, {$f['descripcion']}) · espacio: {$f['espacio']}\n";
}
if (!$aplicar || $filas === []) {
    exit(0);
}

Database::transaccion(function (PDO $pdo) use ($filas): void {
    $cerrar = $pdo->prepare('UPDATE asignaciones SET activa = 0 WHERE id = ? AND activa = 1');
    foreach ($filas as $f) {
        $cerrar->execute([(int) $f['asignacion_id']]);
        Auditoria::registrar(null, (int) $f['institucion_id'], 'correccion_datos', 'bien', (int) $f['bien_id'],
            ['asignacion_id' => (int) $f['asignacion_id'], 'activa' => 1, 'espacio' => $f['espacio']],
            ['activa' => 0, 'motivo' => 'Corrección R06: el bien está dado de baja y su asignación seguía activa']);
    }
});
echo "Listo: " . count($filas) . " asignación(es) cerrada(s).\n";
