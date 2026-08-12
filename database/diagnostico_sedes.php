<?php

declare(strict_types=1);

/**
 * Diagnóstico (SOLO LECTURA, no modifica nada) para el problema reportado: "el sistema
 * no deja crear más de una sede/sección por institución principal".
 *
 * Reporta, para cada institución principal: cuántas secciones tiene, sus códigos DANE
 * (para detectar duplicados o códigos que excedan el límite de la columna, VARCHAR(20)),
 * y cualquier sección "huérfana" (con institucion_padre_id apuntando a algo que ya no
 * existe o está inactivo).
 *
 * Uso: php database/diagnostico_sedes.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::cargar();

$pdo = Database::connection();

echo "=== 1. Todas las instituciones principales, con sus secciones ===\n\n";

$principales = $pdo->query(
    "SELECT id, codigo_dane, nombre, activo FROM instituciones WHERE tipo_sede = 'principal' ORDER BY id"
)->fetchAll();

foreach ($principales as $p) {
    echo "Principal #{$p['id']}: \"{$p['nombre']}\"  (DANE: {$p['codigo_dane']}, " . strlen($p['codigo_dane']) . " caracteres, activo={$p['activo']})\n";

    $secciones = $pdo->prepare(
        "SELECT id, codigo_dane, nombre, activo FROM instituciones WHERE institucion_padre_id = ? ORDER BY id"
    );
    $secciones->execute([$p['id']]);
    $filas = $secciones->fetchAll();

    if (empty($filas)) {
        echo "    (sin secciones)\n\n";
        continue;
    }

    foreach ($filas as $s) {
        $largo = strlen($s['codigo_dane']);
        $alerta = $largo > 20 ? '  *** EXCEDE 20 CARACTERES, LA COLUMNA LO VA A RECORTAR ***' : '';
        echo "    Sección #{$s['id']}: \"{$s['nombre']}\"  (DANE: {$s['codigo_dane']}, {$largo} caracteres, activo={$s['activo']}){$alerta}\n";
    }
    echo "    Total de secciones: " . count($filas) . "\n\n";
}

echo "=== 2. Secciones \"huérfanas\" (institucion_padre_id nulo o apunta a algo que ya no existe) ===\n\n";

$huerfanas = $pdo->query(
    "SELECT s.id, s.codigo_dane, s.nombre, s.institucion_padre_id
     FROM instituciones s
     WHERE s.tipo_sede = 'seccion'
       AND (s.institucion_padre_id IS NULL
            OR NOT EXISTS (SELECT 1 FROM instituciones p WHERE p.id = s.institucion_padre_id))"
)->fetchAll();

if (empty($huerfanas)) {
    echo "Ninguna. Todas las secciones están correctamente vinculadas a una principal.\n\n";
} else {
    foreach ($huerfanas as $h) {
        echo "  *** Sección #{$h['id']} \"{$h['nombre']}\" (DANE: {$h['codigo_dane']}) — institucion_padre_id = " . ($h['institucion_padre_id'] ?? 'NULL') . " (no existe) ***\n";
    }
    echo "\n";
}

echo "=== 3. Códigos DANE duplicados o casi idénticos (primeros 20 caracteres iguales) ===\n\n";

$todos = $pdo->query('SELECT id, codigo_dane, nombre FROM instituciones ORDER BY codigo_dane')->fetchAll();
$porPrefijo = [];
foreach ($todos as $i) {
    $prefijo = substr($i['codigo_dane'], 0, 20);
    $porPrefijo[$prefijo][] = $i;
}

$huboColision = false;
foreach ($porPrefijo as $prefijo => $grupo) {
    if (count($grupo) > 1) {
        $huboColision = true;
        echo "  *** Mismos primeros 20 caracteres (\"{$prefijo}\"): ***\n";
        foreach ($grupo as $i) {
            echo "      #{$i['id']} \"{$i['nombre']}\" -> código completo: \"{$i['codigo_dane']}\"\n";
        }
    }
}
if (!$huboColision) {
    echo "Ninguna colisión encontrada.\n";
}

echo "\nListo. Nada se modificó.\n";
