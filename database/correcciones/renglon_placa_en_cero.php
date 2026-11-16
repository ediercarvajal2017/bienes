<?php

declare(strict_types=1);

/**
 * Pone al día el "renglón de la cartera" de cada placa con unidades (App\Services\RenglonDePlaca):
 * el bien suelto con el código de la placa (p. ej. 13019802 "8 SILLA…") cuyas unidades del alta
 * masiva son 13019802-001…-008. Antes de v1.2.32 solo el reintegro por cantidad lo actualizaba;
 * reintegrar una unidad desde su ficha o por selección, reactivarla o pasarla a "Sin cartera" lo
 * dejaba con la cantidad, el valor o el estado viejos (y un renglón Activo sin sillas sumaba un
 * ítem de más).
 *
 *   php database/correcciones/renglon_placa_en_cero.php            (SIMULACIÓN: solo muestra)
 *   php database/correcciones/renglon_placa_en_cero.php --aplicar  (después del respaldo)
 *
 * Con --aplicar usa la misma regla que MIA aplica ahora en cada operación: cantidad y valor de
 * las unidades en cartera; Reintegrado si ya no queda ninguna y alguna se reintegró (cierra su
 * asignación); Activo de nuevo si quedan unidades y MIA lo había sacado. No crea movimientos ni
 * toca lotes. Queda en la auditoría (acción "editar", con el motivo).
 *
 * Solo informa (no cambia): los renglones sin ninguna unidad en cartera que no se reintegró
 * ninguna (todas pasaron a "Sin cartera": revisar a mano), los que el usuario reintegró aparte
 * con su propio movimiento, y los lotes de reintegro donde el renglón quedó junto a sus unidades
 * (el comprobante ya no lo suma).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Services\RenglonDePlaca;

Env::cargar();
$aplicar = array_key_exists('aplicar', getopt('', ['aplicar']) ?: []);
$pdo = Database::connection();
$pesos = static fn (float $v): string => '$' . number_format($v, 0, ',', '.');

// Todos los renglones de la cartera: bien sin lote con unidades de su código.
$renglones = $pdo->query(
    "SELECT r.id, r.institucion_id, i.nombre AS institucion, r.codigo_identificacion AS placa
       FROM bienes r
       JOIN instituciones i ON i.id = r.institucion_id
      WHERE r.lote IS NULL
        AND EXISTS (SELECT 1 FROM bienes u WHERE u.institucion_id = r.institucion_id AND u.lote = r.codigo_identificacion)
      ORDER BY i.nombre, r.codigo_identificacion"
)->fetchAll();

$aCorregir = [];
$revisar = [];
foreach ($renglones as $r) {
    $c = RenglonDePlaca::calcular((int) $r['institucion_id'], (string) $r['placa']);
    if ($c === null) {
        continue;
    }
    $b = $c['bien'];
    $donde = "#{$r['id']} {$r['institucion']} · placa {$r['placa']}";
    if ($c['cambia']) {
        $aCorregir[] = $r;
        echo "  {$donde}\n      hoy:     \"{$b['descripcion']}\" · " . $pesos((float) $b['valor']) . " · {$b['estado']}\n"
            . "      debería: \"{$c['descripcion']}\" · " . $pesos($c['valor']) . " · {$c['estado']}\n";
    }
    if ($c['quedan'] === 0 && $c['reintegradas'] === 0 && in_array($b['estado'], ['activo', 'en_reparacion'], true)) {
        $revisar[] = "  {$donde}: no queda ninguna unidad en cartera y ninguna se reintegró (pasaron a \"Sin cartera\")";
    } elseif ($c['quedan'] > 0 && $b['estado'] === 'reintegrado' && $c['reintegroPropio']) {
        $revisar[] = "  {$donde}: quedan {$c['quedan']} en cartera pero el renglón se reintegró aparte (tiene su propio reintegro)";
    }
}
echo "== Renglones de la cartera: " . count($renglones) . "; a poner al día: " . count($aCorregir) . "\n";

echo "\n== Para revisar a mano (no se cambian): " . count($revisar) . "\n" . implode("\n", $revisar) . ($revisar !== [] ? "\n" : '');

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
echo "\n== Lotes con el renglón junto a sus unidades (el comprobante ya no lo suma; no se cambian): " . count($lotes) . "\n";
foreach ($lotes as $l) {
    echo "  Lote #{$l['lote']} ({$l['estado']}) · {$l['institucion']} · placa {$l['placa']}\n";
}

echo "\nA poner al día: " . count($aCorregir) . ($aplicar ? "\n" : "  (SIMULACIÓN; use --aplicar después del respaldo)\n");
if (!$aplicar || $aCorregir === []) {
    exit(0);
}

$hechos = Database::transaccion(static function () use ($aCorregir): int {
    $hechos = 0;
    foreach ($aCorregir as $r) {
        if (RenglonDePlaca::sincronizar((int) $r['institucion_id'], (string) $r['placa'], 'Corrección del renglón de la placa') !== null) {
            $hechos++;
        }
    }

    return $hechos;
});
echo "Listo: {$hechos} renglón(es) al día.\n";
