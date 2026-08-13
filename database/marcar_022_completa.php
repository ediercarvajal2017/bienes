<?php

declare(strict_types=1);

/**
 * 022_categorias_institucion_id_not_null.sql quedó en un estado del que ya no se puede
 * recuperar corriéndola de nuevo tal cual: sus dos primeros pasos (quitar la NOT NULL
 * vieja, convertir la columna) SÍ se aplicaron; el tercero (volver a poner la llave
 * foránea) falló y nunca se reintentó. Si migrate.php la vuelve a intentar, su PRIMER
 * paso ahora falla (ya no existe la llave foránea que intenta quitar).
 *
 * Este script la marca como aplicada en schema_migrations — sin volver a ejecutar su
 * SQL — para que migrate.php la salte y siga con las migraciones pendientes
 * (023 y la nueva 024, que sí vuelve a poner la llave foránea).
 *
 * Requisito: correr database/corregir_categorias_huerfanas.php --aplicar PRIMERO, y
 * confirmar con diagnostico_migracion_022.php que ya no hay categorías huérfanas, o la
 * migración 024 va a fallar igual que falló el tercer paso de la 022.
 *
 * Uso: php database/marcar_022_completa.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::cargar();
$pdo = Database::connection();

$nombre = '022_categorias_institucion_id_not_null.sql';

$stmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migracion = ?');
$stmt->execute([$nombre]);

if ($stmt->fetchColumn()) {
    echo "Ya estaba marcada como aplicada. No se hizo nada.\n";
    exit(0);
}

$huerfanas = (int) $pdo->query(
    "SELECT COUNT(*) FROM categorias_bienes c
     LEFT JOIN instituciones i ON i.id = c.institucion_id
     WHERE i.id IS NULL"
)->fetchColumn();

if ($huerfanas > 0) {
    fwrite(STDERR, "Todavía hay {$huerfanas} categoría(s) huérfana(s) -- corre corregir_categorias_huerfanas.php --aplicar primero.\n");
    exit(1);
}

$pdo->prepare('INSERT INTO schema_migrations (migracion) VALUES (?)')->execute([$nombre]);

echo "Marcada como aplicada. Ahora corre: php database/migrate.php\n";
