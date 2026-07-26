<?php

use App\Core\Csrf;
use App\Core\Url;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Nueva jornada de verificación</h1>
    <a href="<?= Url::to('/verificaciones') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small" style="max-width:520px;"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<p class="text-muted small" style="max-width:560px;">
    Al iniciar la jornada, cualquier persona con acceso a un bien podrá escanear su QR y confirmar si existe físicamente
    o reportar una discrepancia. Un docente solo puede verificar los bienes de los espacios donde es responsable.
</p>

<form method="post" action="<?= Url::to('/verificaciones') ?>" class="card" style="max-width: 480px;">
    <div class="card-body">
        <?= Csrf::field() ?>
        <div class="mb-3">
            <label class="form-label small">Nombre de la jornada</label>
            <input type="text" name="nombre" class="form-control form-control-sm" placeholder="Ej. Verificación semestre 1 - 2026" required>
        </div>
        <div class="mb-3">
            <label class="form-label small">Fecha de inicio</label>
            <input type="date" name="fecha_inicio" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-clipboard2-check me-1"></i>Iniciar jornada
        </button>
    </div>
</form>
