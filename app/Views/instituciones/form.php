<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;

$esEdicion = $institucion !== null;
$puedeEditar = Auth::esSuperusuario() || Auth::tienePermiso('instituciones.editar');
$viejo ??= [];
$v = static fn (string $campo, mixed $porDefecto = '') => $viejo[$campo] ?? $institucion[$campo] ?? $porDefecto;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><?= $esEdicion ? 'Editar institución' : 'Nueva institución' ?></h1>
    <a href="<?= Url::to('/instituciones') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<?php if ($puedeEditar): ?>
    <p class="text-muted small mb-2">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>
<?php endif; ?>

<form method="post"
      action="<?= $esEdicion ? Url::to('/instituciones/' . $institucion['id']) : Url::to('/instituciones') ?>"
      enctype="multipart/form-data" class="row g-3" style="max-width: 640px;">
    <?= Csrf::field() ?>

    <div class="col-md-6">
        <label class="form-label small requerido">Código DANE</label>
        <input type="text" name="codigo_dane" class="form-control" required <?= $puedeEditar ? '' : 'disabled' ?>
               value="<?= htmlspecialchars($v('codigo_dane'), ENT_QUOTES) ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label small">Tipo de sede</label>
        <select name="tipo_sede" id="tipoSede" class="form-select" <?= $puedeEditar ? '' : 'disabled' ?>>
            <option value="principal" <?= $v('tipo_sede', 'principal') === 'principal' ? 'selected' : '' ?>>Principal</option>
            <option value="seccion" <?= $v('tipo_sede') === 'seccion' ? 'selected' : '' ?>>Sección</option>
        </select>
    </div>

    <div class="col-12">
        <label class="form-label small requerido">Nombre</label>
        <input type="text" name="nombre" class="form-control" required <?= $puedeEditar ? '' : 'disabled' ?>
               value="<?= htmlspecialchars($v('nombre'), ENT_QUOTES) ?>">
    </div>

    <div class="col-12" id="campoPadre" style="<?= $v('tipo_sede') === 'seccion' ? '' : 'display:none;' ?>">
        <label class="form-label small">Institución principal</label>
        <select name="institucion_padre_id" class="form-select selector-buscable" <?= $puedeEditar ? '' : 'disabled' ?>>
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
        <label class="form-label small">Dirección</label>
        <input type="text" name="direccion" class="form-control" <?= $puedeEditar ? '' : 'disabled' ?>
               value="<?= htmlspecialchars($v('direccion'), ENT_QUOTES) ?>">
    </div>

    <div class="col-12">
        <label class="form-label small">Correo institucional</label>
        <input type="email" name="email_institucional" class="form-control" <?= $puedeEditar ? '' : 'disabled' ?>
               value="<?= htmlspecialchars($v('email_institucional'), ENT_QUOTES) ?>">
    </div>

    <div class="col-12">
        <label class="form-label small d-block">Logo institucional (JPG o PNG)</label>
        <?php if (!empty($institucion['logo_path'])): ?>
            <img src="<?= Url::to('/archivos/' . $institucion['logo_path']) ?>" alt="Logo actual" class="mb-2 d-block" style="height:56px;">
        <?php endif; ?>
        <?php if ($puedeEditar): ?>
            <input type="file" name="logo" accept="image/jpeg,image/png" class="form-control">
        <?php endif; ?>
    </div>

    <?php if ($puedeEditar): ?>
        <div class="col-12">
            <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear institución' ?></button>
        </div>
    <?php endif; ?>
</form>

<script>
document.getElementById('tipoSede')?.addEventListener('change', function () {
    document.getElementById('campoPadre').style.display = this.value === 'seccion' ? '' : 'none';
});
</script>
