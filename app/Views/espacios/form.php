<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;

$esEdicion = $espacio !== null;
$puedeEditar = Auth::esSuperusuario() || Auth::tienePermiso('espacios.editar') || (!$esEdicion && Auth::tienePermiso('espacios.crear'));
$viejo ??= [];
$v = static fn (string $campo, mixed $porDefecto = '') => $viejo[$campo] ?? $espacio[$campo] ?? $porDefecto;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><?= $esEdicion ? 'Editar espacio' : 'Nuevo espacio' ?></h1>
    <a href="<?= Url::to('/espacios') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<?php if ($puedeEditar): ?>
    <p class="text-muted small mb-2">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>
<?php endif; ?>

<form method="post"
      action="<?= $esEdicion ? Url::to('/espacios/' . $espacio['id']) : Url::to('/espacios') ?>"
      class="row g-3" style="max-width: 640px;">
    <?= Csrf::field() ?>

    <?php if (!$esEdicion && Auth::esSuperusuario()): ?>
        <div class="col-12">
            <label class="form-label small requerido">Institución</label>
            <select name="institucion_id" class="form-select selector-buscable" required>
                <?php foreach ($instituciones as $i): ?>
                    <option value="<?= $i['id'] ?>" <?= (string) $v('institucion_id') === (string) $i['id'] ? 'selected' : '' ?>><?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <div class="col-md-4">
        <label class="form-label small requerido">Número de espacio</label>
        <input type="text" name="codigo" class="form-control" required
               <?= $puedeEditar ? '' : 'disabled' ?>
               placeholder="Ej. 101 o A-101"
               value="<?= htmlspecialchars($v('codigo'), ENT_QUOTES) ?>">
    </div>
    <div class="col-md-8">
        <label class="form-label small requerido">Nombre del espacio</label>
        <input type="text" name="nombre" class="form-control" required <?= $puedeEditar ? '' : 'disabled' ?>
               placeholder="Ej. Sala de sistemas 1"
               value="<?= htmlspecialchars($v('nombre'), ENT_QUOTES) ?>">
    </div>

    <div class="col-12">
        <label class="form-label small requerido" for="responsables">Responsable(s) del espacio</label>
        <select name="responsables[]" id="responsables" class="form-select selector-buscable" multiple size="8" <?= $puedeEditar ? '' : 'disabled' ?>>
            <?php foreach ($usuarios as $u): ?>
                <option value="<?= $u['id'] ?>" <?= in_array((int) $u['id'], $responsablesSeleccionados, true) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['nombres'] . ' ' . $u['apellidos'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($usuarios)): ?>
            <div class="form-text text-danger">
                No hay usuarios disponibles en esta institución. Crea uno en
                "<a href="<?= Url::to('/usuarios/crear') ?>">Usuarios</a>" antes de crear un espacio.
            </div>
        <?php else: ?>
            <div class="form-text">Mantén Ctrl (o Cmd en Mac) para seleccionar varios. Es obligatorio al menos uno.</div>
        <?php endif; ?>
    </div>

    <?php if ($puedeEditar): ?>
        <div class="col-12">
            <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Registrar espacio' ?></button>
        </div>
    <?php endif; ?>
</form>
