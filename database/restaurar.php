<?php

declare(strict_types=1);

/**
 * Restaura un respaldo generado por database/respaldo.php (.sql.gz) en una base de datos.
 *
 * NUNCA se ejecuta automáticamente (ni desde el despliegue ni desde un cron). Pensado para:
 *   1. VERIFICAR un respaldo: restaurarlo en una base aparte y vacía, y comprobar que
 *      quedaron las mismas tablas y filas.
 *        php database/restaurar.php respaldo.sql.gz --base=sigebi_verificacion
 *   2. RECUPERAR producción tras un despliegue fallido (último recurso):
 *        php database/restaurar.php pre-deploy-....sql.gz --base=<base_de_produccion> --reemplazar
 *      Pide escribir a mano "RESTAURAR <base>" antes de tocar nada.
 *
 * Si la base destino ya tiene tablas, exige --reemplazar y la confirmación escrita; el
 * respaldo borra y recrea cada tabla que contiene (DROP TABLE IF EXISTS + CREATE TABLE).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;

Env::cargar();

$dbConfig = require __DIR__ . '/../config/database.php';

// Los argumentos se leen a mano: getopt() deja de leer opciones en el primer argumento
// posicional (la ruta del archivo), así que "--base=..." escrito después no se veía.
$archivo = '';
$base = '';
$reemplazar = false;
foreach (array_slice($argv, 1) as $argumento) {
    if (str_starts_with($argumento, '--base=')) {
        $base = substr($argumento, strlen('--base='));
    } elseif ($argumento === '--reemplazar') {
        $reemplazar = true;
    } elseif (!str_starts_with($argumento, '--') && $archivo === '') {
        $archivo = $argumento;
    }
}

if ($archivo === '' || $base === '') {
    fwrite(STDERR, "Uso: php database/restaurar.php <respaldo.sql.gz> --base=<nombre_base> [--reemplazar]\n");
    exit(1);
}

if (!is_file($archivo)) {
    fwrite(STDERR, "No existe el archivo: {$archivo}\n");
    exit(1);
}

if (!preg_match('/^[A-Za-z0-9_]+$/', $base)) {
    fwrite(STDERR, "Nombre de base no válido: {$base}\n");
    exit(1);
}

$pdo = new PDO(
    "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset={$dbConfig['charset']}",
    $dbConfig['username'],
    $dbConfig['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$base}`");

$tablasExistentes = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

if ($tablasExistentes !== []) {
    if (!$reemplazar) {
        fwrite(STDERR, "La base `{$base}` ya tiene " . count($tablasExistentes) . " tablas. "
            . "Para sobrescribirla agregue --reemplazar (y haga antes un respaldo de ella).\n");
        exit(1);
    }

    echo "ATENCIÓN: se van a REEMPLAZAR las tablas de la base `{$base}` ("
        . count($tablasExistentes) . " tablas) con el contenido de:\n  {$archivo}\n";
    echo "Escriba exactamente  RESTAURAR {$base}  para continuar: ";
    $respuesta = trim((string) fgets(STDIN));
    if ($respuesta !== "RESTAURAR {$base}") {
        echo "Cancelado. No se modificó nada.\n";
        exit(1);
    }
}

$lector = gzopen($archivo, 'rb');
if ($lector === false) {
    fwrite(STDERR, "No se pudo abrir {$archivo} (¿no es un .gz?).\n");
    exit(1);
}

/**
 * Acumula líneas hasta completar una sentencia. Una sentencia termina en ';' al final de
 * una línea, pero SOLO si ese ';' no está dentro de un texto entre comillas simples (los
 * datos pueden contener ';' y saltos de línea). Los textos vienen de PDO::quote(), que
 * escapa con barra invertida.
 */
$sentencia = '';
$dentroDeTexto = false;
$ejecutadas = 0;
$completo = false;
$inicio = microtime(true);

while (!gzeof($lector)) {
    $linea = gzgets($lector, 1 << 24);
    if ($linea === false) {
        break;
    }

    if (!$dentroDeTexto && $sentencia === '') {
        $recortada = trim($linea);
        if ($recortada === '-- FIN DEL RESPALDO') {
            $completo = true;
            continue;
        }
        if ($recortada === '' || str_starts_with($recortada, '--')) {
            continue;
        }
    }

    $largo = strlen($linea);
    for ($i = 0; $i < $largo; $i++) {
        $c = $linea[$i];
        if ($dentroDeTexto && $c === '\\') {
            $i++; // el carácter siguiente está escapado
            continue;
        }
        if ($c === "'") {
            $dentroDeTexto = !$dentroDeTexto;
        }
    }

    $sentencia .= $linea;

    if (!$dentroDeTexto && str_ends_with(rtrim($linea), ';')) {
        try {
            $pdo->exec($sentencia);
        } catch (PDOException $e) {
            fwrite(STDERR, "\nERROR ejecutando la sentencia #" . ($ejecutadas + 1) . ":\n"
                . mb_substr($sentencia, 0, 500) . "\n\n" . $e->getMessage() . "\n");
            exit(1);
        }
        $ejecutadas++;
        $sentencia = '';

        if ($ejecutadas % 5000 === 0) {
            echo "  {$ejecutadas} sentencias...\n";
        }
    }
}
gzclose($lector);

if (trim($sentencia) !== '') {
    fwrite(STDERR, "ADVERTENCIA: quedó una sentencia incompleta al final del archivo (no se ejecutó).\n");
}

if (!$completo) {
    fwrite(STDERR, "ADVERTENCIA: el respaldo no tiene la marca '-- FIN DEL RESPALDO' "
        . "(puede estar incompleto, o ser de una versión anterior de respaldo.php).\n");
}

$segundos = round(microtime(true) - $inicio, 1);
echo "Restauración terminada en `{$base}`: {$ejecutadas} sentencias en {$segundos} s.\n";
echo "Conteo de filas por tabla:\n";
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $tabla) {
    $filas = (int) $pdo->query("SELECT COUNT(*) FROM `{$tabla}`")->fetchColumn();
    echo sprintf("  %-40s %d\n", $tabla, $filas);
}

exit($completo ? 0 : 1);
