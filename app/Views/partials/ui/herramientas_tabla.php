<?php
/**
 * Herramientas de una tabla navegable (tabla-navegable.js), solo en pantallas grandes, donde
 * se ve como tabla: el botón «Vista compacta» (filas más bajas, recordado en el computador)
 * y el ícono (?) con las teclas para moverse como en una planilla.
 *
 * Lo pinta partials/paginacion con 'densidad' => true; en una tabla sin paginación se pone
 * encima de ella: <?= ui('herramientas_tabla', ['suelta' => true]) ?>.
 *
 * Variables:
 *  - $atajos (bool, por defecto true): el ícono (?) de las teclas (Bienes lo tiene junto al título).
 *  - $suelta (bool): fuera de la paginación, alineada a la derecha y con margen abajo.
 */

$atajos ??= true;
$suelta ??= false;
?>
<div class="herramientas-tabla d-none d-lg-flex align-items-center gap-1<?= $suelta ? ' justify-content-end mb-2' : '' ?>">
    <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center" data-densidad aria-pressed="false"
            title="Filas más bajas: más registros en pantalla">
        <?= icono('densidad', 'me-1') ?>Vista compacta
    </button>
    <?php if ($atajos): ?>
        <span class="d-inline-flex align-items-center"><span class="small text-muted ms-1">Teclado</span><?= \App\Helpers\AyudaCampo::boton('tabla_navegable') ?></span>
    <?php endif; ?>
</div>
