<?php
/**
 * Panel de notas rápidas: se abre con el ícono de la barra superior (layout.php) y lo
 * maneja public/assets/js/notas.js contra NotaController. Cada usuario ve solo las suyas.
 */

use App\Core\Csrf;
use App\Core\Url;
use App\Models\Nota;

?>
<div id="notasFondo" class="notas-fondo" aria-hidden="true"></div>
<aside id="panelNotas" class="panel-notas" role="dialog" aria-labelledby="tituloNotas"
       data-url="<?= Url::to('/notas') ?>" data-csrf="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>"
       data-max-caracteres="<?= Nota::MAX_CARACTERES ?>" data-max-notas="<?= Nota::MAX_POR_USUARIO ?>">
    <div class="panel-notas-encabezado">
        <h2 id="tituloNotas" class="h6 mb-0"><i class="bi bi-sticky me-2" aria-hidden="true"></i>Mis notas</h2>
        <button type="button" class="btn-close" data-cerrar-notas aria-label="Cerrar las notas"></button>
    </div>
    <div class="panel-notas-acciones">
        <button type="button" class="btn btn-sm btn-primary" data-nueva-nota>
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nueva nota
        </button>
        <span class="small text-muted">Solo tú las ves. Se guardan solas.</span>
    </div>
    <div class="panel-notas-error small" role="alert" data-notas-error hidden></div>
    <p class="text-muted small text-center my-4" data-notas-cargando hidden>Cargando tus notas…</p>
    <p class="text-muted small text-center px-3 my-4" data-notas-vacio hidden>
        Todavía no tienes notas. Pulsa <strong>Nueva nota</strong> para anotar algo pendiente.
    </p>
    <div class="panel-notas-lista" data-lista-notas></div>
</aside>
