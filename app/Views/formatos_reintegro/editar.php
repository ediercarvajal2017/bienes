<?php

use App\Core\Csrf;
use App\Core\Url;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Editar formato de reintegro</h1>
    <a href="<?= Url::to('/formatos-reintegro/historial') ?>" class="btn btn-sm btn-outline-secondary">Volver al histórico</a>
</div>

<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<form method="post" action="<?= Url::to('/formatos-reintegro/' . $registro['id'] . '/actualizar') ?>" enctype="multipart/form-data" class="row g-3" style="max-width: 480px;">
    <?= Csrf::field() ?>

    <div class="col-md-6">
        <label class="form-label small">Fecha del reintegro</label>
        <input type="date" name="fecha_reintegro" class="form-control form-control-sm" required
               value="<?= htmlspecialchars($registro['fecha_reintegro'], ENT_QUOTES) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label small d-block">Archivo actual</label>
        <a href="<?= Url::to('/archivos/' . $registro['archivo_path']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
            <i class="bi bi-download me-1"></i>Descargar
        </a>
    </div>
    <div class="col-12">
        <label class="form-label small">Reemplazar archivo (opcional, PDF)</label>
        <input type="file" name="archivo" accept="application/pdf" class="form-control form-control-sm">
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Guardar cambios
        </button>
    </div>
</form>

<form method="post" action="<?= Url::to('/formatos-reintegro/' . $registro['id'] . '/eliminar') ?>" class="mt-2"
      onsubmit="return confirm('¿Eliminar este registro de forma permanente? Esta acción no se puede deshacer.');">
    <?= Csrf::field() ?>
    <button type="submit" class="btn btn-sm btn-outline-danger">
        <i class="bi bi-trash me-1"></i>Eliminar registro
    </button>
</form>
