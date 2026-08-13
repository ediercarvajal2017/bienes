<?php

declare(strict_types=1);

/**
 * Diagnóstico (SOLO LECTURA, no modifica nada) para el fallo de "php database/migrate.php"
 * al ejecutar 022_categorias_institucion_id_not_null.sql — reporta exactamente en qué
 * estado quedó la base de datos, sin adivinar.
 *
 * Uso: php database/diagnostico_migracion_022.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::cargar();
$pdo = Database::connection();

echo "=== 1. Migraciones marcadas como aplicadas (las 5 mas recientes) ===\n\n";
$aplicadas = $pdo->query('SELECT migracion, aplicada_en FROM schema_migrations ORDER BY aplicada_en DESC LIMIT 5')->fetchAll();
foreach ($aplicadas as $m) {
    echo "  {$m['migracion']}  ({$m['aplicada_en']})\n";
}

echo "\n=== 2. Estado actual de categorias_bienes.institucion_id ===\n\n";
$columna = $pdo->query(
    "SELECT IS_NULLABLE, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categorias_bienes' AND COLUMN_NAME = 'institucion_id'"
)->fetch();
echo "  Permite NULL: {$columna['IS_NULLABLE']}  (tipo: {$columna['COLUMN_TYPE']})\n";

echo "\n=== 3. ¿Existe la llave foránea fk_categoria_institucion? ===\n\n";
$fk = $pdo->query(
    "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categorias_bienes'
       AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'fk_categoria_institucion'"
)->fetch();
echo $fk ? "  SI existe.\n" : "  *** NO existe -- la tabla quedo sin esa proteccion. ***\n";

echo "\n=== 4. Filas de categorias_bienes con institucion_id NULL ===\n\n";
$nulos = (int) $pdo->query('SELECT COUNT(*) FROM categorias_bienes WHERE institucion_id IS NULL')->fetchColumn();
echo "  Total con NULL: {$nulos}\n";
if ($nulos > 0) {
    $filas = $pdo->query('SELECT id, nombre FROM categorias_bienes WHERE institucion_id IS NULL LIMIT 20')->fetchAll();
    foreach ($filas as $f) {
        echo "    #{$f['id']}: \"{$f['nombre']}\"\n";
    }
}

echo "\n=== 4b. Filas de categorias_bienes cuyo institucion_id NO existe en instituciones (huerfanas) ===\n\n";
$huerfanas = $pdo->query(
    "SELECT c.id, c.nombre, c.institucion_id
     FROM categorias_bienes c
     LEFT JOIN instituciones i ON i.id = c.institucion_id
     WHERE i.id IS NULL"
)->fetchAll();
echo '  Total huerfanas: ' . count($huerfanas) . "\n";
foreach ($huerfanas as $h) {
    echo "    #{$h['id']}: \"{$h['nombre']}\" -> institucion_id={$h['institucion_id']} (no existe)\n";
}

echo "\n=== 4c. ¿Algún bien usa esas categorías huérfanas? ===\n\n";
if (empty($huerfanas)) {
    echo "  (no aplica, no hay huerfanas)\n";
} else {
    $ids = array_column($huerfanas, 'id');
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $bienes = $pdo->prepare(
        "SELECT b.id, b.codigo_identificacion, b.institucion_id, b.categoria_id
         FROM bienes b WHERE b.categoria_id IN ({$marcadores})"
    );
    $bienes->execute($ids);
    $filas = $bienes->fetchAll();
    echo '  Total de bienes que las usan: ' . count($filas) . "\n";
    foreach ($filas as $b) {
        echo "    Bien #{$b['id']} (\"{$b['codigo_identificacion']}\", institucion_id={$b['institucion_id']}) -> categoria_id={$b['categoria_id']}\n";
    }
}

echo "\n=== 5. ¿Incluye movimientos.tipo el valor 'reactivacion' (migracion 023)? ===\n\n";
$tipo = $pdo->query(
    "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimientos' AND COLUMN_NAME = 'tipo'"
)->fetch();
echo "  {$tipo['COLUMN_TYPE']}\n";

echo "\nListo. Nada se modificó.\n";
