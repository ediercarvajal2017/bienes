<?php

declare(strict_types=1);

/**
 * Genera un respaldo completo de la base de datos (estructura + datos de cada
 * tabla, como sentencias SQL) en storage/backups/, comprimido en .gz. Escrito en
 * PHP puro (sin depender del binario mysqldump) porque el hosting compartido no
 * siempre permite ejecutar comandos de sistema desde PHP.
 *
 * Si `backup_email` está configurado (ver config/app.php / variable BACKUP_EMAIL
 * en el .env), además envía el respaldo por correo como adjunto — es la única
 * copia que queda fuera del propio servidor, así que sin esto configurado el
 * respaldo no protege contra una falla del servidor o del hosting en sí.
 *
 * Uso manual: php database/respaldo.php
 * Pensado para ejecutarse a diario vía un cron job de Hostinger (ver
 * instrucciones de despliegue).
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Services\MailService;

Env::cargar();

$config = require __DIR__ . '/../config/app.php';
$dbConfig = require __DIR__ . '/../config/database.php';

$dirBackups = $config['storage_path'] . '/backups';
if (!is_dir($dirBackups)) {
    mkdir($dirBackups, 0775, true);
}

$pdo = Database::connection();
$fecha = date('Y-m-d_H-i-s');
$nombreArchivo = "sigebi_{$fecha}.sql";
$rutaSql = $dirBackups . '/' . $nombreArchivo;
$rutaComprimida = $rutaSql . '.gz';

$handle = fopen($rutaSql, 'w');
if ($handle === false) {
    fwrite(STDERR, "No se pudo crear el archivo de respaldo en {$rutaSql}\n");
    exit(1);
}

fwrite($handle, "-- Respaldo de '{$dbConfig['database']}' generado el " . date('Y-m-d H:i:s') . "\n");
fwrite($handle, "SET NAMES utf8mb4;\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

$tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$totalFilas = 0;

foreach ($tablas as $tabla) {
    $create = $pdo->query("SHOW CREATE TABLE `{$tabla}`")->fetch();
    fwrite($handle, "DROP TABLE IF EXISTS `{$tabla}`;\n");
    fwrite($handle, $create['Create Table'] . ";\n\n");

    $filas = $pdo->query("SELECT * FROM `{$tabla}`");
    foreach ($filas as $fila) {
        $columnas = '`' . implode('`, `', array_keys($fila)) . '`';
        $valores = implode(', ', array_map(
            static fn ($valor) => $valor === null ? 'NULL' : $pdo->quote((string) $valor),
            array_values($fila)
        ));
        fwrite($handle, "INSERT INTO `{$tabla}` ({$columnas}) VALUES ({$valores});\n");
        $totalFilas++;
    }
    fwrite($handle, "\n");
}

fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($handle);

// Comprimir y borrar el .sql sin comprimir: el respaldo real es el .gz.
$sql = file_get_contents($rutaSql);
file_put_contents($rutaComprimida, gzencode($sql, 9));
unlink($rutaSql);

$pesoMb = round(filesize($rutaComprimida) / 1024 / 1024, 2);
echo "Respaldo creado: {$rutaComprimida} ({$pesoMb} MB, " . count($tablas) . " tablas, {$totalFilas} filas)\n";

// Limpieza de respaldos locales viejos, para no llenar el disco del hosting —
// esto NO es la retención real del respaldo (esa la da la copia que llega por
// correo, si backup_email está configurado); es solo espacio en este servidor.
$retencionDias = $config['backup_retencion_dias'];
$corte = time() - ($retencionDias * 86400);
$borrados = 0;
foreach (glob($dirBackups . '/*.sql.gz') as $archivo) {
    if (filemtime($archivo) < $corte) {
        unlink($archivo);
        $borrados++;
    }
}
if ($borrados > 0) {
    echo "{$borrados} respaldo(s) local(es) de más de {$retencionDias} días eliminado(s).\n";
}

if ($config['backup_email'] === '') {
    echo "BACKUP_EMAIL no está configurado: el respaldo solo quedó guardado en este servidor, sin copia externa.\n";
    exit(0);
}

try {
    MailService::enviarConAdjunto(
        $config['backup_email'],
        'SIGEBI',
        "Respaldo SIGEBI - {$fecha}",
        "<p>Respaldo automático de la base de datos de SIGEBI.</p><p>Tablas: " . count($tablas) . " — Filas: {$totalFilas} — Tamaño: {$pesoMb} MB.</p>",
        $rutaComprimida,
        $nombreArchivo . '.gz'
    );
    echo "Respaldo enviado por correo a {$config['backup_email']}.\n";
} catch (\RuntimeException $e) {
    fwrite(STDERR, "El respaldo se generó pero no se pudo enviar por correo: " . $e->getMessage() . "\n");
    exit(1);
}
