<?php

declare(strict_types=1);

/**
 * Fase 1 del código automático para "Sin cartera": las instituciones NUEVAS ya reciben
 * las 4 categorías base automáticamente (ver InstitucionController::guardar()), pero las
 * que ya existían antes de ese cambio solo recibieron "Sin cartera" si el script de
 * corrección de categorías (actualizar_categorias_por_institucion.php) encontró bienes
 * reales usándola — una institución sin ningún bien en esa categoría se quedó sin ella.
 *
 * Este script recorre TODAS las instituciones y llama a Categoria::sembrarPorDefecto()
 * (ya idempotente: no duplica ni toca las que ya existan) — así queda garantizado que
 * "Sin cartera" (y el resto del set base) exista en cada una antes de activar el código
 * automático, que depende de que esa categoría siempre esté disponible.
 *
 * Uso: php database/asegurar_categorias_por_defecto.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Models\Categoria;

Env::cargar();

$pdo = Database::connection();
$instituciones = $pdo->query('SELECT id, nombre FROM instituciones ORDER BY id')->fetchAll();

echo 'Instituciones encontradas: ' . count($instituciones) . "\n\n";

foreach ($instituciones as $institucion) {
    $antes = $pdo->prepare('SELECT COUNT(*) FROM categorias_bienes WHERE institucion_id = ?');
    $antes->execute([$institucion['id']]);
    $totalAntes = (int) $antes->fetchColumn();

    Categoria::sembrarPorDefecto((int) $institucion['id']);

    $despues = $pdo->prepare('SELECT COUNT(*) FROM categorias_bienes WHERE institucion_id = ?');
    $despues->execute([$institucion['id']]);
    $totalDespues = (int) $despues->fetchColumn();

    $creadas = $totalDespues - $totalAntes;
    if ($creadas > 0) {
        echo "#{$institucion['id']} {$institucion['nombre']}: {$creadas} categoría(s) nueva(s) sembrada(s)\n";
    } else {
        echo "#{$institucion['id']} {$institucion['nombre']}: ya tenía el set completo, sin cambios\n";
    }
}

echo "\nListo.\n";
