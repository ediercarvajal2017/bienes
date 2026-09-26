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
 *
 * Opciones (las usa deploy-hostinger.sh antes de cada despliegue):
 *   --salida=/ruta/archivo.sql.gz  guarda el respaldo en esa ruta exacta
 *   --sin-correo                   no lo envía por correo aunque BACKUP_EMAIL exista
 *   --sin-limpieza                 no borra respaldos locales viejos
 *
 * Al terminar verifica que el .gz quedó completo (termina con la marca de fin) e
 * imprime una línea "RESPALDO=<ruta>". Si algo falla sale con código 1, para que el
 * despliegue se detenga antes de tocar la base de datos.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Services\MailService;

Env::cargar();

$config = require __DIR__ . '/../config/app.php';
$dbConfig = require __DIR__ . '/../config/database.php';

$opciones = getopt('', ['salida:', 'sin-correo', 'sin-limpieza']) ?: [];

$dirBackups = $config['storage_path'] . '/backups';
if (!is_dir($dirBackups)) {
    mkdir($dirBackups, 0775, true);
}

$pdo = Database::connection();
$fecha = date('Y-m-d_H-i-s');
$rutaComprimida = isset($opciones['salida']) && is_string($opciones['salida'])
    ? $opciones['salida']
    : $dirBackups . "/sigebi_{$fecha}.sql.gz";
$nombreArchivo = basename($rutaComprimida);

if (!is_dir(dirname($rutaComprimida))) {
    mkdir(dirname($rutaComprimida), 0775, true);
}

// Se comprime mientras se escribe (gzopen) en vez de armar todo el SQL en memoria y
// comprimirlo al final: con una base grande eso podía agotar la memoria del hosting.
$handle = gzopen($rutaComprimida, 'wb6');
if ($handle === false) {
    fwrite(STDERR, "No se pudo crear el archivo de respaldo en {$rutaComprimida}\n");
    exit(1);
}

$escribir = static function ($handle, string $texto): void {
    if (gzwrite($handle, $texto) === false) {
        fwrite(STDERR, "Error escribiendo el respaldo (¿disco lleno?).\n");
        exit(1);
    }
};

$escribir($handle, "-- Respaldo de '{$dbConfig['database']}' generado el " . date('Y-m-d H:i:s') . "\n");
$escribir($handle, "SET NAMES utf8mb4;\n");
$escribir($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

$tablas = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN);
$totalFilas = 0;

// Lectura sin búfer: las filas llegan de a una desde MySQL en lugar de cargar la tabla
// completa en memoria (auditoría puede tener cientos de miles de filas).
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

foreach ($tablas as $tabla) {
    $create = $pdo->query("SHOW CREATE TABLE `{$tabla}`")->fetch();
    $escribir($handle, "DROP TABLE IF EXISTS `{$tabla}`;\n");
    $escribir($handle, $create['Create Table'] . ";\n\n");

    $filas = $pdo->query("SELECT * FROM `{$tabla}`");
    foreach ($filas as $fila) {
        $columnas = '`' . implode('`, `', array_keys($fila)) . '`';
        $valores = implode(', ', array_map(
            static fn ($valor) => $valor === null ? 'NULL' : $pdo->quote((string) $valor),
            array_values($fila)
        ));
        $escribir($handle, "INSERT INTO `{$tabla}` ({$columnas}) VALUES ({$valores});\n");
        $totalFilas++;
    }
    $filas->closeCursor();
    $escribir($handle, "\n");
}

$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

$escribir($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
$escribir($handle, "-- FIN DEL RESPALDO\n");
gzclose($handle);

// Verificación: el .gz se puede leer hasta el final y termina con la marca de fin. Un
// respaldo cortado (disco lleno, proceso interrumpido) no sirve para restaurar.
$lector = gzopen($rutaComprimida, 'rb');
$ultimaLinea = '';
while ($lector !== false && !gzeof($lector)) {
    $linea = gzgets($lector, 1 << 20);
    if ($linea !== false && trim($linea) !== '') {
        $ultimaLinea = trim($linea);
    }
}
if ($lector !== false) {
    gzclose($lector);
}
if ($ultimaLinea !== '-- FIN DEL RESPALDO') {
    fwrite(STDERR, "ERROR: el respaldo {$rutaComprimida} quedó incompleto o dañado.\n");
    exit(1);
}

$pesoMb = round(filesize($rutaComprimida) / 1024 / 1024, 2);
echo "Respaldo creado y verificado: {$rutaComprimida} ({$pesoMb} MB, " . count($tablas) . " tablas, {$totalFilas} filas)\n";
echo "RESPALDO={$rutaComprimida}\n";

// Limpieza de respaldos locales viejos, para no llenar el disco del hosting —
// esto NO es la retención real del respaldo (esa la da la copia que llega por
// correo, si backup_email está configurado); es solo espacio en este servidor.
// Solo toca los respaldos diarios (sigebi_*.sql.gz), nunca los "pre-deploy".
if (!isset($opciones['sin-limpieza'])) {
    $retencionDias = $config['backup_retencion_dias'];
    $corte = time() - ($retencionDias * 86400);
    $borrados = 0;
    foreach (glob($dirBackups . '/sigebi_*.sql.gz') ?: [] as $archivo) {
        if ($archivo !== $rutaComprimida && filemtime($archivo) < $corte) {
            unlink($archivo);
            $borrados++;
        }
    }
    if ($borrados > 0) {
        echo "{$borrados} respaldo(s) local(es) de más de {$retencionDias} días eliminado(s).\n";
    }
}

if (isset($opciones['sin-correo'])) {
    exit(0);
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
        $nombreArchivo
    );
    echo "Respaldo enviado por correo a {$config['backup_email']}.\n";
} catch (\RuntimeException $e) {
    fwrite(STDERR, "El respaldo se generó pero no se pudo enviar por correo: " . $e->getMessage() . "\n");
    exit(1);
}
