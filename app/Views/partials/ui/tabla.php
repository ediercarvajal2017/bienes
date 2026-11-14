<?php
/**
 * Abre una tabla de datos compacta: encabezado fijo al desplazar (en pantallas grandes),
 * texto a la izquierda, números y acciones a la derecha. En el celular cada fila se
 * muestra como tarjeta: cada <td> lleva data-label con el nombre de su columna.
 * Se cierra con ui('tabla_fin'); las filas las escribe la vista:
 *   <td class="celda-numero" data-label="Valor">…</td>
 *   <td class="celda-acciones" data-label="Acciones">…</td>
 *
 * Variables:
 *  - $columnas: list<array{texto: string, alineacion?: 'derecha'|'centro', oculta?: bool, clase?: string}>
 *    (oculta: el texto solo lo leen los lectores de pantalla, p. ej. la columna de acciones).
 *  - $leyenda (?string): qué contiene la tabla, para lectores de pantalla.
 *  - $id (?string)
 *  - $encabezadoFijo (bool, por defecto true).
 */

$leyenda ??= null;
$id ??= null;
$encabezadoFijo ??= true;
$alineaciones = ['derecha' => 'text-end', 'centro' => 'text-center'];
?>
<?php // Recuadro con desplazamiento: enfocable con el teclado y con nombre (WCAG 2.1.1). ?>
<div class="table-responsive tabla-datos<?= $encabezadoFijo ? ' tabla-encabezado-fijo' : '' ?>" tabindex="0" role="region"<?= $leyenda !== null ? ' aria-label="' . htmlspecialchars($leyenda, ENT_QUOTES) . '"' : '' ?>>
    <table class="table table-sm align-middle tabla-cards"<?= $id !== null ? ' id="' . htmlspecialchars($id, ENT_QUOTES) . '"' : '' ?>>
        <?php if ($leyenda !== null): ?><caption class="visually-hidden"><?= htmlspecialchars($leyenda, ENT_QUOTES) ?></caption><?php endif; ?>
        <thead>
        <tr>
            <?php foreach ($columnas as $columna): ?>
                <?php $clase = trim(($alineaciones[$columna['alineacion'] ?? ''] ?? '') . ' ' . ($columna['clase'] ?? '')); ?>
                <th scope="col"<?= $clase !== '' ? ' class="' . htmlspecialchars($clase, ENT_QUOTES) . '"' : '' ?>><?php if (!empty($columna['oculta'])): ?><span class="visually-hidden"><?= htmlspecialchars($columna['texto'], ENT_QUOTES) ?></span><?php else: ?><?= htmlspecialchars($columna['texto'], ENT_QUOTES) ?><?php endif; ?></th>
            <?php endforeach; ?>
        </tr>
        </thead>
        <tbody>
