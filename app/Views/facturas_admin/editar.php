<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Editar factura</h1>
    <a href="<?= Url::to('/facturas/historial') ?>" class="btn btn-sm btn-outline-secondary">Volver al histórico</a>
</div>

<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<p class="text-muted small mb-2">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>

<form method="post" action="<?= Url::to('/facturas/' . $registro['id'] . '/actualizar') ?>" enctype="multipart/form-data" class="row g-3" style="max-width: 640px;">
    <?= Csrf::field() ?>

    <div class="col-md-6">
        <label class="form-label small requerido">Fecha de la factura</label>
        <input type="date" name="fecha_factura" class="form-control" required
               value="<?= htmlspecialchars($registro['fecha_factura'], ENT_QUOTES) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label small d-block">Archivo actual</label>
        <a href="<?= Url::to('/archivos/' . $registro['archivo_path']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
            <i class="bi bi-download me-1"></i>Descargar
        </a>
    </div>
    <div class="col-12">
        <label class="form-label small requerido">Descripción breve</label>
        <input type="text" name="descripcion" class="form-control" required
               value="<?= htmlspecialchars($registro['descripcion'], ENT_QUOTES) ?>">
    </div>
    <div class="col-12">
        <?php View::render('partials/campo_foto', [
            'nombreCampo' => 'archivo',
            'etiqueta' => 'Reemplazar archivo (opcional, PDF o foto)',
            'fotoActualUrl' => null,
            'accept' => '.pdf,image/jpeg,image/png',
        ]); ?>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Guardar cambios
        </button>
    </div>
</form>

<form method="post" action="<?= Url::to('/facturas/' . $registro['id'] . '/eliminar') ?>" class="mt-2"
      onsubmit="return prompt('Para eliminar este registro, escribe ELIMINAR (un superusuario podrá restaurarlo desde la papelera si fue un error):') === 'ELIMINAR';">
    <?= Csrf::field() ?>
    <button type="submit" class="btn btn-sm btn-outline-danger">
        <i class="bi bi-trash me-1"></i>Eliminar registro
    </button>
</form>
