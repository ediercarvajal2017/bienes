<?php

declare(strict_types=1);

/**
 * Archiva (exporta y saca de la tabla activa) los registros de auditoría anteriores
 * a RETENCION_DIAS. A propósito NO hay forma de eliminar un registro de auditoría
 * desde la pantalla /auditoria -eso rompería la garantía de "nadie puede borrar la
 * evidencia de lo que borró"-, así que este es el ÚNICO camino para que la tabla no
 * crezca indefinidamente, y nunca destruye nada: cada corrida deja un .json.gz
 * descargable en storage/archivo_auditoria/ (y, si BACKUP_EMAIL está configurado,
 * lo envía por correo, igual que respaldo.php) antes de borrar esas filas de la
 * tabla. Al terminar, deja un único registro nuevo en `auditoria` (acción
 * "archivar") dejando constancia de que el archivado ocurrió, sin repetir el
 * detalle de cada fila archivada -eso ya vive en el .json.gz-.
 *
 * Uso manual: php database/archivar_auditoria.php
 * Pensado para ejecutarse mensualmente vía un cron job de Hostinger (a diferencia
 * de purgar_papelera.php/respaldo.php, que sí conviene diarios) — con una
 * retención de años, correrlo a diario no aporta nada. No hace nada si no hay
 * registros más viejos que el período de retención.
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Models\Auditoria;
use App\Services\MailService;

Env::cargar();

const RETENCION_DIAS = 365 * 3; // 3 años

$pdo = Database::connection();
$corte = date('Y-m-d H:i:s', strtotime('-' . RETENCION_DIAS . ' days'));

$stmt = $pdo->prepare('SELECT * FROM auditoria WHERE created_at < ? ORDER BY created_at ASC');
$stmt->execute([$corte]);
$filas = $stmt->fetchAll();

if (empty($filas)) {
    echo "No hay registros de auditoría anteriores a {$corte}. Nada que archivar.\n";
    exit(0);
}

$config = require __DIR__ . '/../config/app.php';
$carpeta = $config['storage_path'] . '/archivo_auditoria';
if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
    fwrite(STDERR, "No se pudo preparar la carpeta de archivo ({$carpeta}).\n");
    exit(1);
}

$fecha = date('Y-m-d_H-i-s');
$nombreArchivo = "auditoria_{$fecha}.json.gz";
$rutaArchivo = $carpeta . '/' . $nombreArchivo;

$json = json_encode($filas, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fwrite(STDERR, "No se pudo serializar los registros a exportar.\n");
    exit(1);
}

if (file_put_contents($rutaArchivo, gzencode($json, 9)) === false) {
    fwrite(STDERR, "No se pudo escribir el archivo de exportación en {$rutaArchivo}.\n");
    exit(1);
}

// Recién ahora que el archivo ya existe en disco se borra de la tabla activa —
// si algo falla antes de este punto, no se pierde ni se borra nada.
$ids = array_column($filas, 'id');
$pdo->beginTransaction();
try {
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM auditoria WHERE id IN ({$marcadores})")->execute($ids);
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "El archivo ya se generó (nada se perdió), pero no se pudo purgar la tabla activa: " . $e->getMessage() . "\n");
    exit(1);
}

Auditoria::registrar(null, null, 'archivar', 'auditoria', 0, null, [
    'cantidad' => count($filas),
    'hasta' => $corte,
    'archivo' => $nombreArchivo,
]);

$pesoMb = round(filesize($rutaArchivo) / 1024 / 1024, 2);
echo 'Archivados ' . count($filas) . " registro(s) de auditoría anteriores a {$corte}.\n";
echo "Guardados en: {$rutaArchivo} ({$pesoMb} MB)\n";

if ($config['backup_email'] === '') {
    echo "BACKUP_EMAIL no está configurado: el archivo solo quedó guardado en este servidor, sin copia externa.\n";
    exit(0);
}

try {
    MailService::enviarConAdjunto(
        $config['backup_email'],
        'SIGEBI',
        "Archivado de auditoría SIGEBI - {$fecha}",
        '<p>Archivado automático de registros de auditoría antiguos.</p><p>Registros: ' . count($filas) . " — Anteriores a: {$corte} — Tamaño: {$pesoMb} MB.</p>",
        $rutaArchivo,
        $nombreArchivo
    );
    echo "Archivo enviado por correo a {$config['backup_email']}.\n";
} catch (\RuntimeException $e) {
    fwrite(STDERR, "El archivado se completó pero no se pudo enviar por correo: " . $e->getMessage() . "\n");
    exit(1);
}
