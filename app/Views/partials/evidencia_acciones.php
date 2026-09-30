<?php
/**
 * Botones de cada registro de una biblioteca de evidencia: Descargar, Editar (en la misma
 * ventana, ?editar=ID) y Eliminar (a la papelera, con confirmación; ver cargando.js).
 * Variables: $archivoPath, $urlEditar y $urlEliminar (ya resueltas con Url::to).
 */

use App\Core\Csrf;
use App\Helpers\EnlaceArchivo;

?>
<div class="d-inline-flex flex-wrap justify-content-end gap-1">
    <a href="<?= EnlaceArchivo::url((string) $archivoPath) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
        <i class="bi bi-download me-1" aria-hidden="true"></i>Descargar
    </a>
    <a href="<?= htmlspecialchars($urlEditar, ENT_QUOTES) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-pencil me-1" aria-hidden="true"></i>Editar
    </a>
    <form method="post" action="<?= htmlspecialchars($urlEliminar, ENT_QUOTES) ?>" class="d-inline"
          data-confirmar="¿Eliminar este registro? Irá a la papelera; un superusuario puede restaurarlo si fue un error.">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar
        </button>
    </form>
</div>
