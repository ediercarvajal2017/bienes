<?php

declare(strict_types=1);

/**
 * Corrección: horas guardadas por la base en UTC (5 horas adelantadas respecto a Colombia).
 *
 * El servidor de base de datos de Hostinger está en UTC y la app en America/Bogota. Las
 * columnas DATETIME que llenaba la base con NOW() (último ingreso, "resuelta el", "eliminado
 * el", aceptación de la política, dispositivos de confianza...) quedaron 5 horas adelantadas.
 * Desde que se activa DB_ZONA_HORARIA=-05:00 (config/database.php) la base escribe en hora de
 * Colombia; este script corrige lo guardado ANTES, restando 5 horas.
 *
 * Las columnas TIMESTAMP (auditoría, creado/actualizado, QR) NO se tocan: la base las guarda en
 * UTC internamente y, con la zona activa, ya se leen en hora de Colombia solas. Tampoco se
 * tocan las fechas que escribe PHP (ya están en hora de Colombia), como el vencimiento del
 * enlace de "olvidé mi contraseña" o el cierre de una verificación.
 *
 *   php database/correcciones/horas_a_colombia.php            (SIMULACIÓN: solo muestra)
 *   php database/correcciones/horas_a_colombia.php --aplicar  (aplica, en una transacción)
 *
 * Se aplica UNA sola vez, en una ventana de mantenimiento, ANTES de poner DB_ZONA_HORARIA en
 * el .env (ver docs/despliegue.md, "Hora de Colombia"). Se niega a correr si la zona ya está
 * activa o si ya se aplicó (marca en storage/backups/horas-a-colombia-aplicada.json): restar
 * dos veces dañaría las horas.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Models\Auditoria;

Env::cargar();
$config = require __DIR__ . '/../../config/app.php';
$dbConfig = require __DIR__ . '/../../config/database.php';
date_default_timezone_set($config['timezone']);
$aplicar = in_array('--aplicar', $argv, true);

$marca = $config['storage_path'] . '/backups/horas-a-colombia-aplicada.json';
if (is_file($marca)) {
    fwrite(STDERR, "La corrección ya se aplicó (ver {$marca}). No se hace nada.\n");
    exit(1);
}
if ((string) ($dbConfig['zona_horaria'] ?? '') !== '') {
    fwrite(STDERR, "DB_ZONA_HORARIA ya está activa: la corrección debe correr ANTES de activarla. No se hace nada.\n");
    exit(1);
}

/** Tabla => columnas DATETIME que llena la base con NOW() o con su valor por defecto. */
const COLUMNAS = [
    'usuarios' => ['ultimo_login', 'totp_activado_en', 'politica_aceptada_en', 'eliminado_en'],
    'bajas_bienes' => ['fecha_reporte', 'resuelta_en'],
    'solicitudes_reintegro' => ['resuelta_en'],
    'hallazgos_verificacion' => ['resuelto_en'],
    'verificaciones_bienes' => ['revisada_en'],
    'espacios' => ['eliminado_en'],
    'categorias_bienes' => ['eliminado_en'],
    'cargos' => ['eliminado_en'],
    'cartera_envios' => ['eliminado_en'],
    'facturas_administrativas' => ['eliminado_en'],
    'formatos_plaqueteo' => ['eliminado_en'],
    'formatos_reintegro' => ['eliminado_en'],
    'dispositivos_confiables' => ['creado_en', 'ultimo_uso_en', 'expira_en', 'revocado_en'],
    'usuario_codigos_recuperacion' => ['creado_en', 'usado_en'],
    'intentos_acceso' => ['creado_en'],
];
/** Tablas con "updated_at ON UPDATE": al corregir no debe cambiar su fecha de actualización. */
const CON_UPDATED_AT = ['usuarios', 'verificaciones_bienes'];

$pdo = Database::connection();
$existentes = $pdo->query(
    "SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = 'datetime'"
)->fetchAll(PDO::FETCH_COLUMN);

$conteos = [];
foreach (COLUMNAS as $tabla => $columnas) {
    foreach ($columnas as $columna) {
        if (!in_array("{$tabla}.{$columna}", $existentes, true)) {
            continue; // la columna no existe en esta base (versión distinta): se omite
        }
        $conteos["{$tabla}.{$columna}"] = (int) $pdo->query("SELECT COUNT(*) FROM `{$tabla}` WHERE `{$columna}` IS NOT NULL")->fetchColumn();
    }
}

