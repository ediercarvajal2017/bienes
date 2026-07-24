<?php
// Ejecuta, en orden, los .sql de database/migrations/ que aún no se hayan aplicado.
// Uso: php database/migrate.php

require __DIR__ . '/../vendor/autoload.php';

$dbConfig = require __DIR__ . '/../config/database.php';

$pdo = new PDO(
    "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset={$dbConfig['charset']}",
    $dbConfig['username'],
    $dbConfig['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$dbConfig['database']}`");

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migracion VARCHAR(150) NOT NULL PRIMARY KEY,
        aplicada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB'
);

$aplicadas = $pdo->query('SELECT migracion FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

$files = glob(__DIR__ . '/migrations/*.sql');
sort($files);

foreach ($files as $file) {
    $nombre = basename($file);

    if (in_array($nombre, $aplicadas, true)) {
        continue;
    }

    echo "Ejecutando {$nombre}...\n";
    $sql = file_get_contents($file);
    $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql)));

    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }

    $pdo->prepare('INSERT INTO schema_migrations (migracion) VALUES (?)')->execute([$nombre]);
}

echo "Migraciones completadas.\n";
