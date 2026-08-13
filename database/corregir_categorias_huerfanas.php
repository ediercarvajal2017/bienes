<?php

declare(strict_types=1);

/**
 * Corrige las categorías que quedaron con institucion_id = 0 (no NULL) — el script
 * original actualizar_categorias_por_institucion.php solo buscaba institucion_id IS NULL,
 * así que estas nunca fueron vistas ni migradas.
 *
 * Para cada bien que apunta a una de esas categorías huérfanas, busca la categoría
 * equivalente ya existente de SU PROPIA institución (mismo nombre — debería existir,
 * porque sembrarPorDefecto() ya la creó para cada institución) y reasigna el bien ahí.
 * Solo cuando ya ningún bien las use, borra las filas huérfanas.
 *
 * Es SEGURO correrlo más de una vez (idempotente): si ya no hay huérfanas, no hace nada.
 *
 * Uso:
 *   php database/corregir_categorias_huerfanas.php            (vista previa, no cambia nada)
 *   php database/corregir_categorias_huerfanas.php --aplicar  (aplica de verdad)
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::cargar();

$aplicar = in_array('--aplicar', $argv, true);

$pdo = Database::connection();

echo $aplicar ? "Modo: APLICAR cambios.\n\n" : "Modo: VISTA PREVIA (nada se va a modificar todavía).\n\n";

// --- 1. Categorías huérfanas: institucion_id apunta a algo que no existe en instituciones ---
$huerfanas = $pdo->query(
    "SELECT c.id, c.nombre, c.institucion_id
     FROM categorias_bienes c
     LEFT JOIN instituciones i ON i.id = c.institucion_id
     WHERE i.id IS NULL"
)->fetchAll();

echo 'Categorías huérfanas encontradas: ' . count($huerfanas) . "\n";
foreach ($huerfanas as $c) {
    echo "  - #{$c['id']} \"{$c['nombre']}\" (institucion_id={$c['institucion_id']})\n";
}
echo "\n";

if (empty($huerfanas)) {
    echo "No hay nada que corregir.\n";
    exit(0);
}

$idsHuerfanas = array_column($huerfanas, 'id');
$marcadores = implode(',', array_fill(0, count($idsHuerfanas), '?'));

// --- 2. Bienes afectados, agrupados por institución + categoría huérfana ---
$stmtBienes = $pdo->prepare(
    "SELECT institucion_id, categoria_id, COUNT(*) AS total
     FROM bienes WHERE categoria_id IN ({$marcadores})
     GROUP BY institucion_id, categoria_id"
);
$stmtBienes->execute($idsHuerfanas);
$grupos = $stmtBienes->fetchAll();

$totalBienesAfectados = (int) array_sum(array_column($grupos, 'total'));
echo "Bienes que se van a reasignar (por institución y categoría huérfana): {$totalBienesAfectados}\n\n";

// --- 3. Para cada grupo, buscar la categoría equivalente de esa institución ---
$nombresPorId = array_column($huerfanas, 'nombre', 'id');
$reasignaciones = []; // [institucion_id, categoria_huerfana_id, categoria_nueva_id, total]
$sinEquivalente = []; // grupos sin categoría de destino encontrada -- no se tocan

foreach ($grupos as $g) {
    $institucionId = (int) $g['institucion_id'];
    $categoriaHuerfanaId = (int) $g['categoria_id'];
    $nombre = $nombresPorId[$categoriaHuerfanaId];

    $stmtEquivalente = $pdo->prepare(
        'SELECT id FROM categorias_bienes WHERE institucion_id = ? AND nombre = ?'
    );
    $stmtEquivalente->execute([$institucionId, $nombre]);
    $idEquivalente = $stmtEquivalente->fetchColumn();

    if ($idEquivalente) {
        $reasignaciones[] = [
            'institucion_id' => $institucionId,
            'categoria_vieja' => $categoriaHuerfanaId,
            'categoria_nueva' => (int) $idEquivalente,
            'nombre' => $nombre,
            'total' => (int) $g['total'],
        ];
    } else {
        $sinEquivalente[] = [
            'institucion_id' => $institucionId,
            'categoria_vieja' => $categoriaHuerfanaId,
            'nombre' => $nombre,
            'total' => (int) $g['total'],
        ];
    }
}

echo "Reasignaciones a realizar:\n";
foreach ($reasignaciones as $r) {
    echo "  - Institución {$r['institucion_id']}: {$r['total']} bien(es) de \"{$r['nombre']}\" (#{$r['categoria_vieja']}) -> #{$r['categoria_nueva']}\n";
}

if (!empty($sinEquivalente)) {
    echo "\n*** SIN categoría equivalente encontrada (no se van a tocar, requieren revisión manual): ***\n";
    foreach ($sinEquivalente as $s) {
        echo "  - Institución {$s['institucion_id']}: {$s['total']} bien(es) de \"{$s['nombre']}\" (#{$s['categoria_vieja']})\n";
    }
}

echo "\n";

if (!$aplicar) {
    echo "Vista previa terminada. Corre con --aplicar para ejecutar los cambios de verdad.\n";
    exit(0);
}

// --- 4. Aplicar ---
$pdo->beginTransaction();
try {
    $bienesActualizados = 0;
    foreach ($reasignaciones as $r) {
        $stmtUpdate = $pdo->prepare('UPDATE bienes SET categoria_id = ? WHERE institucion_id = ? AND categoria_id = ?');
        $stmtUpdate->execute([$r['categoria_nueva'], $r['institucion_id'], $r['categoria_vieja']]);
        $bienesActualizados += $stmtUpdate->rowCount();
    }

    $totalEsperado = (int) array_sum(array_column($reasignaciones, 'total'));
    if ($bienesActualizados !== $totalEsperado) {
        throw new \RuntimeException("Descuadre: se esperaban {$totalEsperado} bienes reasignados, se reasignaron {$bienesActualizados}. Se revierte todo.");
    }

    // --- 5. Borrar las huérfanas que ya nadie usa (las que sí tenían equivalente y quedaron
    // en 0 referencias). Las que están en $sinEquivalente NO se tocan ni se borran.
    $idsConEquivalente = array_unique(array_column($reasignaciones, 'categoria_vieja'));
    $idsSinEquivalente = array_unique(array_column($sinEquivalente, 'categoria_vieja'));
    $idsABorrar = array_diff($idsHuerfanas, $idsSinEquivalente);

    $borradas = 0;
    if (!empty($idsABorrar)) {
        $marcadoresBorrar = implode(',', array_fill(0, count($idsABorrar), '?'));
        $stmtVerifica = $pdo->prepare("SELECT COUNT(*) FROM bienes WHERE categoria_id IN ({$marcadoresBorrar})");
        $stmtVerifica->execute(array_values($idsABorrar));
        $restantes = (int) $stmtVerifica->fetchColumn();

        if ($restantes > 0) {
            throw new \RuntimeException("Quedan {$restantes} bienes apuntando a categorías que se iban a borrar. Se revierte todo.");
        }

        $stmtBorrar = $pdo->prepare("DELETE FROM categorias_bienes WHERE id IN ({$marcadoresBorrar})");
        $stmtBorrar->execute(array_values($idsABorrar));
        $borradas = $stmtBorrar->rowCount();
    }

    $pdo->commit();

    echo "Bienes reasignados: {$bienesActualizados}\n";
    echo "Categorías huérfanas eliminadas: {$borradas}\n";
    if (!empty($idsSinEquivalente)) {
        echo 'Categorías huérfanas que quedaron SIN tocar (requieren revisión manual): ' . implode(', ', $idsSinEquivalente) . "\n";
    }
    echo "\nListo. Vuelve a correr database/diagnostico_migracion_022.php para confirmar.\n";
} catch (\Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Error, no se aplicó ningún cambio: ' . $e->getMessage() . "\n");
    exit(1);
}
