<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$verificacionId ??= null;
$descripcionSugerida ??= '';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Reportar baja</h1>
    <a href="<?= Url::to('/qr/' . $token) ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<div class="card mb-3" style="max-width: 560px;">
    <div class="card-body py-3 d-flex gap-3 align-items-center">
        <?php if (!empty($bien['foto_path'])): ?>
            <img src="<?= Url::to('/archivos/' . $bien['foto_path']) ?>" style="width:52px;height:52px;object-fit:cover;border-radius:6px;">
        <?php endif; ?>
        <div>
            <div class="fw-semibold"><?= htmlspecialchars($bien['descripcion'], ENT_QUOTES) ?></div>
            <div class="small text-muted mono"><?= htmlspecialchars($bien['codigo_identificacion'], ENT_QUOTES) ?></div>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small" style="max-width: 560px;"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<?php if ($verificacionId !== null): ?>
    <div class="alert alert-info py-2 small" style="max-width: 560px;">
        <i class="bi bi-info-circle me-1"></i>Este reporte viene de una discrepancia detectada en una jornada de verificación.
    </div>
<?php endif; ?>

<form method="post" action="<?= Url::to('/qr/' . $token . '/baja') ?>" enctype="multipart/form-data" class="row g-3" style="max-width: 560px;">
    <?= Csrf::field() ?>
    <?php if ($verificacionId !== null): ?>
        <input type="hidden" name="verificacion_id" value="<?= (int) $verificacionId ?>">
    <?php endif; ?>

    <div class="col-12">
        <label class="form-label small">Estado del bien</label>
        <input type="text" name="estado_reportado" class="form-control" required list="sugerenciasEstado" placeholder="Ej. Dañado, Perdido, Obsoleto...">
        <datalist id="sugerenciasEstado">
            <option value="Dañado">
            <option value="Perdido">
            <option value="Deteriorado por uso">
            <option value="Obsoleto">
        </datalist>
    </div>

    <div class="col-12">
        <label class="form-label small">Ubicación</label>
        <input type="text" name="ubicacion" class="form-control"
               value="<?= htmlspecialchars($asignacion['espacio_nombre'] ?? '', ENT_QUOTES) ?>">
    </div>

    <div class="col-12">
        <label class="form-label small">Descripción de la baja</label>
        <textarea name="descripcion" class="form-control" rows="3" required placeholder="Qué pasó y por qué se solicita la baja"><?= htmlspecialchars($descripcionSugerida, ENT_QUOTES) ?></textarea>
    </div>

    <div class="col-12">
        <?php View::render('partials/campo_foto', [
            'nombreCampo' => 'foto',
            'etiqueta' => 'Fotografía del estado actual',
            'fotoActualUrl' => null,
        ]); ?>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-outline-danger">Enviar reporte</button>
        <span class="small text-muted ms-2">Quedará pendiente de aprobación.</span>
    </div>
</form>
