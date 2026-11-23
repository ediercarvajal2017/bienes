<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Helpers\AyudaCampo;

?>
<?php ob_start(); ?>
    <a href="<?= Url::to('/verificaciones') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
<?= ui('encabezado_pagina', ['titulo' => 'Nueva jornada de verificación', 'icono' => 'verificacion', 'acciones' => (string) ob_get_clean()]) ?>

<p class="text-muted small contenedor-md">
    Al iniciar la jornada, cualquier persona con acceso a un bien podrá escanear su QR y confirmar si existe físicamente
    o reportar una discrepancia. Un docente solo puede verificar los bienes de los espacios donde es responsable.
</p>

<form method="post" action="<?= Url::to('/verificaciones') ?>" class="card contenedor-md">
    <div class="card-body">
        <?= Csrf::field() ?>
        <div class="mb-3">
            <?= AyudaCampo::etiqueta('campo-nombre', 'Nombre de la jornada', 'jornada_nombre', true) ?>
            <input id="campo-nombre" type="text" name="nombre" class="form-control<?= clase_invalido('nombre') ?>" maxlength="150" placeholder="Ej. Verificación semestre 1 - 2026" required>
        </div>
        <div class="mb-3">
            <?= AyudaCampo::etiqueta('campo-fecha-inicio', 'Fecha de inicio', 'jornada_fecha_inicio', true) ?>
            <input id="campo-fecha-inicio" type="date" name="fecha_inicio" class="form-control<?= clase_invalido('fecha_inicio') ?>" value="<?= date('Y-m-d') ?>" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">
            <?= icono('verificacion', 'me-1') ?>Iniciar jornada
        </button>
    </div>
</form>
