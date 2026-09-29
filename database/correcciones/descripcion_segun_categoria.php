<?php

declare(strict_types=1);

/**
 * Corrección: la descripción de cada bien en mayúscula o minúscula según su categoría (la
 * regla que ya aplica MIA al crear y editar, App\Models\Bien::descripcionSegunCategoria):
 * "Sin cartera" en minúscula; cualquier otra categoría en mayúscula. Los bienes sin
 * categoría no se tocan. Solo cambia mayúsculas/minúsculas: ninguna letra se agrega ni se quita.
 *
 *   php database/correcciones/descripcion_segun_categoria.php            (SIMULACIÓN: solo muestra)
 *   php database/correcciones/descripcion_segun_categoria.php --aplicar  (aplica, en una transacción)
 *
 * Al aplicar:
 *  - guarda antes la descripción original de cada bien cambiado en
 *    storage/backups/correccion-descripciones-AAAAMMDD-HHMMSS.json (para revertir exacto);
 *  - no cambia la fecha de "actualizado" de los bienes;
 *  - deja un registro "correccion_datos" en la auditoría por institución, con el total.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Models\Auditoria;
use App\Models\Categoria;

Env::cargar();
$config = require __DIR__ . '/../../config/app.php';
date_default_timezone_set($config['timezone']);
$aplicar = in_array('--aplicar', $argv, true);
$pdo = Database::connection();

$bienes = $pdo->query(
    'SELECT b.id, b.institucion_id, b.codigo_identificacion, b.descripcion, c.nombre AS categoria, i.nombre AS institucion
       FROM bienes b
       JOIN categorias_bienes c ON c.id = b.categoria_id
       JOIN instituciones i ON i.id = b.institucion_id
      ORDER BY i.nombre, c.nombre, b.codigo_identificacion'
)->fetchAll();

$cambios = [];
$resumen = [];
foreach ($bienes as $b) {
    $sinCartera = $b['categoria'] === Categoria::NOMBRE_CATEGORIA_PROTEGIDA;
    $nueva = $sinCartera ? mb_strtolower((string) $b['descripcion'], 'UTF-8') : mb_strtoupper((string) $b['descripcion'], 'UTF-8');
    $clave = $b['institucion'] . ' · ' . $b['categoria'] . ($sinCartera ? ' (a minúscula)' : ' (a mayúscula)');
    $resumen[$clave] ??= ['revisados' => 0, 'cambian' => 0];
    $resumen[$clave]['revisados']++;
    if ($nueva !== $b['descripcion']) {
        $resumen[$clave]['cambian']++;
        $cambios[] = $b + ['nueva' => $nueva];
    }
}

echo count($bienes) . ' bienes con categoría revisados; ' . count($cambios) . ' cambian'
    . ($aplicar ? ":\n" : " (SIMULACIÓN; use --aplicar para corregir):\n");
foreach ($resumen as $clave => $r) {
    printf("  %-60s %5d revisados, %5d cambian\n", $clave, $r['revisados'], $r['cambian']);
}
echo "\nEjemplos:\n";
foreach (array_slice($cambios, 0, 12) as $c) {
    echo "  {$c['codigo_identificacion']} [{$c['categoria']}]: \"{$c['descripcion']}\" → \"{$c['nueva']}\"\n";
}
if (!$aplicar || $cambios === []) {
    exit(0);
}

// Copia exacta de lo que se va a cambiar, antes de tocar nada.
$carpeta = $config['storage_path'] . '/backups';
if (!is_dir($carpeta) && !mkdir($carpeta, 0700, true) && !is_dir($carpeta)) {
    fwrite(STDERR, "No se pudo crear {$carpeta}: no se aplicó nada.\n");
    exit(1);
}
$copia = $carpeta . '/correccion-descripciones-' . date('Ymd-His') . '.json';
$contenido = json_encode(array_map(static fn (array $c): array => [
    'id' => (int) $c['id'], 'codigo' => $c['codigo_identificacion'], 'descripcion_original' => $c['descripcion'],
], $cambios), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($contenido === false || file_put_contents($copia, $contenido) === false) {
    fwrite(STDERR, "No se pudo guardar la copia de las descripciones: no se aplicó nada.\n");
    exit(1);
}
chmod($copia, 0600);

$aplicados = Database::transaccion(function (PDO $pdo) use ($cambios): int {
    // Solo si la descripción sigue igual que al simular (nadie la editó entretanto), y sin
    // mover la fecha de "actualizado" del bien.
    $actualizar = $pdo->prepare(
        'UPDATE bienes SET descripcion = ?, updated_at = updated_at WHERE id = ? AND descripcion = BINARY ?'
    );
    $porInstitucion = [];
    $total = 0;
    foreach ($cambios as $c) {
        $actualizar->execute([$c['nueva'], (int) $c['id'], $c['descripcion']]);
        if ($actualizar->rowCount() === 1) {
            $porInstitucion[(int) $c['institucion_id']] = ($porInstitucion[(int) $c['institucion_id']] ?? 0) + 1;
            $total++;
        }
    }
    foreach ($porInstitucion as $institucionId => $n) {
        Auditoria::registrar(null, $institucionId, 'correccion_datos', 'institucion', $institucionId, null, [
            'bienes' => $n,
            'motivo' => 'Descripción en minúscula ("Sin cartera") o en mayúscula (demás categorías)',
        ]);
    }

    return $total;
});

echo "\nListo: {$aplicados} descripción(es) corregida(s). Originales guardadas en {$copia}\n";
