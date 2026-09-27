<?php
// Ejecuta, en orden, los .sql de database/migrations/ que aún no se hayan aplicado.
// Uso: php database/migrate.php
//      php database/migrate.php --pendientes   (SOLO LECTURA: lista lo que falta aplicar,
//                                               sin ejecutar nada ni crear tablas)
//
// Cada archivo se registra en schema_migrations solo si TODAS sus sentencias corrieron
// bien. Si una falla, el script se detiene con código de salida 1 e indica el archivo y
// la sentencia (en MySQL/MariaDB los ALTER/CREATE no se pueden deshacer con una
// transacción, por eso las migraciones deben escribirse idempotentes — ver 024).

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

App\Core\Env::cargar();

$dbConfig = require __DIR__ . '/../config/database.php';

$pdo = new PDO(
    "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset={$dbConfig['charset']}",
    $dbConfig['username'],
    $dbConfig['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$soloListar = in_array('--pendientes', $argv, true);

if ($soloListar) {
    // Solo lectura: no crea la base ni schema_migrations si no existen.
    $pdo->exec("USE `{$dbConfig['database']}`");
    $existeTabla = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations'"
    )->fetchColumn() > 0;
    $aplicadas = $existeTabla ? $pdo->query('SELECT migracion FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN) : [];
    $archivos = glob(__DIR__ . '/migrations/*.sql') ?: [];
    sort($archivos);
    $pendientes = array_values(array_filter(array_map('basename', $archivos), static fn ($n) => !in_array($n, $aplicadas, true)));

    echo $pendientes === [] ? "No hay migraciones pendientes.\n" : "Migraciones pendientes (" . count($pendientes) . "):\n  - " . implode("\n  - ", $pendientes) . "\n";
    exit(0);
}

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

    foreach ($statements as $i => $statement) {
        if ($statement === '') {
            continue;
        }

        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            fwrite(STDERR, "\nERROR en {$nombre}, sentencia #" . ($i + 1) . ":\n{$statement}\n\n{$e->getMessage()}\n");
            fwrite(STDERR, "La migración NO quedó registrada. Corrija el problema y vuelva a ejecutar este script.\n");
            exit(1);
        }
    }

    $pdo->prepare('INSERT INTO schema_migrations (migracion) VALUES (?)')->execute([$nombre]);
}

echo "Migraciones completadas.\n";
