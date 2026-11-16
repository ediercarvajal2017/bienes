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
    <?= ui('boton', ['texto' => 'Descargar', 'icono' => 'descargar', 'tamano' => 'sm', 'url' => EnlaceArchivo::url((string) $archivoPath), 'atributos' => ['target' => '_blank']]) ?>
    <?= ui('boton', ['texto' => 'Editar', 'icono' => 'editar', 'tamano' => 'sm', 'url' => $urlEditar]) ?>
    <form method="post" action="<?= htmlspecialchars($urlEliminar, ENT_QUOTES) ?>" class="d-inline">
        <?= Csrf::field() ?>
        <?= ui('boton', ['texto' => 'Eliminar', 'tipo' => 'peligro', 'icono' => 'eliminar', 'tamano' => 'sm', 'tipoBoton' => 'submit',
            'confirmar' => 'El registro irá a la papelera. Un superusuario puede restaurarlo si fue un error.']) ?>
    </form>
</div>
