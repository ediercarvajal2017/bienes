<?php
/**
 * Abre una tarjeta: superficie con borde de 1 px, sin sombra, para agrupar un bloque con
 * título (datos de un formulario, un resumen). No todo va en tarjetas: los listados van en
 * una tabla directamente sobre la página. Se cierra con ui('tarjeta_fin').
 *
 * Variables:
 *  - $titulo (?string): título de la sección (h2).
 *  - $acciones (string): HTML a la derecha del título (botones pequeños).
 *  - $id (?string), $clases (string): atributos de la tarjeta.
 *
 * Uso: <?= ui('tarjeta', ['titulo' => 'Datos del bien']) ?> … <?= ui('tarjeta_fin') ?>
 */

$titulo ??= null;
$acciones ??= '';
$id ??= null;
$clases ??= '';
?>
<section class="card tarjeta<?= $clases !== '' ? ' ' . htmlspecialchars($clases, ENT_QUOTES) : '' ?>"<?= $id !== null ? ' id="' . htmlspecialchars($id, ENT_QUOTES) . '"' : '' ?>>
    <div class="card-body">
        <?php if ($titulo !== null || $acciones !== ''): ?>
            <div class="tarjeta-encabezado">
                <?php if ($titulo !== null): ?><h2 class="titulo-seccion"><?= htmlspecialchars($titulo, ENT_QUOTES) ?></h2><?php endif; ?>
                <?php if ($acciones !== ''): ?><div class="tarjeta-acciones"><?= $acciones ?></div><?php endif; ?>
            </div>
        <?php endif; ?>
