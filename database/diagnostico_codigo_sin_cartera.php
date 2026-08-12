<?php

declare(strict_types=1);

/**
 * Diagnóstico (SOLO LECTURA, no modifica nada) para el código automático de la
 * categoría "Sin cartera": reporta exactamente lo que ve el servidor, para saber por
 * qué la sugerencia no está trayendo el consecutivo esperado.
 *
 * Revisa las tres cosas que pueden hacer fallar la función en silencio:
 *   1. Que el nombre de la categoría no sea EXACTAMENTE "Sin cartera" (mayúsculas,
 *      espacios invisibles, tildes distintas) — la comparación es exacta.
 *   2. Que los bienes de esa categoría tengan códigos que no son de 10 dígitos
 *      (los códigos heredados largos/cortos no cuentan para el consecutivo).
 *   3. Qué código está devolviendo hoy el cálculo real, institución por institución.
 *
 * Uso: php database/diagnostico_codigo_sin_cartera.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Models\Bien;
use App\Models\Categoria;

Env::cargar();

$pdo = Database::connection();

echo "=== 1. Nombre exacto de cada categoría (buscando parecidas a 'cartera') ===\n";
echo "Si el nombre no es exactamente \"Sin cartera\", la función NO se activa.\n\n";

$stmt = $pdo->query(
    "SELECT c.id, c.institucion_id, i.nombre AS institucion, c.nombre, c.activo, c.eliminado_en,
            LENGTH(c.nombre) AS bytes, CHAR_LENGTH(c.nombre) AS caracteres, HEX(c.nombre) AS hex
     FROM categorias_bienes c
     JOIN instituciones i ON i.id = c.institucion_id
     WHERE c.nombre LIKE '%cartera%'
     ORDER BY c.institucion_id"
);

$esperado = Categoria::NOMBRE_CATEGORIA_PROTEGIDA;
foreach ($stmt->fetchAll() as $c) {
    $coincide = $c['nombre'] === $esperado ? 'SI COINCIDE' : '*** NO COINCIDE ***';
    echo "  Institución #{$c['institucion_id']} ({$c['institucion']})\n";
    echo "    categoría #{$c['id']}: \"{$c['nombre']}\"  -> {$coincide} con \"{$esperado}\"\n";
    echo "    activa={$c['activo']}  eliminada=" . ($c['eliminado_en'] ?? 'no') . "\n";
    echo "    bytes={$c['bytes']}  caracteres={$c['caracteres']}  hex={$c['hex']}\n\n";
}

echo "=== 2. Qué devuelve HOY el cálculo, por institución ===\n\n";

foreach ($pdo->query('SELECT id, nombre FROM instituciones ORDER BY id')->fetchAll() as $institucion) {
    $institucionId = (int) $institucion['id'];
    echo "  Institución #{$institucionId} ({$institucion['nombre']})\n";

    $categoriaId = Categoria::idDeProtegida($institucionId);

    if ($categoriaId === null) {
        echo "    *** No encontró ninguna categoría llamada exactamente \"{$esperado}\" ***\n";
        echo "    -> La sugerencia NO se va a activar para esta institución.\n\n";
        continue;
    }

    echo "    categoría \"{$esperado}\" = #{$categoriaId}\n";

    $conteo = $pdo->prepare('SELECT COUNT(*) FROM bienes WHERE institucion_id = ? AND categoria_id = ?');
    $conteo->execute([$institucionId, $categoriaId]);
    $totalBienes = (int) $conteo->fetchColumn();

    $conteo10 = $pdo->prepare("SELECT COUNT(*) FROM bienes WHERE institucion_id = ? AND categoria_id = ? AND codigo_identificacion REGEXP '^[0-9]{10}$'");
    $conteo10->execute([$institucionId, $categoriaId]);
    $totalDiezDigitos = (int) $conteo10->fetchColumn();

    echo "    bienes en esa categoría: {$totalBienes}  (de esos, con código de 10 dígitos: {$totalDiezDigitos})\n";

    $ultimos = $pdo->prepare(
        'SELECT codigo_identificacion, created_at FROM bienes
         WHERE institucion_id = ? AND categoria_id = ?
         ORDER BY id DESC LIMIT 5'
    );
    $ultimos->execute([$institucionId, $categoriaId]);
    $filas = $ultimos->fetchAll();

    if ($filas) {
        echo "    últimos 5 registrados en esa categoría:\n";
        foreach ($filas as $b) {
            $largo = strlen($b['codigo_identificacion']);
            $cuenta = preg_match('/^[0-9]{10}$/', $b['codigo_identificacion']) ? 'cuenta' : 'NO cuenta (no son 10 dígitos)';
            echo "      - \"{$b['codigo_identificacion']}\" ({$largo} caracteres, {$cuenta})  {$b['created_at']}\n";
        }
    } else {
        echo "    (todavía no hay bienes en esa categoría)\n";
    }

    echo "    >>> CÓDIGO QUE SUGERIRÍA AHORA: " . Bien::siguienteCodigoSinCartera($institucionId, $categoriaId) . "\n\n";
}

echo "Listo. Nada se modificó.\n";
