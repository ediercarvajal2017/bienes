<?php

declare(strict_types=1);

/**
 * Script de un solo uso: asigna masivamente la categoria de los bienes ya
 * registrados en el sistema, a partir de un Excel (columna A = Codigo,
 * columna B = Categoria, fila 1 = encabezado, datos desde fila 2).
 *
 * Las categorias deben existir previamente en /categorias. Este script NO
 * crea categorias nuevas, solo actualiza bienes.categoria_id.
 *
 * Uso:
 *   php database/herramientas/asignar_categorias_masivo.php ruta.xlsx tu_correo@ejemplo.com            (vista previa)
 *   php database/herramientas/asignar_categorias_masivo.php ruta.xlsx tu_correo@ejemplo.com --aplicar  (aplica de verdad)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use PhpOffice\PhpSpreadsheet\IOFactory;

Env::cargar();

$rutaExcel = $argv[1] ?? null;
$correo = $argv[2] ?? null;
$aplicar = in_array('--aplicar', $argv, true);

if ($rutaExcel === null || $correo === null) {
    echo "Uso: php database/herramientas/asignar_categorias_masivo.php ruta.xlsx tu_correo@ejemplo.com [--aplicar]\n";
    exit(1);
}

if (!is_file($rutaExcel)) {
    echo "No se encontro el archivo: {$rutaExcel}\n";
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

// Mapa codigo normalizado -> bien
$bienesPorCodigo = [];
$stmtBienes = $pdo->prepare('SELECT id, codigo_identificacion, categoria_id FROM bienes WHERE institucion_id = ?');
$stmtBienes->execute([$institucionId]);
foreach ($stmtBienes->fetchAll() as $b) {
    $bienesPorCodigo[normalizarTexto($b['codigo_identificacion'])] = $b;
}

// Mapa categoria normalizada -> id, solo de esta institucion (categorias_bienes ya esta
// aislada por institucion; antes esto era una consulta global y podia mezclar categorias
// de otro colegio con el mismo nombre).
$categoriasPorNombre = [];
$stmtCategorias = $pdo->prepare('SELECT id, nombre FROM categorias_bienes WHERE institucion_id = ?');
$stmtCategorias->execute([$institucionId]);
foreach ($stmtCategorias->fetchAll() as $c) {
    $categoriasPorNombre[normalizarTexto($c['nombre'])] = (int) $c['id'];
}

$sheet = IOFactory::load($rutaExcel)->getActiveSheet();
$ultimaFila = $sheet->getHighestDataRow();

$actualizaciones = []; // bien_id => ['codigo' => ..., 'categoria_id' => ..., 'categoria_nombre' => ...]
$bienesNoEncontrados = [];
$categoriasNoEncontradas = [];
$sinCambios = 0;
$codigosVistos = [];

for ($fila = 2; $fila <= $ultimaFila; $fila++) {
    $codigo = trim((string) $sheet->getCell("A{$fila}")->getValue());
    $categoriaNombre = trim((string) $sheet->getCell("B{$fila}")->getValue());

    if ($codigo === '' && $categoriaNombre === '') {
        continue;
    }

    if (isset($codigosVistos[$codigo])) {
        continue; // duplicado dentro del mismo excel, se cuenta una sola vez
    }
    $codigosVistos[$codigo] = true;

    $bien = $bienesPorCodigo[normalizarTexto($codigo)] ?? null;
    if ($bien === null) {
        $bienesNoEncontrados[] = "{$codigo} (fila {$fila})";
        continue;
    }

    $categoriaId = $categoriasPorNombre[normalizarTexto($categoriaNombre)] ?? null;
    if ($categoriaId === null) {
        $categoriasNoEncontradas[] = "{$categoriaNombre} (fila {$fila}, codigo {$codigo})";
        continue;
    }

    if ((int) $bien['categoria_id'] === $categoriaId) {
        $sinCambios++;
        continue;
    }

    $actualizaciones[(int) $bien['id']] = [
        'codigo' => $codigo,
        'categoria_id' => $categoriaId,
        'categoria_nombre' => $categoriaNombre,
    ];
}

echo "=== Resumen ===\n";
echo "Bienes a actualizar: " . count($actualizaciones) . "\n";
echo "Bienes que ya tenian esa categoria (sin cambios): {$sinCambios}\n";
echo "Codigos del Excel que no existen en el sistema: " . count($bienesNoEncontrados) . "\n";
foreach (array_slice($bienesNoEncontrados, 0, 20) as $item) {
    echo "  - {$item}\n";
}
if (count($bienesNoEncontrados) > 20) {
    echo "  ... y " . (count($bienesNoEncontrados) - 20) . " mas.\n";
}
echo "Categorias del Excel que no existen en /categorias: " . count($categoriasNoEncontradas) . "\n";
foreach (array_slice($categoriasNoEncontradas, 0, 20) as $item) {
    echo "  - {$item}\n";
}
if (count($categoriasNoEncontradas) > 20) {
    echo "  ... y " . (count($categoriasNoEncontradas) - 20) . " mas.\n";
}

if (!$aplicar) {
    echo "\n>>> Vista previa unicamente. Nada se modifico. Revisa bien la lista.\n";
    echo ">>> Repite el comando agregando --aplicar al final para aplicar de verdad.\n";
    exit(0);
}

if (empty($actualizaciones)) {
    echo "\n>>> No hay bienes que actualizar.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $stmtUpdate = $pdo->prepare('UPDATE bienes SET categoria_id = ? WHERE id = ?');

    foreach ($actualizaciones as $bienId => $datos) {
        $stmtUpdate->execute([$datos['categoria_id'], $bienId]);
    }

    $pdo->commit();
    echo "\n>>> Listo: " . count($actualizaciones) . " bien(es) actualizados con su nueva categoria.\n";
} catch (\Throwable $e) {
    $pdo->rollBack();
    echo "\n>>> ERROR, no se aplico nada (se revirtio todo): " . $e->getMessage() . "\n";
    exit(1);
}
