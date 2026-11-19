<?php
/**
 * Abre una tabla de datos compacta: encabezado fijo al desplazar (en pantallas grandes),
 * texto a la izquierda, números y acciones a la derecha. En el celular cada fila se
 * muestra como tarjeta: cada <td> lleva data-label con el nombre de su columna.
 * Se cierra con ui('tabla_fin'); las filas las escribe la vista (la celda de acciones va sin
 * data-label: en el celular los botones se explican solos):
 *   <td class="celda-numero" data-label="Valor">…</td>
 *   <td class="celda-acciones">…</td>
 *
 * Variables:
 *  - $columnas: list<array{texto: string, alineacion?: 'derecha'|'centro', oculta?: bool, clase?: string, fija?: bool}>
 *    (oculta: el texto solo lo leen los lectores de pantalla, p. ej. la columna de acciones;
 *    fija: queda inmovilizada a la izquierda al desplazarse a la derecha; solo cuentan las
 *    primeras columnas seguidas marcadas así).
 *  - $leyenda (?string): qué contiene la tabla, para lectores de pantalla.
 *  - $id (?string)
 *  - $encabezadoFijo (bool, por defecto true).
 *  - $navegable (bool): en pantallas grandes se recorre con el teclado como una planilla
 *    (public/assets/js/tabla-navegable.js). Cada <tr> puede llevar data-url (lo que abre
 *    Enter o el doble clic) y data-id (para volver a la misma fila al recargar).
 *  - $filaInicial (int): cuántas filas hay antes de esta página (para «fila 51 de 2.590»).
 *  - $totalFilas (?int): cuántas filas tiene el listado completo.
 *  - Ordenar por columna: cada columna ordenable lleva 'orden' => clave y, si se quiere,
 *    'ordenTextos' => [ascendente, descendente] («de menor a mayor», «de la A a la Z»…).
 *    $ordenActual / $direccion ('asc'|'desc') dicen cómo está ordenada, y $urlOrden
 *    (fn (string $clave, string $dir): string) arma el enlace. El título es un enlace
 *    (funciona sin JavaScript) con aria-sort en la columna ordenada.
 */

$leyenda ??= null;
$id ??= null;
$encabezadoFijo ??= true;
$navegable ??= false;
$filaInicial ??= 0;
$totalFilas ??= null;
$ordenActual ??= null;
$direccion ??= 'asc';
$urlOrden ??= null;
$alineaciones = ['derecha' => 'text-end', 'centro' => 'text-center'];

$columnasFijas = 0;
foreach ($columnas as $columna) {
    if (empty($columna['fija'])) {
        break;
    }
    $columnasFijas++;
}

$atributos = $navegable ? ' data-navegable data-fila-inicial="' . (int) $filaInicial . '"' . ($totalFilas !== null ? ' data-total-filas="' . (int) $totalFilas . '"' : '') : '';
$atributos .= $columnasFijas > 0 ? ' data-columnas-fijas="' . $columnasFijas . '"' : '';
$clases = 'table-responsive tabla-datos' . ($encabezadoFijo ? ' tabla-encabezado-fijo' : '') . ($columnasFijas > 0 ? ' tabla-fijas' : '');
?>
<?php // Recuadro con desplazamiento: enfocable con el teclado y con nombre (WCAG 2.1.1). ?>
<div class="<?= $clases ?>" tabindex="0" role="region"<?= $leyenda !== null ? ' aria-label="' . htmlspecialchars($leyenda, ENT_QUOTES) . '"' : '' ?><?= $atributos ?>>
    <table class="table table-sm table-hover align-middle tabla-cards"<?= $id !== null ? ' id="' . htmlspecialchars($id, ENT_QUOTES) . '"' : '' ?>>
        <?php if ($leyenda !== null): ?><caption class="visually-hidden"><?= htmlspecialchars($leyenda, ENT_QUOTES) ?></caption><?php endif; ?>
        <thead>
        <tr>
            <?php foreach ($columnas as $columna): ?>
                <?php $clase = trim(($alineaciones[$columna['alineacion'] ?? ''] ?? '') . ' ' . ($columna['clase'] ?? '')); ?>
                <?php if (isset($columna['orden']) && $urlOrden !== null): ?>
                    <?php
                    $ordenada = $ordenActual === $columna['orden'];
                    $siguiente = $ordenada && $direccion === 'asc' ? 'desc' : 'asc';
                    [$textoAsc, $textoDesc] = $columna['ordenTextos'] ?? ['de menor a mayor', 'de mayor a menor'];
                    $icono = $ordenada ? ($direccion === 'asc' ? 'orden_ascendente' : 'orden_descendente') : 'ordenable';
                    ?>
                    <th scope="col"<?= $clase !== '' ? ' class="' . htmlspecialchars($clase, ENT_QUOTES) . '"' : '' ?><?= $ordenada ? ' aria-sort="' . ($direccion === 'asc' ? 'ascending' : 'descending') . '"' : '' ?>>
                        <?php $accion = 'ordenar ' . ($siguiente === 'asc' ? $textoAsc : $textoDesc); ?>
                        <a class="encabezado-orden<?= $ordenada ? ' activo' : '' ?>" href="<?= htmlspecialchars($urlOrden($columna['orden'], $siguiente), ENT_QUOTES) ?>"
                           aria-label="<?= htmlspecialchars($columna['texto'] . ', ' . $accion, ENT_QUOTES) ?>"
                           title="<?= htmlspecialchars(mb_strtoupper(mb_substr($accion, 0, 1)) . mb_substr($accion, 1), ENT_QUOTES) ?>"><?= htmlspecialchars($columna['texto'], ENT_QUOTES) ?><?= icono($icono, 'encabezado-orden-icono') ?></a>
                    </th>
                <?php else: ?>
                <th scope="col"<?= $clase !== '' ? ' class="' . htmlspecialchars($clase, ENT_QUOTES) . '"' : '' ?>><?php if (!empty($columna['oculta'])): ?><span class="visually-hidden"><?= htmlspecialchars($columna['texto'], ENT_QUOTES) ?></span><?php else: ?><?= htmlspecialchars($columna['texto'], ENT_QUOTES) ?><?php endif; ?></th>
                <?php endif; ?>
            <?php endforeach; ?>
        </tr>
        </thead>
        <tbody>
