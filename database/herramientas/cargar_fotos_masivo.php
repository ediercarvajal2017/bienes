<?php

declare(strict_types=1);

/**
 * Script de un solo uso: asigna masivamente la foto de los bienes ya
 * registrados en el sistema, a partir de una carpeta de imagenes donde cada
 * archivo se llama "{codigo_del_bien}.jpg" (o .jpeg/.png).
 *
 * Reutiliza el mismo guardado que usa el programa (App\Helpers\Uploader), asi
 * que el archivo queda con el nombre "{codigo}_{consecutivo}.ext" en la misma
 * carpeta de almacenamiento de siempre. NUNCA sobrescribe un bien que ya tiene
 * foto (para que repetir el script por error no borre una foto mas reciente).
 *
 * Uso:
 *   php database/herramientas/cargar_fotos_masivo.php carpeta_fotos tu_correo@ejemplo.com            (vista previa)
 *   php database/herramientas/cargar_fotos_masivo.php carpeta_fotos tu_correo@ejemplo.com --aplicar  (aplica de verdad)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Helpers\Uploader;
use App\Models\Bien;

Env::cargar();

$carpeta = $argv[1] ?? null;
$correo = $argv[2] ?? null;
$aplicar = in_array('--aplicar', $argv, true);

if ($carpeta === null || $correo === null) {
    echo "Uso: php database/herramientas/cargar_fotos_masivo.php carpeta_fotos tu_correo@ejemplo.com [--aplicar]\n";
    exit(1);
}

$carpeta = rtrim($carpeta, '/\\');

if (!is_dir($carpeta)) {
    echo "No se encontro la carpeta: {$carpeta}\n";
    exit(1);
}

$pdo = Database::connection();

$stmt = $pdo->prepare('SELECT institucion_id FROM usuarios WHERE email = ?');
$stmt->execute([$correo]);
$usuario = $stmt->fetch();

if (!$usuario) {
    echo "No se encontro ningun usuario con ese correo.\n";
    exit(1);
}

$institucionId = (int) $usuario['institucion_id'];

function normalizarTexto(string $texto): string
{
    $texto = str_replace("\xC2\xA0", ' ', $texto); // espacio "no separable" (NBSP)
    $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;
    return mb_strtoupper(trim($texto));
}

const EXTENSIONES_IMAGEN = ['jpg', 'jpeg', 'png'];

// Mapa codigo normalizado -> bien
$bienesPorCodigo = [];
$stmtBienes = $pdo->prepare('SELECT id, codigo_identificacion, foto_path FROM bienes WHERE institucion_id = ?');
$stmtBienes->execute([$institucionId]);
foreach ($stmtBienes->fetchAll() as $b) {
    $bienesPorCodigo[normalizarTexto($b['codigo_identificacion'])] = $b;
}

$archivos = array_filter(scandir($carpeta) ?: [], static fn (string $f) => !in_array($f, ['.', '..', '.DS_Store'], true));

$porAsignar = []; // ruta => ['codigo' => ..., 'bien_id' => ...]
$sinBien = [];
$yaTeniaFoto = [];
$formatoInvalido = [];
$codigosVistos = [];

foreach ($archivos as $archivo) {
    $rutaCompleta = $carpeta . '/' . $archivo;
    if (!is_file($rutaCompleta)) {
        continue;
    }

    $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
    $codigo = pathinfo($archivo, PATHINFO_FILENAME);

    if (!in_array($extension, EXTENSIONES_IMAGEN, true)) {
        $formatoInvalido[] = $archivo;
        continue;
    }

    if (isset($codigosVistos[normalizarTexto($codigo)])) {
        continue; // duplicado (mismo codigo con dos extensiones, etc.), se usa el primero
    }
    $codigosVistos[normalizarTexto($codigo)] = true;

    $bien = $bienesPorCodigo[normalizarTexto($codigo)] ?? null;
    if ($bien === null) {
        $sinBien[] = $archivo;
        continue;
    }

    if (!empty($bien['foto_path'])) {
        $yaTeniaFoto[] = "{$codigo} ({$archivo})";
        continue;
    }

    $porAsignar[$rutaCompleta] = ['codigo' => $codigo, 'bien_id' => (int) $bien['id']];
}

echo "=== Resumen ===\n";
echo "Fotos a asignar: " . count($porAsignar) . "\n";
echo "Archivos cuyo codigo no existe en el sistema: " . count($sinBien) . "\n";
foreach (array_slice($sinBien, 0, 20) as $item) {
    echo "  - {$item}\n";
}
if (count($sinBien) > 20) {
    echo "  ... y " . (count($sinBien) - 20) . " mas.\n";
}
echo "Bienes que ya tenian foto (no se tocan): " . count($yaTeniaFoto) . "\n";
foreach (array_slice($yaTeniaFoto, 0, 20) as $item) {
    echo "  - {$item}\n";
}
if (count($yaTeniaFoto) > 20) {
    echo "  ... y " . (count($yaTeniaFoto) - 20) . " mas.\n";
}
echo "Archivos con formato invalido (no jpg/jpeg/png): " . count($formatoInvalido) . "\n";
foreach ($formatoInvalido as $item) {
    echo "  - {$item}\n";
}

if (!$aplicar) {
    echo "\n>>> Vista previa unicamente. Nada se modifico. Revisa bien la lista.\n";
    echo ">>> Repite el comando agregando --aplicar al final para aplicar de verdad.\n";
    exit(0);
}

if (empty($porAsignar)) {
    echo "\n>>> No hay fotos que asignar.\n";
    exit(0);
}

$asignadas = 0;
$errores = [];

foreach ($porAsignar as $ruta => $datos) {
    try {
        $path = Uploader::guardarImagenDesdeRuta($ruta, 'fotos_bienes', $datos['codigo']);
        Bien::updateFoto($datos['bien_id'], $path);
        $asignadas++;
    } catch (\Throwable $e) {
        $errores[] = "{$datos['codigo']}: " . $e->getMessage();
    }
}

echo "\n>>> Listo: {$asignadas} foto(s) asignadas.\n";
if (!empty($errores)) {
    echo ">>> Errores en " . count($errores) . " archivo(s):\n";
    foreach ($errores as $error) {
        echo "  - {$error}\n";
    }
}
