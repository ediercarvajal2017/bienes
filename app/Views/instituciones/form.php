<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;

$esEdicion = $institucion !== null;
$puedeEditar = Auth::esSuperusuario() || Auth::tienePermiso('instituciones.editar');
// Código DANE, tipo de sede e institución principal: solo el superusuario (el servidor
// ignora estos campos para los demás — ver InstitucionController::actualizar()).
$puedeEditarEstructura = Auth::esSuperusuario();
$viejo ??= [];
$v = static fn (string $campo, mixed $porDefecto = '') => $viejo[$campo] ?? $institucion[$campo] ?? $porDefecto;
?>
<?php ob_start(); ?>
    <a href="<?= Url::to('/instituciones') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
<?= ui('encabezado_pagina', ['titulo' => (string) ($esEdicion ? 'Editar institución' : 'Nueva institución'), 'icono' => 'institucion', 'acciones' => (string) ob_get_clean()]) ?>

<?php if (!empty($error)): ?>
    <?= ui('alerta', ['tipo' => 'peligro', 'mensaje' => (string) $error, 'clases' => 'py-2 small']) ?>
<?php endif; ?>

<?php if ($puedeEditar): ?>
    <p class="text-muted small mb-2">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>
<?php endif; ?>

<form method="post"
      action="<?= $esEdicion ? Url::to('/instituciones/' . $institucion['id']) : Url::to('/instituciones') ?>"
      enctype="multipart/form-data" class="row g-3 contenedor-md">
    <?= Csrf::field() ?>

    <div class="col-md-6">
        <label class="form-label small requerido" for="codigoDane">Código DANE</label>
        <input type="text" name="codigo_dane" id="codigoDane" class="form-control" inputmode="numeric" pattern="[0-9]*" required <?= $puedeEditarEstructura ? '' : 'disabled' ?>
               value="<?= htmlspecialchars($v('codigo_dane'), ENT_QUOTES) ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label small" for="tipoSede">Tipo de sede</label>
        <select name="tipo_sede" id="tipoSede" class="form-select" <?= $puedeEditarEstructura ? '' : 'disabled' ?>>
            <option value="principal" <?= $v('tipo_sede', 'principal') === 'principal' ? 'selected' : '' ?>>Principal</option>
            <option value="seccion" <?= $v('tipo_sede') === 'seccion' ? 'selected' : '' ?>>Sección</option>
        </select>
    </div>

    <?php if ($esEdicion && !$puedeEditarEstructura): ?>
        <div class="col-12">
            <p class="form-text small mb-0">
                El código DANE, el tipo de sede y la institución principal solo los puede cambiar el superusuario.
            </p>
        </div>
    <?php endif; ?>

    <div class="col-12">
        <label for="campo-nombre" class="form-label small requerido">Nombre</label>
        <input id="campo-nombre" type="text" name="nombre" class="form-control" required <?= $puedeEditar ? '' : 'disabled' ?>
               value="<?= htmlspecialchars($v('nombre'), ENT_QUOTES) ?>">
    </div>

    <div class="col-12" id="campoPadre"<?= $v('tipo_sede') === 'seccion' ? '' : ' hidden' ?>>
        <label class="form-label small" for="institucionPadre">Institución principal</label>
        <select name="institucion_padre_id" id="institucionPadre" class="form-select selector-buscable" <?= $puedeEditarEstructura ? '' : 'disabled' ?>>
            <option value="">-- Selecciona --</option>
            <?php foreach ($instituciones as $opt): ?>
                <?php if ($esEdicion && (int) $opt['id'] === (int) $institucion['id']) { continue; } ?>
                <option value="<?= $opt['id'] ?>" <?= (int) $v('institucion_padre_id', 0) === (int) $opt['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($opt['nombre'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-12">
        <label for="campo-direccion" class="form-label small">Dirección</label>
        <input id="campo-direccion" type="text" name="direccion" class="form-control" <?= $puedeEditar ? '' : 'disabled' ?>
               value="<?= htmlspecialchars($v('direccion'), ENT_QUOTES) ?>">
    </div>

    <div class="col-12">
        <label for="campo-email-institucional" class="form-label small">Correo institucional</label>
        <input id="campo-email-institucional" type="email" name="email_institucional" class="form-control" <?= $puedeEditar ? '' : 'disabled' ?>
               value="<?= htmlspecialchars($v('email_institucional'), ENT_QUOTES) ?>">
    </div>

    <div class="col-12">
        <label for="campo-logo" class="form-label small d-block">Logo institucional (JPG o PNG)</label>
        <?php if (!empty($institucion['logo_path'])): ?>
            <img src="<?= \App\Helpers\EnlaceArchivo::url($institucion['logo_path']) ?>" alt="Logo actual" class="mb-2 d-block logo-56">
        <?php endif; ?>
        <?php if ($puedeEditar): ?>
            <input id="campo-logo" type="file" name="logo" accept="image/jpeg,image/png" class="form-control">
        <?php endif; ?>
    </div>

    <?php if ($puedeEditar): ?>
        <div class="col-12">
            <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Registrar institución' ?></button>
        </div>
    <?php endif; ?>
</form>

<script>
document.getElementById('tipoSede')?.addEventListener('change', function () {
    document.getElementById('campoPadre').hidden = this.value !== 'seccion';
});
</script>
