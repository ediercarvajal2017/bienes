<?php

use App\Core\Csrf;
use App\Core\Url;

?>
<?php ob_start(); ?>
    <a href="<?= Url::to('/verificaciones') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
<?= ui('encabezado_pagina', ['titulo' => 'Nueva jornada de verificación', 'icono' => 'verificacion', 'acciones' => (string) ob_get_clean()]) ?>

<p class="text-muted small contenedor-md">
    Al iniciar la jornada, cualquier persona con acceso a un bien podrá escanear su QR y confirmar si existe físicamente
    o reportar una discrepancia. Un docente solo puede verificar los bienes de los espacios donde es responsable.
</p>

<p class="text-muted small mb-2 contenedor-md">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>

<form method="post" action="<?= Url::to('/verificaciones') ?>" class="card contenedor-md">
    <div class="card-body">
        <?= Csrf::field() ?>
        <div class="mb-3">
            <label for="campo-nombre" class="form-label small requerido">Nombre de la jornada</label>
            <input id="campo-nombre" type="text" name="nombre" class="form-control<?= clase_invalido('nombre') ?>" placeholder="Ej. Verificación semestre 1 - 2026" required>
        </div>
        <div class="mb-3">
            <label for="campo-fecha-inicio" class="form-label small requerido">Fecha de inicio</label>
            <input id="campo-fecha-inicio" type="date" name="fecha_inicio" class="form-control<?= clase_invalido('fecha_inicio') ?>" value="<?= date('Y-m-d') ?>" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">
            <?= icono('verificacion', 'me-1') ?>Iniciar jornada
        </button>
    </div>
</form>
