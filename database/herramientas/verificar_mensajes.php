<?php

declare(strict_types=1);

/**
 * Verificador de los mensajes guiados (qué pasó, por qué, qué hacer; docs/guia-estilo-ui.md):
 *   php database/herramientas/verificar_mensajes.php              informe; código 1 si algo empeoró
 *   php database/herramientas/verificar_mensajes.php --actualizar  baja la lista de pendientes
 * PHPUnit lo comprueba también (tests/Unit/MensajesGuiadosTest.php).
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Tests\Unit\RevisionMensajes;

if (in_array('--actualizar', $argv, true)) {
    // La primera vez (sin lista) se toma el estado actual como punto de partida.
    $subio = is_file(RevisionMensajes::archivoPendientes()) ? RevisionMensajes::comparar()['subio'] : [];
    if ($subio !== []) {
        fwrite(STDERR, "No se actualiza: hay archivos que empeoraron.\n  " . implode("\n  ", $subio) . "\n");
        exit(1);
    }
    file_put_contents(RevisionMensajes::archivoPendientes(), RevisionMensajes::exportar());
    echo "Lista de pendientes actualizada.\n";
}

$conteo = RevisionMensajes::conteo();
$totales = array_fill_keys(array_keys(RevisionMensajes::REGLAS), 0);
foreach ($conteo as $reglas) {
    foreach ($reglas as $regla => $cantidad) {
        $totales[$regla] += $cantidad;
    }
}
echo 'Archivos con mensajes antiguos: ' . count($conteo) . "\n";
foreach ($totales as $regla => $total) {
    echo "  {$regla}: {$total}\n";
}

$diferencias = RevisionMensajes::comparar();
if ($diferencias['subio'] !== []) {
    echo "\nEmpeoraron:\n  " . implode("\n  ", $diferencias['subio']) . "\n";
    exit(1);
}
if ($diferencias['bajo'] !== []) {
    echo "\nMejoraron (ejecuta con --actualizar):\n  " . implode("\n  ", $diferencias['bajo']) . "\n";
}
exit(0);
