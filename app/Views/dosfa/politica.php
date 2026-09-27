<?php

use App\Core\Csrf;
use App\Core\Url;

?>
<h1 class="h4 mb-1"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Verificación en dos pasos</h1>
<p class="text-muted small mb-3" style="max-width: 760px;">
    Define para qué roles es obligatoria. Nadie queda bloqueado: quien aún no la tiene sigue entrando con su
    contraseña durante los días de gracia (verá un aviso); al vencer, se le pide configurarla justo después de
    iniciar sesión. El plazo se cuenta desde el siguiente ingreso de cada usuario.
</p>

<?php if (!$disponible): ?>
    <div class="alert alert-warning small" style="max-width: 760px;">
        <strong>No está habilitada en este servidor:</strong> falta la llave <code>APP_KEY</code> en el <code>.env</code>.
        Mientras tanto no se exige a nadie. Genérela con
        <code>php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"</code> y guárdela también fuera del servidor.
    </div>
<?php endif; ?>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success py-2 small" style="max-width: 760px;"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small" style="max-width: 760px;"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<form method="post" action="<?= Url::to('/seguridad/verificacion-dos-pasos') ?>" style="max-width: 760px;"
      onsubmit="return confirm('¿Guardar la política? Los plazos de gracia de quienes aún no la configuran se contarán de nuevo desde su siguiente ingreso.');">
    <?= Csrf::field() ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle bg-white tabla-cards">
            <thead>
            <tr>
                <th>Rol</th>
                <th>Obligatoria</th>
                <th>Días de gracia</th>
                <th>Usuarios activos con 2 pasos</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($politicas as $p): ?>
                <?php $rolId = (int) $p['rol_id']; ?>
                <tr>
                    <td class="text-capitalize" data-label="Rol"><strong><?= htmlspecialchars($p['rol_nombre'], ENT_QUOTES) ?></strong></td>
                    <td data-label="Obligatoria">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" name="obligatorio[<?= $rolId ?>]" value="1"
                                   id="obligatorio<?= $rolId ?>" <?= (int) $p['obligatorio'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="obligatorio<?= $rolId ?>">Obligatoria</label>
                        </div>
                    </td>
                    <td data-label="Días de gracia">
                        <label class="visually-hidden" for="dias<?= $rolId ?>">Días de gracia para <?= htmlspecialchars($p['rol_nombre'], ENT_QUOTES) ?></label>
                        <input type="number" name="dias_gracia[<?= $rolId ?>]" id="dias<?= $rolId ?>" class="form-control form-control-sm"
                               style="max-width: 100px;" min="0" max="90" value="<?= (int) $p['dias_gracia'] ?>" inputmode="numeric">
                    </td>
                    <td data-label="Con 2 pasos"><?= (int) $p['con_2fa'] ?> de <?= (int) $p['usuarios'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="form-text">0 días de gracia: se le pide configurarla en su siguiente ingreso.</p>
    <button type="submit" class="btn btn-primary">Guardar política</button>
</form>
