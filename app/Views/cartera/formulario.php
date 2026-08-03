<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Cartera enviada a la Alcaldía</h1>
        <p class="text-muted small mb-0">Registra la evidencia de cada cartera que la institución envió por correo (fuera del sistema).</p>
    </div>
    <a href="<?= Url::to('/cartera/enviados') ?>" class="btn btn-sm btn-outline-secondary">Ver histórico</a>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<?php if (Auth::esSuperusuario()): ?>
    <div class="mb-3" style="max-width: 320px;">
        <label class="form-label small">Institución</label>
        <select id="selectorInstitucion" class="form-select form-select-sm selector-buscable">
            <option value="">-- Selecciona una institución --</option>
            <?php foreach ($instituciones as $i): ?>
                <option value="<?= $i['id'] ?>" <?= $institucionId === (int) $i['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <script>
    document.getElementById('selectorInstitucion').addEventListener('change', function () {
        window.location = <?= json_encode(Url::to('/cartera/enviar')) ?> + (this.value ? '?institucion=' + encodeURIComponent(this.value) : '');
    });
    </script>
<?php endif; ?>

<?php if ($institucionId === 0): ?>
    <p class="text-muted">Selecciona una institución para continuar.</p>
<?php else: ?>
    <p class="text-muted small mb-2">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>
    <form method="post" action="<?= Url::to('/cartera/enviar') ?>" enctype="multipart/form-data" class="row g-3" style="max-width: 640px;">
        <?= Csrf::field() ?>
        <input type="hidden" name="institucion_id" value="<?= $institucionId ?>">

        <div class="col-md-6">
            <label class="form-label small requerido">Funcionario que realizó el envío</label>
            <input type="text" name="nombre_funcionario" class="form-control" required
                   value="<?= htmlspecialchars($nombreFuncionarioPorDefecto ?? '', ENT_QUOTES) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label small requerido">Correo del remitente</label>
            <input type="email" name="correo_remitente" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label small requerido">Fecha de envío</label>
            <input type="date" name="fecha_envio" class="form-control" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label small requerido">Archivo adjunto (cartera, Excel)</label>
            <input type="file" name="archivo" accept=".xlsx,.xls" class="form-control" required>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-archive me-1"></i>Guardar registro
            </button>
        </div>
    </form>
<?php endif; ?>
