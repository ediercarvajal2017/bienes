<?php

declare(strict_types=1);

/**
 * Paso 2 de 3 de la migración "categorías por institución" (ver migración 021 y 022).
 * Corre DESPUÉS de 021_categorias_institucion_id.sql y ANTES de
 * 022_categorias_institucion_id_not_null.sql.
 *
 * Antes de este cambio, categorias_bienes era una sola lista compartida por TODAS
 * las instituciones del sistema — sin aislamiento, cualquier rector podía editar la
 * categoría de otro colegio sin darse cuenta. Este script reparte esas categorías
 * compartidas en copias propias por institución, para solo las 4 categorías
 * confirmadas en uso real (ver CATEGORIAS_A_MIGRAR) — cualquier otra categoría del
 * catálogo viejo (datos de prueba, categorías sin bienes reales) se descarta.
 *
 * Es SEGURO correrlo más de una vez (idempotente): si una categoría ya tiene dueño,
 * no se vuelve a crear ni se duplica.
 *
 * Uso:
 *   php database/actualizar_categorias_por_institucion.php            (vista previa, no cambia nada)
 *   php database/actualizar_categorias_por_institucion.php --aplicar  (aplica de verdad)
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::cargar();

const CATEGORIAS_A_MIGRAR = ['Muebles', 'Sin cartera', 'Otra IE', 'Tecnología'];

$aplicar = in_array('--aplicar', $argv, true);

$pdo = Database::connection();

echo $aplicar ? "Modo: APLICAR cambios.\n\n" : "Modo: VISTA PREVIA (nada se va a modificar todavía).\n\n";

// --- 1. Categorías viejas (sin institución todavía) que coinciden con la lista a migrar ---
$marcadores = implode(',', array_fill(0, count(CATEGORIAS_A_MIGRAR), '?'));
$stmt = $pdo->prepare("SELECT id, nombre FROM categorias_bienes WHERE institucion_id IS NULL AND nombre IN ({$marcadores})");
$stmt->execute(CATEGORIAS_A_MIGRAR);
$categoriasViejas = $stmt->fetchAll(); // ['id' => ..., 'nombre' => ...]

echo 'Categorías compartidas encontradas para migrar: ' . count($categoriasViejas) . "\n";
foreach ($categoriasViejas as $c) {
    echo "  - #{$c['id']} {$c['nombre']}\n";
}
$nombresEncontrados = array_column($categoriasViejas, 'nombre');
foreach (CATEGORIAS_A_MIGRAR as $nombre) {
    if (!in_array($nombre, $nombresEncontrados, true)) {
        echo "  (no existe en este entorno: \"{$nombre}\" — se omite, no es un error)\n";
    }
}
echo "\n";

// --- 2. Instituciones que tienen al menos un bien ---
$institucionesConBienes = $pdo->query('SELECT DISTINCT institucion_id FROM bienes')->fetchAll(PDO::FETCH_COLUMN);
echo 'Instituciones con bienes registrados: ' . count($institucionesConBienes) . ' (' . implode(', ', $institucionesConBienes) . ")\n\n";

// --- 3. Conteo "antes" por institución + nombre de categoría (para verificar después) ---
$conteoAntes = [];
foreach ($institucionesConBienes as $institucionId) {
    foreach ($categoriasViejas as $c) {
        $stmtConteo = $pdo->prepare('SELECT COUNT(*) FROM bienes WHERE institucion_id = ? AND categoria_id = ?');
        $stmtConteo->execute([$institucionId, $c['id']]);
        $conteoAntes["{$institucionId}|{$c['nombre']}"] = (int) $stmtConteo->fetchColumn();
    }
}

$totalBienesAfectados = array_sum($conteoAntes);
echo "Bienes que se van a reasignar (por institución y categoría):\n";
foreach ($conteoAntes as $clave => $cantidad) {
    if ($cantidad > 0) {
        echo "  - {$clave}: {$cantidad}\n";
    }
}
echo "Total: {$totalBienesAfectados}\n\n";

if (!$aplicar) {
    echo "Vista previa terminada. Corre con --aplicar para ejecutar los cambios de verdad.\n";
    exit(0);
}

// --- 4. Aplicar: crear (o reutilizar) la categoría propia de cada institución, y reapuntar los bienes ---
$pdo->beginTransaction();
try {
    $nuevosIds = []; // "{institucion_id}|{nombre}" => id nuevo
    $creadas = 0;
    $reutilizadas = 0;

    foreach ($institucionesConBienes as $institucionId) {
        foreach ($categoriasViejas as $c) {
            $stmtExiste = $pdo->prepare('SELECT id FROM categorias_bienes WHERE institucion_id = ? AND nombre = ?');
            $stmtExiste->execute([$institucionId, $c['nombre']]);
            $idExistente = $stmtExiste->fetchColumn();

            if ($idExistente) {
                $nuevosIds["{$institucionId}|{$c['nombre']}"] = (int) $idExistente;
                $reutilizadas++;
                continue;
            }

            $stmtCrear = $pdo->prepare('INSERT INTO categorias_bienes (institucion_id, nombre, activo) VALUES (?, ?, 1)');
            $stmtCrear->execute([$institucionId, $c['nombre']]);
            $nuevosIds["{$institucionId}|{$c['nombre']}"] = (int) $pdo->lastInsertId();
            $creadas++;
        }
    }

    $bienesActualizados = 0;
    foreach ($institucionesConBienes as $institucionId) {
        foreach ($categoriasViejas as $c) {
            $nuevoId = $nuevosIds["{$institucionId}|{$c['nombre']}"];
            $stmtUpdate = $pdo->prepare('UPDATE bienes SET categoria_id = ? WHERE institucion_id = ? AND categoria_id = ?');
            $stmtUpdate->execute([$nuevoId, $institucionId, $c['id']]);
            $bienesActualizados += $stmtUpdate->rowCount();
        }
    }

    // --- 5. Verificación: el total movido debe coincidir exactamente con el "antes" ---
    if ($bienesActualizados !== $totalBienesAfectados) {
        throw new \RuntimeException("Descuadre: se esperaban {$totalBienesAfectados} bienes reasignados, se reasignaron {$bienesActualizados}. Se revierte todo.");
    }

    // --- 6. Cualquier bien que siga apuntando a una categoría vieja compartida (fuera de
    // la lista a migrar, ej. datos de prueba) queda sin categoría — no se pierde el bien,
    // solo pierde la categoría inválida que ya no va a existir.
    $idsViejos = array_column($categoriasViejas, 'id');
    $bienesSinCategoriaValida = 0;
    if (!empty($idsViejos)) {
        $marcadoresViejos = implode(',', array_fill(0, count($idsViejos), '?'));
        $stmtOtras = $pdo->prepare("SELECT id FROM categorias_bienes WHERE institucion_id IS NULL");
        $stmtOtras->execute();
        $todasLasViejas = $stmtOtras->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($todasLasViejas)) {
            $marcadoresTodas = implode(',', array_fill(0, count($todasLasViejas), '?'));
            $stmtNull = $pdo->prepare("UPDATE bienes SET categoria_id = NULL WHERE categoria_id IN ({$marcadoresTodas})");
            $stmtNull->execute($todasLasViejas);
            $bienesSinCategoriaValida = $stmtNull->rowCount();
        }
    }

    // --- 7. Borrar TODAS las categorías compartidas viejas (institucion_id IS NULL) — ya
    // nada las referencia, sea porque se migraron (paso 4) o porque se limpiaron (paso 6).
    $borradas = $pdo->exec('DELETE FROM categorias_bienes WHERE institucion_id IS NULL');

    $pdo->commit();

    echo "Categorías creadas nuevas: {$creadas}\n";
    echo "Categorías reutilizadas (ya existían de una corrida anterior): {$reutilizadas}\n";
    echo "Bienes reasignados a su nueva categoría por institución: {$bienesActualizados}\n";
    echo "Bienes que quedaron sin categoría (usaban una categoría fuera de la lista a migrar): {$bienesSinCategoriaValida}\n";
    echo "Categorías compartidas viejas eliminadas del catálogo global: {$borradas}\n";
    echo "\nListo. Corre ahora la migración 022 para exigir institucion_id obligatorio.\n";
} catch (\Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Error, no se aplicó ningún cambio: ' . $e->getMessage() . "\n");
    exit(1);
}
