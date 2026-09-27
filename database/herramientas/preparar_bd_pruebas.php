<?php

declare(strict_types=1);

/**
 * Recrea desde cero la base de datos DESECHABLE de la suite de pruebas: la borra, la crea,
 * corre todas las migraciones, el seed base y los datos de prueba (seeders/pruebas.php).
 * Imprime al final el JSON de identificadores que genera pruebas.php.
 *
 * La usa tests/global-setup.js antes de cada corrida de Playwright. Se puede correr a mano:
 *     DB_DATABASE=sigebi_test php database/herramientas/preparar_bd_pruebas.php
 *
 * Salvaguardas: exige que DB_DATABASE contenga "test" y no contenga "prod". Nunca toca
 * otra base.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Env;

Env::cargar();
$dbConfig = require __DIR__ . '/../../config/database.php';
$base = (string) $dbConfig['database'];

if (!preg_match('/^[A-Za-z0-9_]+$/', $base) || !str_contains(strtolower($base), 'test') || str_contains(strtolower($base), 'prod')) {
    fwrite(STDERR, "Negado: solo se recrea una base cuyo nombre contenga \"test\" (actual: {$base}).\n");
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

// Cada paso en su propio proceso, con el mismo entorno (DB_DATABASE incluido).
foreach (['database/migrate.php', 'database/seeders/seed.php'] as $script) {
    $salida = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/' . $script) . ' 2>&1', $salida, $codigo);
    if ($codigo !== 0) {
        fwrite(STDERR, "Falló {$script}:\n" . implode("\n", $salida) . "\n");
        exit(1);
    }
    fwrite(STDERR, "{$script}: listo.\n");
}

passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/seeders/pruebas.php'), $codigo);
exit($codigo);