echo 'Horas a corregir (se restan 5 horas)' . ($aplicar ? ":\n" : " — SIMULACIÓN; use --aplicar para corregir:\n");
foreach ($conteos as $columna => $n) {
    printf("  %-50s %6d valor(es)\n", $columna, $n);
}
printf("  %-50s %6d\n", 'TOTAL', array_sum($conteos));

// Comprobación: el último ingreso de cada usuario debe coincidir con su último "login_ok" de
// la auditoría (TIMESTAMP, que la base sí guarda bien) una vez restadas las 5 horas.
echo "\nComprobación con la auditoría (último ingreso corregido vs. último inicio de sesión registrado):\n";
$muestras = $pdo->query(
    "SELECT u.email, u.ultimo_login, u.ultimo_login - INTERVAL 5 HOUR AS corregido,
            UNIX_TIMESTAMP(MAX(a.created_at)) AS epoch_auditoria
       FROM usuarios u JOIN auditoria a ON a.usuario_id = u.id AND a.accion = 'login_ok'
      WHERE u.ultimo_login IS NOT NULL
      GROUP BY u.id ORDER BY u.ultimo_login DESC LIMIT 5"
)->fetchAll();
foreach ($muestras as $m) {
    // UNIX_TIMESTAMP da el instante exacto, sin importar la zona de la sesión; se muestra en hora de Colombia.
    $segunAuditoria = date('Y-m-d H:i:s', (int) $m['epoch_auditoria']);
    $diferencia = abs(strtotime((string) $m['corregido']) - (int) $m['epoch_auditoria']);
    printf("  %-32s guardado %s → corregido %s | auditoría %s %s\n", $m['email'], $m['ultimo_login'], $m['corregido'],
        $segunAuditoria, $diferencia <= 120 ? 'coincide' : '(no coincide)');
}

if (!$aplicar) {
    exit(0);
}

// Copia de los valores originales (tabla, id, columna, valor) antes de tocar nada.
$originales = [];
foreach ($conteos as $columna => $n) {
    [$tabla, $col] = explode('.', $columna);
    foreach ($pdo->query("SELECT id, `{$col}` AS valor FROM `{$tabla}` WHERE `{$col}` IS NOT NULL")->fetchAll() as $f) {
        $originales[] = ['tabla' => $tabla, 'id' => (int) $f['id'], 'columna' => $col, 'valor' => $f['valor']];
    }
}
$copia = $config['storage_path'] . '/backups/horas-a-colombia-originales-' . date('Ymd-His') . '.json';
if (file_put_contents($copia, (string) json_encode($originales, JSON_PRETTY_PRINT)) === false) {
    fwrite(STDERR, "No se pudo guardar la copia de los valores originales: no se aplicó nada.\n");
    exit(1);
}
chmod($copia, 0600);

$corregidos = Database::transaccion(function (PDO $pdo) use ($conteos): int {
    $total = 0;
    foreach (array_keys($conteos) as $columna) {
        [$tabla, $col] = explode('.', $columna);
        $extra = in_array($tabla, CON_UPDATED_AT, true) ? ', updated_at = updated_at' : '';
        $total += (int) $pdo->exec("UPDATE `{$tabla}` SET `{$col}` = `{$col}` - INTERVAL 5 HOUR{$extra} WHERE `{$col}` IS NOT NULL");
    }
    Auditoria::registrar(null, null, 'correccion_datos', 'sistema', 0, null, [
        'motivo' => 'Horas guardadas por la base en UTC pasadas a hora de Colombia (-5 horas)',
        'valores' => $total,
    ] + $conteos);

    return $total;
});

file_put_contents($marca, (string) json_encode([
    'aplicada_en' => date('Y-m-d H:i:s'),
    'valores' => $corregidos,
    'copia' => $copia,
], JSON_PRETTY_PRINT));

echo "\nListo: {$corregidos} valor(es) corregido(s). Originales en {$copia}\n";
echo "Ahora ponga DB_ZONA_HORARIA=-05:00 en el .env y quite el modo mantenimiento.\n";
