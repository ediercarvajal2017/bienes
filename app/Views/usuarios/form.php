<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;
use App\Helpers\AyudaCampo;

$esEdicion = $usuario !== null;
$viejo ??= [];
$v = static fn (string $campo, mixed $porDefecto = '') => $viejo[$campo] ?? $usuario[$campo] ?? $porDefecto;
// Campo marcado por el aviso de error de la acción anterior (App\Core\Aviso).
$invalido = static fn (string $campo): string => clase_invalido($campo);
?>
<?php ob_start(); ?>
    <a href="<?= Url::to('/usuarios') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
<?= ui('encabezado_pagina', ['titulo' => (string) ($esEdicion ? 'Editar usuario' : 'Nuevo usuario'), 'icono' => 'usuarios', 'acciones' => (string) ob_get_clean()]) ?>

<form method="post"
      action="<?= $esEdicion ? Url::to('/usuarios/' . $usuario['id']) : Url::to('/usuarios') ?>"
      enctype="multipart/form-data" class="row g-3 contenedor-md">
    <?= Csrf::field() ?>

    <div class="col-md-6">
        <?= AyudaCampo::etiqueta('campo-documento', 'Documento', 'usuario_documento', true) ?>
        <input id="campo-documento" type="text" name="documento" class="form-control<?= $invalido('documento') ?>" required maxlength="20"
               value="<?= htmlspecialchars($v('documento'), ENT_QUOTES) ?>">
        <?= error_campo('documento') ?>
    </div>
    <div class="col-md-6">
        <?= AyudaCampo::etiqueta('campo-email', 'Correo', 'usuario_correo', true) ?>
        <input id="campo-email" type="email" name="email" class="form-control<?= $invalido('email') ?>" required maxlength="150"
               value="<?= htmlspecialchars($v('email'), ENT_QUOTES) ?>">
        <?= error_campo('email') ?>
    </div>

    <div class="col-md-6">
        <?= AyudaCampo::etiqueta('campo-nombres', 'Nombres', 'usuario_nombres', true) ?>
        <input id="campo-nombres" type="text" name="nombres" class="form-control<?= $invalido('nombres') ?>" required maxlength="100"
               value="<?= htmlspecialchars($v('nombres'), ENT_QUOTES) ?>">
        <?= error_campo('nombres') ?>
    </div>
    <div class="col-md-6">
        <?= AyudaCampo::etiqueta('campo-apellidos', 'Apellidos', 'usuario_apellidos', true) ?>
        <input id="campo-apellidos" type="text" name="apellidos" class="form-control<?= $invalido('apellidos') ?>" required maxlength="100"
               value="<?= htmlspecialchars($v('apellidos'), ENT_QUOTES) ?>">
        <?= error_campo('apellidos') ?>
    </div>

    <div class="col-md-6">
        <?= AyudaCampo::etiqueta('campo-cargo-id', 'Cargo', 'usuario_cargo', true) ?>
        <select id="campo-cargo-id" name="cargo_id" class="form-select" required>
            <?php foreach ($cargos as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (int) $v('cargo_id', 0) === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-6">
        <?= AyudaCampo::etiqueta('campo-rol-id', 'Rol', 'usuario_rol', true) ?>
        <select id="campo-rol-id" name="rol_id" class="form-select<?= $invalido('rol_id') ?>" required>
            <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>" <?= (int) $v('rol_id', 0) === (int) $r['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst($r['nombre']), ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= error_campo('rol_id') ?>
    </div>

    <?php if (Auth::esSuperusuario()): ?>
        <div class="col-12">
            <?= AyudaCampo::etiqueta('campo-institucion-id', 'Institución', 'usuario_institucion', true) ?>
            <select id="campo-institucion-id" name="institucion_id" class="form-select selector-buscable" required>
                <?php foreach ($instituciones as $i): ?>
                    <option value="<?= $i['id'] ?>" <?= (int) $v('institucion_id', 0) === (int) $i['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php elseif (count($familiaSedes) > 1): ?>
        <div class="col-12">
            <?= AyudaCampo::etiqueta('sedeUsuarioSelect', 'Sede', 'usuario_sede', true) ?>
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
        <?= AyudaCampo::etiqueta('password', $esEdicion ? 'Nueva contraseña (opcional)' : 'Contraseña', 'usuario_contrasena', !$esEdicion) ?>
        <input type="password" name="password" id="password" class="form-control<?= $invalido('password') ?>" <?= $esEdicion ? '' : 'required' ?> minlength="10" autocomplete="new-password">
        <?= error_campo('password') ?>
    </div>

    <div class="col-12">
        <?php View::render('partials/campo_foto', [
            'nombreCampo' => 'foto',
            'etiqueta' => 'Fotografía',
            'ayuda' => 'usuario_foto',
            'fotoActualUrl' => !empty($usuario['foto_path']) ? \App\Helpers\EnlaceArchivo::url($usuario['foto_path']) : null,
        ]); ?>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Registrar usuario' ?></button>
    </div>
</form>

<?php if ($esEdicion && (int) $usuario['id'] !== (int) Auth::id()): ?>
    <div class="card mt-4 contenedor-md">
        <div class="card-body">
            <h2 class="h6"><?= icono('seguridad', 'me-1') ?>Verificación en dos pasos</h2>
            <?php if (!empty($dosFactoresActiva)): ?>
                <p class="small text-muted mb-2">
                    Activa desde <?= htmlspecialchars(substr((string) $usuario['totp_activado_en'], 0, 10), ENT_QUOTES) ?>.
                    Si el usuario perdió o cambió su teléfono, restablézcala: entrará solo con su contraseña y la configurará de nuevo.
                </p>
                <form method="post" action="<?= Url::to('/usuarios/' . $usuario['id'] . '/restablecer-2fa') ?>"
                      class="row g-2 align-items-end"
                      data-confirmar="¿Restablecer la verificación en dos pasos de este usuario? Se cerrarán sus sesiones abiertas.">
                    <?= Csrf::field() ?>
                    <div class="col-sm-7">
                        <?= AyudaCampo::etiqueta('passwordConfirmacion2fa', 'Tu contraseña, para confirmar', 'restablecer_dos_pasos') ?>
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
