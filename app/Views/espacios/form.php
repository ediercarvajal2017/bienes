<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Helpers\AyudaCampo;

$esEdicion = $espacio !== null;
$puedeEditar = Auth::esSuperusuario() || Auth::tienePermiso('espacios.editar') || (!$esEdicion && Auth::tienePermiso('espacios.crear'));
$viejo ??= [];
$v = static fn (string $campo, mixed $porDefecto = '') => $viejo[$campo] ?? $espacio[$campo] ?? $porDefecto;
?>
<?php ob_start(); ?>
    <a href="<?= Url::to('/espacios') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
<?= ui('encabezado_pagina', ['titulo' => (string) ($esEdicion ? 'Editar espacio' : 'Nuevo espacio'), 'icono' => 'espacio', 'acciones' => (string) ob_get_clean()]) ?>

<form method="post"
      action="<?= $esEdicion ? Url::to('/espacios/' . $espacio['id']) : Url::to('/espacios') ?>"
      class="row g-3 contenedor-md">
    <?= Csrf::field() ?>

    <?php if (!$esEdicion && Auth::esSuperusuario()): ?>
        <div class="col-12">
            <?= AyudaCampo::etiqueta('campo-institucion-id', 'Institución', 'espacio_institucion', true) ?>
            <select id="campo-institucion-id" name="institucion_id" class="form-select selector-buscable" required>
                <?php foreach ($instituciones as $i): ?>
                    <option value="<?= $i['id'] ?>" <?= (string) $v('institucion_id') === (string) $i['id'] ? 'selected' : '' ?>><?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <div class="col-md-4">
        <?= AyudaCampo::etiqueta('campo-codigo', 'Número de espacio', 'espacio_numero', true) ?>
        <input id="campo-codigo" type="text" name="codigo" class="form-control<?= clase_invalido('codigo') ?>" required maxlength="30"
               <?= $puedeEditar ? '' : 'disabled' ?>
               placeholder="Ej. 101 o A-101"
               value="<?= htmlspecialchars($v('codigo'), ENT_QUOTES) ?>">
        <?= error_campo('codigo') ?>
    </div>
    <div class="col-md-8">
        <?= AyudaCampo::etiqueta('campo-nombre', 'Nombre del espacio', 'espacio_nombre', true) ?>
        <input id="campo-nombre" type="text" name="nombre" class="form-control<?= clase_invalido('nombre') ?>" required maxlength="100" <?= $puedeEditar ? '' : 'disabled' ?>
               placeholder="Ej. Sala de sistemas 1"
               value="<?= htmlspecialchars($v('nombre'), ENT_QUOTES) ?>">
        <?= error_campo('nombre') ?>
    </div>

    <div class="col-12">
        <?= AyudaCampo::etiqueta('responsables', 'Responsable(s) del espacio', 'espacio_responsables', true) ?>
        <select name="responsables[]" id="responsables" class="form-select selector-buscable<?= clase_invalido('responsables[]') ?>" multiple size="8" <?= $puedeEditar ? '' : 'disabled' ?>>
            <?php foreach ($usuarios as $u): ?>
                <option value="<?= $u['id'] ?>" <?= in_array((int) $u['id'], $responsablesSeleccionados, true) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['nombres'] . ' ' . $u['apellidos'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= error_campo('responsables[]') ?>
        <?php if (empty($usuarios)): ?>
            <div class="form-text text-danger">
                No hay usuarios disponibles en esta institución. Crea uno en
                "<a href="<?= Url::to('/usuarios/crear') ?>">Usuarios</a>" antes de crear un espacio.
            </div>
        <?php endif; ?>
    </div>

    <?php if ($puedeEditar): ?>
        <div class="col-12">
            <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Registrar espacio' ?></button>
        </div>
    <?php endif; ?>
</form>
