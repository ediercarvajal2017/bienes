<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Helpers\AyudaCampo;

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

<form method="post"
      action="<?= $esEdicion ? Url::to('/instituciones/' . $institucion['id']) : Url::to('/instituciones') ?>"
      enctype="multipart/form-data" class="row g-3 contenedor-md">
    <?= Csrf::field() ?>

    <div class="col-md-6">
        <?= AyudaCampo::etiqueta('codigoDane', 'Código DANE', 'institucion_dane', true) ?>
        <input type="text" name="codigo_dane" id="codigoDane" class="form-control<?= clase_invalido('codigo_dane') ?>" inputmode="numeric" pattern="[0-9]*" maxlength="20" required <?= $puedeEditarEstructura ? '' : 'disabled' ?>
               value="<?= htmlspecialchars($v('codigo_dane'), ENT_QUOTES) ?>">
        <?= error_campo('codigo_dane') ?>
    </div>

    <div class="col-md-6">
        <?= AyudaCampo::etiqueta('tipoSede', 'Tipo de sede', 'institucion_tipo_sede') ?>
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
        <?= AyudaCampo::etiqueta('campo-nombre', 'Nombre', 'institucion_nombre', true) ?>
        <input id="campo-nombre" type="text" name="nombre" class="form-control<?= clase_invalido('nombre') ?>" required maxlength="150" <?= $puedeEditar ? '' : 'disabled' ?>
               value="<?= htmlspecialchars($v('nombre'), ENT_QUOTES) ?>">
        <?= error_campo('nombre') ?>
    </div>

    <div class="col-12" id="campoPadre"<?= $v('tipo_sede') === 'seccion' ? '' : ' hidden' ?>>
        <?= AyudaCampo::etiqueta('institucionPadre', 'Institución principal', 'institucion_principal') ?>
        <select name="institucion_padre_id" id="institucionPadre" class="form-select selector-buscable<?= clase_invalido('institucion_padre_id') ?>" <?= $puedeEditarEstructura ? '' : 'disabled' ?>>
            <option value="">-- Selecciona --</option>
            <?php foreach ($instituciones as $opt): ?>
                <?php if ($esEdicion && (int) $opt['id'] === (int) $institucion['id']) { continue; } ?>
                <option value="<?= $opt['id'] ?>" <?= (int) $v('institucion_padre_id', 0) === (int) $opt['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($opt['nombre'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= error_campo('institucion_padre_id') ?>
    </div>

    <div class="col-12">
        <?= AyudaCampo::etiqueta('campo-direccion', 'Dirección', 'institucion_direccion') ?>
        <input id="campo-direccion" type="text" name="direccion" class="form-control<?= clase_invalido('direccion') ?>" maxlength="200" <?= $puedeEditar ? '' : 'disabled' ?>
               value="<?= htmlspecialchars($v('direccion'), ENT_QUOTES) ?>">
        <?= error_campo('direccion') ?>
    </div>

    <div class="col-12">
        <?= AyudaCampo::etiqueta('campo-email-institucional', 'Correo institucional', 'institucion_correo') ?>
        <input id="campo-email-institucional" type="email" name="email_institucional" class="form-control<?= clase_invalido('email_institucional') ?>" maxlength="150" <?= $puedeEditar ? '' : 'disabled' ?>
               value="<?= htmlspecialchars($v('email_institucional'), ENT_QUOTES) ?>">
        <?= error_campo('email_institucional') ?>
    </div>

    <div class="col-12">
        <?= AyudaCampo::etiqueta('campo-logo', 'Logo institucional (JPG o PNG)', 'institucion_logo') ?>
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
