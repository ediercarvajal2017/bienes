<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$esEdicion = $usuario !== null;
$viejo ??= [];
$v = static fn (string $campo, mixed $porDefecto = '') => $viejo[$campo] ?? $usuario[$campo] ?? $porDefecto;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><?= $esEdicion ? 'Editar usuario' : 'Nuevo usuario' ?></h1>
    <a href="<?= Url::to('/usuarios') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<p class="text-muted small mb-2">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>

<form method="post"
      action="<?= $esEdicion ? Url::to('/usuarios/' . $usuario['id']) : Url::to('/usuarios') ?>"
      enctype="multipart/form-data" class="row g-3" style="max-width: 640px;">
    <?= Csrf::field() ?>

    <div class="col-md-6">
        <label class="form-label small requerido">Documento</label>
        <input type="text" name="documento" class="form-control" required
               value="<?= htmlspecialchars($v('documento'), ENT_QUOTES) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label small requerido">Correo</label>
        <input type="email" name="email" class="form-control" required
               value="<?= htmlspecialchars($v('email'), ENT_QUOTES) ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label small requerido">Nombres</label>
        <input type="text" name="nombres" class="form-control" required
               value="<?= htmlspecialchars($v('nombres'), ENT_QUOTES) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label small requerido">Apellidos</label>
        <input type="text" name="apellidos" class="form-control" required
               value="<?= htmlspecialchars($v('apellidos'), ENT_QUOTES) ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label small requerido">Cargo</label>
        <select name="cargo_id" class="form-select" required>
            <?php foreach ($cargos as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (int) $v('cargo_id', 0) === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label small requerido">Rol</label>
        <select name="rol_id" class="form-select" required>
            <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>" <?= (int) $v('rol_id', 0) === (int) $r['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst($r['nombre']), ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if (Auth::esSuperusuario()): ?>
        <div class="col-12">
            <label class="form-label small requerido">Institución</label>
            <select name="institucion_id" class="form-select selector-buscable" required>
                <?php foreach ($instituciones as $i): ?>
                    <option value="<?= $i['id'] ?>" <?= (int) $v('institucion_id', 0) === (int) $i['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <div class="col-md-6">
        <label class="form-label small<?= $esEdicion ? '' : ' requerido' ?>"><?= $esEdicion ? 'Nueva contraseña (opcional)' : 'Contraseña' ?></label>
        <input type="password" name="password" class="form-control" <?= $esEdicion ? '' : 'required' ?> minlength="8">
    </div>

    <div class="col-12">
        <?php View::render('partials/campo_foto', [
            'nombreCampo' => 'foto',
            'etiqueta' => 'Fotografía',
            'fotoActualUrl' => !empty($usuario['foto_path']) ? Url::to('/archivos/' . $usuario['foto_path']) : null,
        ]); ?>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear usuario' ?></button>
    </div>
</form>
