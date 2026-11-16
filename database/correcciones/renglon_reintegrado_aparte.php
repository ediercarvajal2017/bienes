<?php

declare(strict_types=1);

/**
 * Corrección de los renglones de la cartera que se reintegraron APARTE mientras sus unidades
 * siguen en cartera (antes de v1.2.31 el renglón se podía reintegrar como si fuera una silla
 * más: p. ej. la placa 200033042 "SILLAS UNIVERSITARIAS" quedó Reintegrada con 137 sillas en la
 * institución). Lo lista database/correcciones/renglon_placa_en_cero.php ("para revisar a mano").
 *
 *   php database/correcciones/renglon_reintegrado_aparte.php                      (SIMULACIÓN)
 *   php database/correcciones/renglon_reintegrado_aparte.php --aplicar            (después del respaldo)
 *   … --responsable=ID   usuario que firma la reactivación (por defecto, el rector activo de la institución)
 *
 * Por cada renglón, en una sola transacción:
 *  1. su movimiento de reintegro sale del lote de reintegro (solo si el lote sigue PENDIENTE;
 *     si ya se entregó a la Alcaldía, no se toca y se informa);
 *  2. se registra su reactivación (motivo: "se reintegró por error junto con sus unidades");
 *  3. vuelve a Activo y a la ubicación que tenía antes del reintegro (espacio o persona de
 *     origen del movimiento), si sigue activa;
 *  4. su cantidad queda al día con RenglonDePlaca, y su valor, en proporción a su valor ORIGINAL
 *     (el primero que registra la auditoría): la regla anterior a v1.2.32 lo calculaba con la
 *     suma de las unidades y, si estas valían $0, lo dejaba en $0 (placa 200388980: "12 PUESTOS"
 *     por $2.554.188 quedó en "8 PUESTOS" por $0).
 * Todo queda en la auditoría con la acción "correccion_datos".
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Helpers\FechaMovimiento;
use App\Models\Asignacion;
use App\Models\Auditoria;
use App\Models\Bien;
use App\Models\Movimiento;
use App\Services\RenglonDePlaca;

Env::cargar();
$opciones = getopt('', ['aplicar', 'responsable:']) ?: [];
$aplicar = array_key_exists('aplicar', $opciones);
$responsableElegido = isset($opciones['responsable']) ? (int) $opciones['responsable'] : null;
$pdo = Database::connection();
$pesos = static fn (float $v): string => '$' . number_format($v, 0, ',', '.');
const MOTIVO = 'Corrección: el renglón de la cartera se reintegró por error junto con sus unidades (siguen en la institución)';

// Renglones de la cartera Reintegrados cuyo último reintegro/reactivación es su propio reintegro.
$candidatos = $pdo->query(
    "SELECT r.id, r.institucion_id, i.nombre AS institucion, r.codigo_identificacion AS placa
       FROM bienes r
       JOIN instituciones i ON i.id = r.institucion_id
      WHERE r.lote IS NULL AND r.estado = 'reintegrado'
        AND EXISTS (SELECT 1 FROM bienes u WHERE u.institucion_id = r.institucion_id AND u.lote = r.codigo_identificacion)
      ORDER BY i.nombre, r.codigo_identificacion"
)->fetchAll();

$stmtMovimiento = $pdo->prepare(
    "SELECT m.id, m.tipo, m.fecha, m.lote_reintegro_id, m.espacio_origen_id, m.persona_origen_id,
            l.estado AS lote_estado, e.activo AS espacio_activo, CONCAT(e.codigo, ' - ', e.nombre) AS espacio_nombre,
            p.activo AS persona_activa, CONCAT(p.nombres, ' ', p.apellidos) AS persona_nombre
       FROM movimientos m
       LEFT JOIN lotes_reintegro l ON l.id = m.lote_reintegro_id
       LEFT JOIN espacios e ON e.id = m.espacio_origen_id
       LEFT JOIN usuarios p ON p.id = m.persona_origen_id
      WHERE m.bien_id = ? AND m.tipo IN ('reintegro', 'reactivacion')
      ORDER BY m.id DESC LIMIT 1"
);
// El primer valor conocido del renglón, con su cantidad ("12 PUESTOS…" por $2.554.188).
$stmtHistorial = $pdo->prepare(
    "SELECT datos_antes FROM auditoria WHERE entidad = 'bien' AND entidad_id = ? AND datos_antes IS NOT NULL ORDER BY id ASC"
);
$valorOriginal = static function (int $bienId) use ($stmtHistorial): ?array {
    $stmtHistorial->execute([$bienId]);
    foreach ($stmtHistorial->fetchAll(\PDO::FETCH_COLUMN) as $json) {
        $antes = json_decode((string) $json, true);
        if (is_array($antes) && isset($antes['descripcion'], $antes['valor'])) {
            $cantidad = RenglonDePlaca::cantidadQueDice((string) $antes['descripcion']);
            if ($cantidad !== null && $cantidad > 0) {
                return ['cantidad' => $cantidad, 'valor' => (float) $antes['valor']];
            }
        }
    }

    return null;
};
$stmtRector = $pdo->prepare(
    "SELECT u.id, CONCAT(u.nombres, ' ', u.apellidos) AS nombre
       FROM usuarios u JOIN roles ro ON ro.id = u.rol_id
      WHERE ro.nombre = 'rector' AND u.institucion_id = ? AND u.activo = 1 AND u.eliminado_en IS NULL
      ORDER BY u.id LIMIT 2"
);

$plan = [];
foreach ($candidatos as $r) {
    $calculo = RenglonDePlaca::calcular((int) $r['institucion_id'], (string) $r['placa']);
    $stmtMovimiento->execute([(int) $r['id']]);
    $mov = $stmtMovimiento->fetch();
    if ($calculo === null || $calculo['quedan'] === 0 || !$mov || $mov['tipo'] !== 'reintegro') {
        continue; // Sin sillas en cartera, o ya reactivado: está bien así.
    }
    $b = $calculo['bien'];
    $donde = "#{$r['id']} {$r['institucion']} · placa {$r['placa']}";

    if ($mov['lote_reintegro_id'] !== null && $mov['lote_estado'] !== 'pendiente') {
        echo "  {$donde}: su reintegro está en el lote #{$mov['lote_reintegro_id']}, ya {$mov['lote_estado']}: NO se toca (revisar con la Alcaldía).\n";
        continue;
    }

    $stmtRector->execute([(int) $r['institucion_id']]);
    $rectores = $stmtRector->fetchAll();
    $responsable = $responsableElegido ?? (count($rectores) === 1 ? (int) $rectores[0]['id'] : null);
    if ($responsable === null) {
        echo "  {$donde}: " . ($rectores === [] ? 'no hay un rector activo' : 'hay más de un rector activo') . "; indique --responsable=ID.\n";
        continue;
    }

    $volverA = null;
    if ($mov['espacio_origen_id'] !== null && (int) $mov['espacio_activo'] === 1) {
        $volverA = "espacio {$mov['espacio_nombre']}" . ($mov['persona_origen_id'] !== null && (int) $mov['persona_activa'] === 1 ? " (a cargo de {$mov['persona_nombre']})" : '');
    } elseif ($mov['espacio_origen_id'] === null && $mov['persona_origen_id'] !== null && (int) $mov['persona_activa'] === 1) {
        $volverA = "a cargo de {$mov['persona_nombre']}";
    }

    $descripcion = RenglonDePlaca::descripcionConCantidad((string) $b['descripcion'], $calculo['quedan']);
    $original = $valorOriginal((int) $r['id']);
    $valor = $original !== null ? round($original['valor'] / $original['cantidad'] * $calculo['quedan'], 2) : $calculo['valor'];
    echo "  {$donde}\n"
        . "      hoy:     \"{$b['descripcion']}\" · " . $pesos((float) $b['valor']) . " · reintegrado (reintegro del {$mov['fecha']}"
        . ($mov['lote_reintegro_id'] !== null ? ", en el lote #{$mov['lote_reintegro_id']}" : ', sin lote') . ")\n"
        . "      quedará: \"{$descripcion}\" · " . $pesos($valor) . " · activo · " . ($volverA ?? 'sin asignar (su ubicación anterior ya no está activa)') . "\n"
        . ($original !== null && abs($valor - (float) $b['valor']) >= 1
            ? "      valor en proporción al original: {$original['cantidad']} por " . $pesos($original['valor']) . "\n" : '')
        . ($mov['lote_reintegro_id'] !== null ? "      su reintegro sale del lote #{$mov['lote_reintegro_id']} (pendiente)\n" : '');
    $plan[] = ['renglon' => $r, 'mov' => $mov, 'responsable' => $responsable, 'valor' => $valor];
}

echo "\nA corregir: " . count($plan) . ($aplicar ? "\n" : "  (SIMULACIÓN; use --aplicar después del respaldo)\n");
if (!$aplicar || $plan === []) {
    exit(0);
}

$hoy = FechaMovimiento::hoy();
Database::transaccion(static function (\PDO $pdo) use ($plan, $hoy): void {
    foreach ($plan as ['renglon' => $r, 'mov' => $mov, 'responsable' => $responsable, 'valor' => $valor]) {
        $bienId = (int) $r['id'];
        $institucionId = (int) $r['institucion_id'];
        $antes = Bien::find($bienId);

        // 1) Su reintegro sale del lote pendiente (si otra persona lo cambió, se deshace todo).
        if ($mov['lote_reintegro_id'] !== null) {
            $st = $pdo->prepare("UPDATE movimientos m JOIN lotes_reintegro l ON l.id = m.lote_reintegro_id
                                    SET m.lote_reintegro_id = NULL
                                  WHERE m.id = ? AND m.lote_reintegro_id = ? AND l.estado = 'pendiente'");
            $st->execute([(int) $mov['id'], (int) $mov['lote_reintegro_id']]);
            if ($st->rowCount() !== 1) {
                throw new \RuntimeException("El lote #{$mov['lote_reintegro_id']} cambió mientras se corregía el renglón #{$bienId}: no se aplicó nada.");
            }
            Auditoria::registrar($responsable, $institucionId, 'correccion_datos', 'lote_reintegro', (int) $mov['lote_reintegro_id'],
                ['movimiento_id' => (int) $mov['id'], 'bien_id' => $bienId], ['movimiento_quitado' => (int) $mov['id'], 'motivo' => MOTIVO]);
        }

        // 2) y 3) Reactivación, de vuelta a donde estaba.
        $espacio = $mov['espacio_origen_id'] !== null && (int) $mov['espacio_activo'] === 1 ? (int) $mov['espacio_origen_id'] : null;
        $persona = $mov['persona_origen_id'] !== null && (int) $mov['persona_activa'] === 1 ? (int) $mov['persona_origen_id'] : null;
        if ($mov['espacio_origen_id'] !== null && $espacio === null) {
            $persona = null; // El espacio ya no existe: queda sin asignar, no a medias.
        }
        $reactivacionId = Movimiento::crear([
            'bien_id' => $bienId,
            'tipo' => 'reactivacion',
            'fecha' => $hoy,
            'responsable_id' => $responsable,
            'espacio_origen_id' => null,
            'espacio_destino_id' => $espacio,
            'destino_texto' => null,
            'observaciones' => MOTIVO,
        ]);
        Bien::cambiarEstado($bienId, 'activo');
        if ($espacio !== null || $persona !== null) {
            Asignacion::crear([
                'bien_id' => $bienId,
                'espacio_id' => $espacio,
                'usuario_responsable_id' => $persona,
                'fecha_asignacion' => $hoy,
                'observaciones' => 'Corrección: vuelve a donde estaba antes del reintegro por error',
                'asignado_por' => $responsable,
            ]);
        }

        // 4) Cantidad al día, y valor en proporción al original.
        RenglonDePlaca::sincronizar($institucionId, (string) $r['placa'], 'Corrección del renglón reintegrado por error');
        $alDia = (array) Bien::find($bienId);
        if (abs($valor - (float) $alDia['valor']) >= 0.005) {
            Bien::actualizarResumenDeCodigo($bienId, (string) $alDia['descripcion'], $valor, (string) $alDia['estado']);
        }

        $despues = Bien::find($bienId);
        Auditoria::registrar($responsable, $institucionId, 'correccion_datos', 'bien', $bienId,
            ['estado' => 'reintegrado', 'descripcion' => $antes['descripcion'] ?? null, 'valor' => $antes['valor'] ?? null,
                'lote_reintegro_id' => $mov['lote_reintegro_id']],
            ['estado' => 'activo', 'descripcion' => $despues['descripcion'] ?? null, 'valor' => $despues['valor'] ?? null,
                'movimiento_reactivacion_id' => $reactivacionId, 'espacio_id' => $espacio, 'usuario_responsable_id' => $persona, 'motivo' => MOTIVO]);
        echo "  #{$bienId} placa {$r['placa']}: \"" . ($despues['descripcion'] ?? '') . "\", activo.\n";
    }
});
echo "Listo.\n";
