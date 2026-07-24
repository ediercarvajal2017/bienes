<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Formatos de reintegro</h1>
        <p class="text-muted small mb-0">Registra la evidencia de cada formato de reintegro firmado por la Alcaldía.</p>
    </div>
    <a href="<?= Url::to('/formatos-reintegro/historial') ?>" class="btn btn-sm btn-outline-secondary">Ver histórico</a>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<?php if (Auth::esSuperusuario()): ?>
    <div class="mb-3" style="max-width: 320px;">
        <label class="form-label small">Institución</label>
        <select id="selectorInstitucion" class="form-select form-select-sm">
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
        window.location = <?= json_encode(Url::to('/formatos-reintegro')) ?> + (this.value ? '?institucion=' + encodeURIComponent(this.value) : '');
    });
    </script>
<?php endif; ?>

<?php if ($institucionId === 0): ?>
    <p class="text-muted">Selecciona una institución para continuar.</p>
<?php else: ?>
    <form method="post" action="<?= Url::to('/formatos-reintegro') ?>" enctype="multipart/form-data" class="row g-3" style="max-width: 480px;">
        <?= Csrf::field() ?>
        <input type="hidden" name="institucion_id" value="<?= $institucionId ?>">

        <div class="col-md-6">
            <label class="form-label small">Fecha del reintegro</label>
            <input type="date" name="fecha_reintegro" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label small">Archivo adjunto (PDF)</label>
            <input type="file" name="archivo" accept="application/pdf" class="form-control form-control-sm" required>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-archive me-1"></i>Guardar registro
            </button>
        </div>
    </form>
<?php endif; ?>
