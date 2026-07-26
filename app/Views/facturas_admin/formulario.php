<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Facturas</h1>
        <p class="text-muted small mb-0">Registra y conserva las facturas de procesos administrativos.</p>
    </div>
    <a href="<?= Url::to('/facturas/historial') ?>" class="btn btn-sm btn-outline-secondary">Ver histórico</a>
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
        window.location = <?= json_encode(Url::to('/facturas')) ?> + (this.value ? '?institucion=' + encodeURIComponent(this.value) : '');
    });
    </script>
<?php endif; ?>

<?php if ($institucionId === 0): ?>
    <p class="text-muted">Selecciona una institución para continuar.</p>
<?php else: ?>
    <form method="post" action="<?= Url::to('/facturas') ?>" enctype="multipart/form-data" class="row g-3" style="max-width: 480px;">
        <?= Csrf::field() ?>
        <input type="hidden" name="institucion_id" value="<?= $institucionId ?>">

        <div class="col-md-6">
            <label class="form-label small">Fecha de la factura</label>
            <input type="date" name="fecha_factura" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-12">
            <?php View::render('partials/campo_foto', [
                'nombreCampo' => 'archivo',
                'etiqueta' => 'Archivo adjunto (PDF o foto)',
                'fotoActualUrl' => null,
                'accept' => '.pdf,image/jpeg,image/png',
                'required' => true,
            ]); ?>
        </div>
        <div class="col-12">
            <label class="form-label small">Descripción breve</label>
            <input type="text" name="descripcion" class="form-control form-control-sm" required placeholder="Ej. Compra de sillas para aula 101">
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-archive me-1"></i>Guardar registro
            </button>
        </div>
    </form>
<?php endif; ?>
