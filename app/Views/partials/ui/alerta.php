<?php
/**
 * Aviso en la página: resultado de una acción o una condición que el usuario debe conocer.
 * Todos los avisos llevan el mismo diseño de MIA (superficie, título en verde MIA); el tipo
 * cambia solo el ícono y la franja de la izquierda (docs/guia-estilo-ui.md, "Mensajes").
 *
 * Variables:
 *  - $tipo: 'exito' | 'peligro' | 'advertencia' | 'info'
 *  - En tres partes (App\Core\Aviso):
 *      $titulo (string): qué pasó.
 *      $detalle (string): por qué (en el éxito, el siguiente paso).
 *      $solucion (string): qué hacer; se antepone "Qué hacer:".
 *      $accion (array{texto: string, url: string}|null): botón a la pantalla donde se corrige.
 *  - O en una sola: $mensaje (string), texto plano.
 *  - $cerrable (bool, por defecto true): alertas.js le pone el botón de cerrar; un aviso de
 *    estado (no un mensaje) va con false.
 *  - $enfocar (bool): recibe el foco al cargar la página (el error que resultó de la acción).
 *  - $clases (string): clases extra (márgenes, ancho).
 */

$cerrable ??= true;
$enfocar ??= false;
$clases ??= '';
$titulo ??= '';
$detalle ??= '';
$solucion ??= '';
$accion ??= null;
$mensaje ??= '';
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
$e = static fn (mixed $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES);
// El aviso de una acción (con título) lleva el título en verde MIA aunque sea de una línea;
// un aviso de estado con $mensaje va como texto simple.
$enPartes = $titulo !== '';
?>
<div class="<?= $e($clase) ?>" role="<?= $rol ?>"<?= $cerrable ? '' : ' data-sin-cerrar' ?><?= $enfocar ? ' tabindex="-1" data-aviso-enfocar' : '' ?>>
    <?= icono($claveIcono, 'aviso-icono') ?>
    <?php if ($enPartes): ?>
        <div class="aviso-cuerpo">
            <p class="aviso-titulo"><?= $e($titulo) ?></p>
            <?php if ($detalle !== ''): ?><p class="aviso-detalle"><?= $e($detalle) ?></p><?php endif; ?>
            <?php if ($solucion !== ''): ?><p class="aviso-solucion"><strong>Qué hacer:</strong> <?= $e($solucion) ?></p><?php endif; ?>
            <?php if ($accion !== null): ?>
                <div class="aviso-acciones"><?= ui('boton', ['texto' => $accion['texto'], 'url' => $accion['url'], 'tipo' => 'primario', 'tamano' => 'sm']) ?></div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <span><?= $e($titulo !== '' ? $titulo : $mensaje) ?></span>
    <?php endif; ?>
</div>
