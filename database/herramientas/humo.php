<?php

declare(strict_types=1);

/**
 * Prueba de humo del despliegue: comprueba que no se perdió información y que el sitio
 * responde. Solo lee; nunca modifica la base de datos.
 *
 *   Antes de migrar:   php database/herramientas/humo.php --guardar=/ruta/conteo-antes.json
 *   Después de migrar: php database/herramientas/humo.php --comparar=/ruta/conteo-antes.json [--url=https://sigebi.dominio]
 *
 * Falla (código 1) si alguna tabla quedó con MENOS filas que antes, si desapareció una
 * tabla, si falta el directorio de archivos subidos o si la URL no responde bien.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::cargar();

$config = require __DIR__ . '/../../config/app.php';
date_default_timezone_set($config['timezone']);
$opciones = getopt('', ['guardar:', 'comparar:', 'url:']) ?: [];

// Tablas que pueden decrecer legítimamente entre un despliegue y otro (tokens vencidos,
// intentos de login, etc.): se informan pero no hacen fallar la prueba.
const TABLAS_VOLATILES = ['password_resets', 'intentos_login', 'dispositivos_confiables'];

$pdo = Database::connection();

$conteo = [];
foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $tabla) {
    $conteo[$tabla] = (int) $pdo->query("SELECT COUNT(*) FROM `{$tabla}`")->fetchColumn();
}
ksort($conteo);

$uploads = $config['storage_path'] . '/uploads';
$archivos = ['cantidad' => 0, 'bytes' => 0];
if (is_dir($uploads)) {
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS));
    foreach ($iterador as $f) {
        if ($f->isFile()) {
            $archivos['cantidad']++;
            $archivos['bytes'] += $f->getSize();
        }
    }
}

$fallas = [];

if (isset($opciones['guardar']) && is_string($opciones['guardar'])) {
    $datos = ['fecha' => date('c'), 'tablas' => $conteo, 'archivos' => $archivos];
    file_put_contents($opciones['guardar'], json_encode($datos, JSON_PRETTY_PRINT));
    echo "Conteo guardado en {$opciones['guardar']}: " . count($conteo) . " tablas, "
        . array_sum($conteo) . " filas, {$archivos['cantidad']} archivos subidos.\n";
}

if (isset($opciones['comparar']) && is_string($opciones['comparar'])) {
    $antes = json_decode((string) @file_get_contents($opciones['comparar']), true);
    if (!is_array($antes) || !isset($antes['tablas'])) {
        fwrite(STDERR, "No se pudo leer el conteo previo: {$opciones['comparar']}\n");
        exit(1);
    }

    echo sprintf("%-40s %10s %10s\n", 'Tabla', 'Antes', 'Ahora');
    foreach ($antes['tablas'] as $tabla => $filasAntes) {
        $filasAhora = $conteo[$tabla] ?? null;
        $marca = '';
        if ($filasAhora === null) {
            $marca = '  <-- TABLA DESAPARECIÓ';
            $fallas[] = "La tabla {$tabla} ya no existe.";
        } elseif ($filasAhora < $filasAntes) {
            if (in_array($tabla, TABLAS_VOLATILES, true)) {
                $marca = '  (volátil, se permite)';
            } else {
                $marca = '  <-- MENOS FILAS';
                $fallas[] = "La tabla {$tabla} bajó de {$filasAntes} a {$filasAhora} filas.";
            }
        }
        echo sprintf("%-40s %10d %10s%s\n", $tabla, $filasAntes, $filasAhora ?? '-', $marca);
    }
    foreach (array_diff_key($conteo, $antes['tablas']) as $tabla => $filas) {
        echo sprintf("%-40s %10s %10d  (nueva)\n", $tabla, '-', $filas);
    }

    $archivosAntes = $antes['archivos']['cantidad'] ?? 0;
    echo "\nArchivos subidos: antes {$archivosAntes}, ahora {$archivos['cantidad']}\n";
    if ($archivos['cantidad'] < $archivosAntes) {
        $fallas[] = "Hay menos archivos subidos que antes ({$archivosAntes} → {$archivos['cantidad']}). Revise STORAGE_PATH.";
    }
}

if (!is_dir($uploads)) {
    $fallas[] = "No existe el directorio de archivos subidos: {$uploads} (revise STORAGE_PATH).";
}

if (isset($opciones['url']) && is_string($opciones['url'])) {
    foreach (['/login' => 200] as $ruta => $esperado) {
        $url = rtrim($opciones['url'], '/') . $ruta;
        $contexto = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 20, 'follow_location' => 0]]);
        $cuerpo = @file_get_contents($url, false, $contexto);
        $estado = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s?/', $http_response_header[0], $m) ? (int) $m[1] : 0;
        echo "HTTP {$url} → {$estado}\n";
        if ($estado !== $esperado || $cuerpo === false || !str_contains($cuerpo, 'SIGEBI')) {
            $fallas[] = "{$url} respondió {$estado} (se esperaba {$esperado} con la página de SIGEBI).";
        }
    }
}

if ($fallas !== []) {
    fwrite(STDERR, "\nPRUEBA DE HUMO FALLIDA:\n  - " . implode("\n  - ", $fallas) . "\n");
    exit(1);
}

echo "\nPrueba de humo OK.\n";
