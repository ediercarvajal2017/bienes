<?php

use App\Core\Csrf;
use App\Core\Url;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Editar registro de cartera</h1>
    <a href="<?= Url::to('/cartera/enviados') ?>" class="btn btn-sm btn-outline-secondary">Volver al histórico</a>
</div>

<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<p class="text-muted small mb-2">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>

<form method="post" action="<?= Url::to('/cartera/' . $registro['id'] . '/actualizar') ?>" enctype="multipart/form-data" class="row g-3" style="max-width: 640px;">
    <?= Csrf::field() ?>

    <div class="col-md-6">
        <label class="form-label small requerido">Funcionario que realizó el envío</label>
        <input type="text" name="nombre_funcionario" class="form-control" required
               value="<?= htmlspecialchars($registro['nombre_funcionario'], ENT_QUOTES) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label small requerido">Correo del remitente</label>
        <input type="email" name="correo_remitente" class="form-control" required
               value="<?= htmlspecialchars($registro['correo_remitente'], ENT_QUOTES) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label small requerido">Fecha de envío</label>
        <input type="date" name="fecha_envio" class="form-control" required
               value="<?= htmlspecialchars($registro['fecha_envio'], ENT_QUOTES) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label small d-block">Archivo actual</label>
        <a href="<?= Url::to('/archivos/' . $registro['archivo_path']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
            <i class="bi bi-download me-1"></i>Descargar
        </a>
    </div>
    <div class="col-12">
        <label class="form-label small">Reemplazar archivo (opcional, Excel)</label>
        <input type="file" name="archivo" accept=".xlsx,.xls" class="form-control">
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Guardar cambios
        </button>
    </div>
</form>

<form method="post" action="<?= Url::to('/cartera/' . $registro['id'] . '/eliminar') ?>" class="mt-2"
      onsubmit="return confirm('¿Eliminar este registro de forma permanente? Esta acción no se puede deshacer.');">
    <?= Csrf::field() ?>
    <button type="submit" class="btn btn-sm btn-outline-danger">
        <i class="bi bi-trash me-1"></i>Eliminar registro
    </button>
</form>
