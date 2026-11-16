<?php
/**
 * Aviso en la página: resultado de una acción (mensaje flash) o una condición que el usuario
 * debe conocer. El texto dice qué pasó y, si hay algo que hacer, qué hacer.
 *
 * Variables:
 *  - $tipo: 'exito' | 'peligro' | 'advertencia' | 'info'
 *  - $mensaje (string): texto plano.
 *  - $cerrable (bool, por defecto true): alertas.js le pone el botón de cerrar; un aviso de
 *    estado (no un mensaje) va con false.
 *  - $clases (string): clases extra (márgenes, ancho).
 */

$cerrable ??= true;
$clases ??= '';
$tipos = [
    'exito' => ['alert-success', 'exito', 'status'],
    'peligro' => ['alert-danger', 'error', 'alert'],
    'advertencia' => ['alert-warning', 'advertencia', 'status'],
    'info' => ['alert-info', 'info', 'status'],
];
if (!isset($tipos[$tipo])) {
    throw new \InvalidArgumentException("Tipo de aviso no válido: \"{$tipo}\".");
}
[$claseBootstrap, $claveIcono, $rol] = $tipos[$tipo];
$clase = trim("alert {$claseBootstrap} aviso " . $clases);
?>
<div class="<?= htmlspecialchars($clase, ENT_QUOTES) ?>" role="<?= $rol ?>"<?= $cerrable ? '' : ' data-sin-cerrar' ?>>
    <?= icono($claveIcono, 'aviso-icono') ?><span><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></span>
</div>
