<?php

declare(strict_types=1);

/**
 * Corrección del "ítem de más" del reintegro por cantidad (v1.2.31). El renglón de la cartera
 * de una placa con unidades (bien sin lote con el código de sus unidades, p. ej. 13019802
 * "8 SILLA…" con 13019802-001…-008) se quedaba Activo cuando ya no quedaba ninguna unidad en
 * cartera, y se contaba como un bien más (8 sillas salían 9).
 *
 *   php database/correcciones/renglon_placa_en_cero.php            (SIMULACIÓN: solo muestra)
 *   php database/correcciones/renglon_placa_en_cero.php --aplicar  (después del respaldo)
 *
 * Con --aplicar, cada renglón Activo (o En reparación) cuyas unidades ya se reintegraron todas
 * pasa a Reintegrado, con la cantidad en 0 y valor $0, y se cierra su asignación: lo mismo que
 * hace ahora el reintegro por cantidad al llegar a 0. No crea movimientos ni toca lotes. Queda
 * en la auditoría con la acción "correccion_datos".
 *
 * Solo informa (no cambia): los renglones cuyas unidades salieron solo por baja (revisar a
 * mano), los lotes de reintegro donde el renglón quedó junto a sus unidades (el comprobante ya
 * no lo suma) y los renglones Activos cuya cantidad no coincide con las unidades en cartera.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Models\Asignacion;
use App\Models\Auditoria;
use App\Models\Bien;
use App\Services\ReintegroUnidadesService;

Env::cargar();
$aplicar = array_key_exists('aplicar', getopt('', ['aplicar']) ?: []);
$pdo = Database::connection();

// Renglones de la cartera (bien sin lote con unidades de su código) que siguen en circulación.
$renglones = $pdo->query(
    "SELECT r.id, r.institucion_id, i.nombre AS institucion, r.codigo_identificacion AS placa, r.descripcion, r.valor, r.estado,
            COUNT(u.id) AS unidades,
            SUM(u.estado IN ('activo', 'en_reparacion')) AS en_cartera,
            SUM(u.estado = 'reintegrado') AS reintegradas,
            SUM(u.estado = 'dado_de_baja') AS de_baja
       FROM bienes r
       JOIN instituciones i ON i.id = r.institucion_id
       JOIN bienes u ON u.institucion_id = r.institucion_id AND u.lote = r.codigo_identificacion
      WHERE r.lote IS NULL AND r.estado IN ('activo', 'en_reparacion')
      GROUP BY r.id
      ORDER BY i.nombre, r.codigo_identificacion"
)->fetchAll();

$aCorregir = [];
echo "== Renglones de la cartera que siguen en circulación: " . count($renglones) . "\n";
foreach ($renglones as $r) {
    $enCartera = (int) $r['en_cartera'];
    $linea = "  #{$r['id']} {$r['institucion']} · placa {$r['placa']} \"{$r['descripcion']}\" ({$r['estado']}): "
        . "{$enCartera} en cartera de {$r['unidades']} unidades ({$r['reintegradas']} reintegradas, {$r['de_baja']} de baja)";
    if ($enCartera === 0 && (int) $r['reintegradas'] > 0) {
        $aCorregir[] = $r;
        echo $linea . "  → pasa a Reintegrado\n";
    } elseif ($enCartera === 0) {
        echo $linea . "  → solo bajas: revisar a mano (no se cambia)\n";
    } elseif (preg_match('/^(\d{1,5})\s/u', trim((string) $r['descripcion']), $m) === 1 && (int) $m[1] !== $enCartera) {
        echo $linea . "  → la descripción dice {$m[1]} (informativo, no se cambia)\n";
    }
}

// Lotes de reintegro donde el renglón quedó junto a sus unidades (informativo).
$lotes = $pdo->query(
    "SELECT DISTINCT m.lote_reintegro_id AS lote, i.nombre AS institucion, r.codigo_identificacion AS placa, l.estado
       FROM movimientos m
       JOIN bienes r ON r.id = m.bien_id AND r.lote IS NULL
       JOIN lotes_reintegro l ON l.id = m.lote_reintegro_id
       JOIN instituciones i ON i.id = l.institucion_id
      WHERE m.tipo = 'reintegro'
        AND EXISTS (SELECT 1 FROM movimientos mu JOIN bienes u ON u.id = mu.bien_id
                     WHERE mu.lote_reintegro_id = m.lote_reintegro_id AND u.institucion_id = r.institucion_id AND u.lote = r.codigo_identificacion)
      ORDER BY m.lote_reintegro_id"
)->fetchAll();
echo "\n== Lotes de reintegro con el renglón junto a sus unidades (el comprobante ya no lo suma): " . count($lotes) . "\n";
foreach ($lotes as $l) {
    echo "  Lote #{$l['lote']} ({$l['estado']}) · {$l['institucion']} · placa {$l['placa']}\n";
}

echo "\nA corregir: " . count($aCorregir) . ($aplicar ? "\n" : "  (SIMULACIÓN; use --aplicar después del respaldo)\n");
if (!$aplicar || $aCorregir === []) {
    exit(0);
}

Database::transaccion(static function () use ($aCorregir): void {
    foreach ($aCorregir as $r) {
        $descripcion = ReintegroUnidadesService::descripcionConCantidad((string) $r['descripcion'], 0);
        Bien::actualizarResumenDeCodigo((int) $r['id'], $descripcion, 0.0, 'reintegrado');
        Asignacion::cerrarActivasDe((int) $r['id']);
        Auditoria::registrar(null, (int) $r['institucion_id'], 'correccion_datos', 'bien', (int) $r['id'],
            ['descripcion' => $r['descripcion'], 'valor' => $r['valor'], 'estado' => $r['estado']],
            ['descripcion' => $descripcion, 'valor' => 0, 'estado' => 'reintegrado',
                'motivo' => "Corrección: todas las unidades de la placa {$r['placa']} ya se reintegraron; el renglón de la cartera sale de ella"]);
    }
});
echo "Listo.\n";
