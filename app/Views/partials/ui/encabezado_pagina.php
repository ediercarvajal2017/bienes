<?php
/**
 * Encabezado de página: el título (h1) con el ícono del módulo, una línea opcional de
 * contexto y las acciones de la página a la derecha (debajo, en el celular). Las migas de
 * pan las arma el layout a partir del menú: una sola fuente, no se repiten aquí.
 *
 * Variables:
 *  - $titulo (string)
 *  - $icono (?string): clave del diccionario; la del módulo (bien, espacio, reintegro…).
 *  - $subtitulo (?string): una línea de contexto, texto plano.
 *  - $acciones (string): HTML de los botones (ui('boton', …)): primero los secundarios,
 *    al final el primario.
 *
 * Uso: <?= ui('encabezado_pagina', ['titulo' => 'Bienes', 'icono' => 'bien', 'acciones' => $botones]) ?>
 */

$icono ??= null;
$subtitulo ??= null;
$acciones ??= '';
?>
<div class="encabezado-pagina">
    <div class="encabezado-pagina-texto">
        <h1 class="titulo-pagina"><?= $icono !== null ? icono($icono, 'titulo-pagina-icono') : '' ?><?= htmlspecialchars($titulo, ENT_QUOTES) ?></h1>
        <?php if ($subtitulo !== null && $subtitulo !== ''): ?>
            <p class="encabezado-pagina-subtitulo"><?= htmlspecialchars($subtitulo, ENT_QUOTES) ?></p>
        <?php endif; ?>
    </div>
    <?php if ($acciones !== ''): ?>
        <div class="encabezado-pagina-acciones"><?= $acciones ?></div>
    <?php endif; ?>
</div>
