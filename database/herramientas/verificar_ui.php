<?php

declare(strict_types=1);

/**
 * Verificador del estándar visual (docs/guia-estilo-ui.md), para la consola o cualquier CI:
 *   php database/herramientas/verificar_ui.php              informe; código 1 si algo empeoró
 *   php database/herramientas/verificar_ui.php --actualizar  baja la lista de pendientes
 * PHPUnit lo comprueba también (tests/Unit/EstandarVisualTest.php).
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Tests\Unit\RevisionVisual;

if (in_array('--actualizar', $argv, true)) {
    // La primera vez (sin lista) se toma el estado actual como punto de partida.
    $subio = is_file(RevisionVisual::archivoPendientes()) ? RevisionVisual::comparar()['subio'] : [];
    if ($subio !== []) {
        fwrite(STDERR, "No se actualiza: hay vistas que empeoraron.\n  " . implode("\n  ", $subio) . "\n");
        exit(1);
    }
    file_put_contents(RevisionVisual::archivoPendientes(), RevisionVisual::exportar());
    echo "Lista de pendientes actualizada.\n";
}

$conteo = RevisionVisual::conteo();
$totales = array_fill_keys(array_keys(RevisionVisual::REGLAS), 0);
foreach ($conteo as $reglas) {
    foreach ($reglas as $regla => $cantidad) {
        $totales[$regla] += $cantidad;
    }
}
echo 'Vistas pendientes: ' . count($conteo) . "\n";
foreach ($totales as $regla => $total) {
    echo "  {$regla}: {$total}\n";
}

$diferencias = RevisionVisual::comparar();
if ($diferencias['subio'] !== []) {
    echo "\nEmpeoraron:\n  " . implode("\n  ", $diferencias['subio']) . "\n";
    exit(1);
}
if ($diferencias['bajo'] !== []) {
    echo "\nMejoraron (ejecuta con --actualizar):\n  " . implode("\n  ", $diferencias['bajo']) . "\n";
}
exit(0);
