<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$esEdicion = $usuario !== null;
$viejo ??= [];
$errorCampo ??= null;
$v = static fn (string $campo, mixed $porDefecto = '') => $viejo[$campo] ?? $usuario[$campo] ?? $porDefecto;
$invalido = static fn (string $campo) => $errorCampo === $campo ? ' is-invalid' : '';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><?= $esEdicion ? 'Editar usuario' : 'Nuevo usuario' ?></h1>
    <a href="<?= Url::to('/usuarios') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>
<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div>
<?php endif; ?>

<p class="text-muted small mb-2">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>

<form method="post"
      action="<?= $esEdicion ? Url::to('/usuarios/' . $usuario['id']) : Url::to('/usuarios') ?>"
      enctype="multipart/form-data" class="row g-3" style="max-width: 640px;">
    <?= Csrf::field() ?>

    <div class="col-md-6">
        <label class="form-label small requerido">Documento</label>
        <input type="text" name="documento" class="form-control<?= $invalido('documento') ?>" required
               value="<?= htmlspecialchars($v('documento'), ENT_QUOTES) ?>">
        <?php if ($errorCampo === 'documento'): ?>
            <div class="invalid-feedback d-block"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
        <?php endif; ?>
    </div>
    <div class="col-md-6">
        <label class="form-label small requerido">Correo</label>
        <input type="email" name="email" class="form-control<?= $invalido('email') ?>" required
               value="<?= htmlspecialchars($v('email'), ENT_QUOTES) ?>">
        <?php if ($errorCampo === 'email'): ?>
            <div class="invalid-feedback d-block"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
        <?php endif; ?>
    </div>

    <div class="col-md-6">
        <label class="form-label small requerido">Nombres</label>
        <input type="text" name="nombres" class="form-control<?= $invalido('nombres') ?>" required
               value="<?= htmlspecialchars($v('nombres'), ENT_QUOTES) ?>">
        <?php if ($errorCampo === 'nombres'): ?>
            <div class="invalid-feedback d-block"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
        <?php endif; ?>
    </div>
    <div class="col-md-6">
        <label class="form-label small requerido">Apellidos</label>
        <input type="text" name="apellidos" class="form-control<?= $invalido('apellidos') ?>" required
               value="<?= htmlspecialchars($v('apellidos'), ENT_QUOTES) ?>">
        <?php if ($errorCampo === 'apellidos'): ?>
            <div class="invalid-feedback d-block"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
        <?php endif; ?>
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
        <select name="rol_id" class="form-select<?= $invalido('rol_id') ?>" required>
            <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>" <?= (int) $v('rol_id', 0) === (int) $r['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst($r['nombre']), ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($errorCampo === 'rol_id'): ?>
            <div class="invalid-feedback d-block"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
        <?php endif; ?>
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
    <?php elseif (count($familiaSedes) > 1): ?>
        <div class="col-12">
            <label class="form-label small requerido" for="sedeUsuarioSelect">Sede</label>
            <select name="institucion_id" id="sedeUsuarioSelect" class="form-select" required>
                <?php foreach ($familiaSedes as $sede): ?>
                    <option value="<?= $sede['id'] ?>" <?= (int) $v('institucion_id', Auth::institucionId()) === (int) $sede['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sede['nombre'], ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <div class="col-md-6">
        <label class="form-label small<?= $esEdicion ? '' : ' requerido' ?>" for="password"><?= $esEdicion ? 'Nueva contraseña (opcional)' : 'Contraseña' ?></label>
        <input type="password" name="password" id="password" class="form-control<?= $invalido('password') ?>" <?= $esEdicion ? '' : 'required' ?> minlength="10" autocomplete="new-password">
        <?php if ($errorCampo === 'password'): ?>
            <div class="invalid-feedback d-block"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
        <?php else: ?>
            <div class="form-text">Mínimo 10 caracteres, con letras y números. No use su nombre, documento ni correo.</div>
        <?php endif; ?>
    </div>

    <div class="col-12">
        <?php View::render('partials/campo_foto', [
            'nombreCampo' => 'foto',
            'etiqueta' => 'Fotografía',
            'fotoActualUrl' => !empty($usuario['foto_path']) ? Url::to('/archivos/' . $usuario['foto_path']) : null,
        ]); ?>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Registrar usuario' ?></button>
    </div>
</form>

<?php if ($esEdicion && (int) $usuario['id'] !== (int) Auth::id()): ?>
    <div class="card mt-4" style="max-width: 640px;">
        <div class="card-body">
            <h2 class="h6"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Verificación en dos pasos</h2>
            <?php if (!empty($dosFactoresActiva)): ?>
                <p class="small text-muted mb-2">
                    Activa desde <?= htmlspecialchars(substr((string) $usuario['totp_activado_en'], 0, 10), ENT_QUOTES) ?>.
                    Si el usuario perdió o cambió su teléfono, restablézcala: entrará solo con su contraseña y la configurará de nuevo.
                </p>
                <form method="post" action="<?= Url::to('/usuarios/' . $usuario['id'] . '/restablecer-2fa') ?>"
                      class="row g-2 align-items-end"
                      onsubmit="return confirm('¿Restablecer la verificación en dos pasos de este usuario? Se cerrarán sus sesiones abiertas.');">
                    <?= Csrf::field() ?>
                    <div class="col-sm-7">
                        <label class="form-label small" for="passwordConfirmacion2fa">Tu contraseña, para confirmar</label>
                        <input type="password" name="password_confirmacion" id="passwordConfirmacion2fa" class="form-control form-control-sm" required autocomplete="current-password">
                    </div>
                    <div class="col-sm-5">
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">Restablecer</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="small text-muted mb-0">No la tiene activa. Cada usuario la configura desde «Mi cuenta».</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
