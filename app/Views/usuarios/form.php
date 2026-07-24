<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;

$esEdicion = $usuario !== null;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><?= $esEdicion ? 'Editar usuario' : 'Nuevo usuario' ?></h1>
    <a href="<?= Url::to('/usuarios') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<form method="post"
      action="<?= $esEdicion ? Url::to('/usuarios/' . $usuario['id']) : Url::to('/usuarios') ?>"
      enctype="multipart/form-data" class="row g-3" style="max-width: 640px;">
    <?= Csrf::field() ?>

    <div class="col-md-6">
        <label class="form-label small">Documento</label>
        <input type="text" name="documento" class="form-control" required
               value="<?= htmlspecialchars($usuario['documento'] ?? '', ENT_QUOTES) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label small">Correo</label>
        <input type="email" name="email" class="form-control" required
               value="<?= htmlspecialchars($usuario['email'] ?? '', ENT_QUOTES) ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label small">Nombres</label>
        <input type="text" name="nombres" class="form-control" required
               value="<?= htmlspecialchars($usuario['nombres'] ?? '', ENT_QUOTES) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label small">Apellidos</label>
        <input type="text" name="apellidos" class="form-control" required
               value="<?= htmlspecialchars($usuario['apellidos'] ?? '', ENT_QUOTES) ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label small">Cargo</label>
        <select name="cargo_id" class="form-select" required>
            <?php foreach ($cargos as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (int) ($usuario['cargo_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label small">Rol</label>
        <select name="rol_id" class="form-select" required>
            <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>" <?= (int) ($usuario['rol_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst($r['nombre']), ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if (Auth::esSuperusuario()): ?>
        <div class="col-12">
            <label class="form-label small">Institución</label>
            <select name="institucion_id" class="form-select" required>
                <?php foreach ($instituciones as $i): ?>
                    <option value="<?= $i['id'] ?>" <?= (int) ($usuario['institucion_id'] ?? 0) === (int) $i['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <div class="col-md-6">
        <label class="form-label small"><?= $esEdicion ? 'Nueva contraseña (opcional)' : 'Contraseña' ?></label>
        <input type="password" name="password" class="form-control" <?= $esEdicion ? '' : 'required' ?> minlength="8">
    </div>

    <div class="col-12">
        <label class="form-label small d-block">Fotografía</label>
        <?php if (!empty($usuario['foto_path'])): ?>
            <img src="<?= Url::to('/archivos/' . $usuario['foto_path']) ?>" class="rounded-circle mb-2 d-block" style="width:64px;height:64px;object-fit:cover;">
        <?php endif; ?>
        <input type="file" name="foto" accept="image/jpeg,image/png" class="form-control">
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear usuario' ?></button>
    </div>
</form>
