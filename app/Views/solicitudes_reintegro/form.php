<?php

use App\Core\Csrf;
use App\Core\Url;

$viejo ??= [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Solicitar reintegro</h1>
    <a href="<?= Url::to('/qr/' . $token) ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<div class="card mb-3" style="max-width: 640px;">
    <div class="card-body py-3 d-flex gap-3 align-items-center">
        <?php if (!empty($bien['foto_path'])): ?>
            <img src="<?= \App\Helpers\EnlaceArchivo::url($bien['foto_path']) ?>"
                 alt="Foto de <?= htmlspecialchars($bien['descripcion'], ENT_QUOTES) ?>"
                 style="width:52px;height:52px;object-fit:cover;border-radius:6px;">
        <?php endif; ?>
        <div>
            <div class="fw-semibold"><?= htmlspecialchars($bien['descripcion'], ENT_QUOTES) ?></div>
            <div class="small text-muted mono"><?= htmlspecialchars($bien['codigo_identificacion'], ENT_QUOTES) ?></div>
            <?php if (!empty($asignacion['espacio_nombre'])): ?>
                <div class="small text-muted"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><?= htmlspecialchars($asignacion['espacio_nombre'], ENT_QUOTES) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small" style="max-width: 640px;"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="alert alert-info py-2 small" style="max-width: 640px;">
    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
    La solicitud llega al rector o al secretario. Si la aprueban, el bien se reintegra y sale de su espacio;
    podrá seguir el estado en <a href="<?= Url::to('/reintegros/solicitudes') ?>">Solicitudes de reintegro</a>.
</div>

<form method="post" action="<?= Url::to('/qr/' . $token . '/solicitar-reintegro') ?>" class="row g-3" style="max-width: 640px;">
    <?= Csrf::field() ?>

    <div class="col-12">
        <label class="form-label small requerido" for="motivoReintegro">¿Por qué debe reintegrarse este bien?</label>
        <textarea name="motivo" id="motivoReintegro" class="form-control" rows="3" required minlength="10" maxlength="500"
                  placeholder="Ej. Ya no se usa en el aula, está obsoleto o sobra después de la reubicación."><?= htmlspecialchars((string) ($viejo['motivo'] ?? ''), ENT_QUOTES) ?></textarea>
        <div class="form-text">Mínimo 10 caracteres.</div>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-send me-1" aria-hidden="true"></i>Enviar solicitud
        </button>
    </div>
</form>
