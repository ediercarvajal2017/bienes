<?php

declare(strict_types=1);

/**
 * Recrea desde cero la base de DEMOSTRACIÓN (datos ficticios): la borra, la crea, corre las
 * migraciones, el seed base y los datos de demostración (seeders/demo.php), y vacía la
 * carpeta de archivos de la demostración (fotos, sesiones).
 *
 * Normalmente se usa a través de demo.bat. A mano:
 *     DB_DATABASE=sigebi_demo STORAGE_PATH=storage/demo php database/herramientas/preparar_demo.php
 *
 * Salvaguardas: exige que DB_DATABASE contenga "demo" (y no "prod") y que STORAGE_PATH
 * contenga "demo". Nunca toca otra base ni otra carpeta.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Env;

Env::cargar();
$dbConfig = require __DIR__ . '/../../config/database.php';
$appConfig = require __DIR__ . '/../../config/app.php';
$base = (string) $dbConfig['database'];
$storage = (string) $appConfig['storage_path'];

if (!preg_match('/^[A-Za-z0-9_]+$/', $base) || !str_contains(strtolower($base), 'demo') || str_contains(strtolower($base), 'prod')) {
    fwrite(STDERR, "Negado: solo se recrea una base cuyo nombre contenga \"demo\" (actual: {$base}).\n");
    exit(1);
}
if (!str_contains(strtolower(str_replace('\\', '/', $storage)), 'demo')) {
    fwrite(STDERR, "Negado: STORAGE_PATH debe ser la carpeta de la demostración (actual: {$storage}).\n");
    exit(1);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=%s', $dbConfig['host'], $dbConfig['port'], $dbConfig['charset']),
    $dbConfig['username'],
    $dbConfig['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("DROP DATABASE IF EXISTS `{$base}`");
$pdo->exec("CREATE DATABASE `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
fwrite(STDERR, "Base {$base} recreada.\n");

// Carpeta de archivos de la demostración: se vacía (fotos generadas, miniaturas, sesiones).
$borrar = static function (string $dir) use (&$borrar): void {
    foreach (glob($dir . '/{,.}[!.,!..]*', GLOB_BRACE) ?: [] as $ruta) {
        is_dir($ruta) ? $borrar($ruta) : unlink($ruta);
    }
    if (is_dir($dir)) {
        rmdir($dir);
    }
};
$borrar($storage);
mkdir($storage . '/uploads', 0755, true);
fwrite(STDERR, "Carpeta {$storage} vaciada.\n");

// Cada paso en su propio proceso, con el mismo entorno (DB_DATABASE incluido).
foreach (['database/migrate.php', 'database/seeders/seed.php', 'database/seeders/demo.php'] as $script) {
    $salida = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/' . $script) . ' 2>&1', $salida, $codigo);
    if ($codigo !== 0) {
        fwrite(STDERR, "Falló {$script}:\n" . implode("\n", $salida) . "\n");
        exit(1);
    }
    fwrite(STDERR, "{$script}: listo.\n");
}
exit(0);
