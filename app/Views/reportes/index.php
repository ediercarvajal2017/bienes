<?php

use App\Core\Auth;
use App\Core\Url;
use App\Models\Institucion;

$instituciones = Auth::esSuperusuario() ? Institucion::listadoParaSelect() : [];
?>
<h1 class="h4 mb-3">Reportes</h1>

<?php if (Auth::esSuperusuario()): ?>
    <div class="mb-3" style="max-width: 320px;">
        <label class="form-label small">Institución a exportar</label>
        <select id="selectorInstitucion" class="form-select form-select-sm selector-buscable">
            <option value="">Todas las instituciones</option>
            <?php foreach ($instituciones as $i): ?>
                <option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>

<div class="row g-3" style="max-width: 780px;">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6">Cartera de bienes</h2>
                <p class="small text-muted mb-3">Código, descripción, ubicación, responsable, valor y fecha de ingreso de todos los bienes.</p>
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-primary enlace-reporte" data-base="<?= Url::to('/reportes/cartera.xlsx') ?>" data-descarga="Generando…" href="<?= Url::to('/reportes/cartera.xlsx') ?>">
                        <i class="bi bi-file-earmark-excel me-1"></i>.xlsx
                    </a>
                    <a class="btn btn-sm btn-outline-secondary enlace-reporte" data-base="<?= Url::to('/reportes/cartera.csv') ?>" data-descarga="Generando…" href="<?= Url::to('/reportes/cartera.csv') ?>">.csv</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100 border-warning-subtle">
            <div class="card-body">
                <span class="badge text-bg-warning mb-2">AÚN NO SE HAN DEVUELTO</span>
                <h2 class="h6">Bienes asignados pendientes de devolver</h2>
                <p class="small text-muted mb-3">Bienes que alguien tiene actualmente en su poder y todavía <strong>no</strong> han sido reintegrados. Antes se llamaba "Planilla de reintegros pendientes".</p>
                <a class="btn btn-sm btn-primary enlace-reporte" data-base="<?= Url::to('/reportes/reintegros.xlsx') ?>" data-descarga="Generando…" href="<?= Url::to('/reportes/reintegros.xlsx') ?>">
                    <i class="bi bi-file-earmark-excel me-1"></i>.xlsx
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100 border-success-subtle">
            <div class="card-body">
                <span class="badge text-bg-success mb-2">YA REINTEGRADOS</span>
                <h2 class="h6">Historial de bienes reintegrados</h2>
                <p class="small text-muted mb-3">Bienes que <strong>ya fueron devueltos</strong>, con la fecha exacta del reintegro, el destino y quién lo registró.</p>
                <a class="btn btn-sm btn-primary enlace-reporte" data-base="<?= Url::to('/reportes/reintegros-historial.xlsx') ?>" data-descarga="Generando…" href="<?= Url::to('/reportes/reintegros-historial.xlsx') ?>">
                    <i class="bi bi-file-earmark-excel me-1"></i>.xlsx
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (Auth::esSuperusuario()): ?>
<script>
document.getElementById('selectorInstitucion').addEventListener('change', function () {
    const valor = this.value;
    document.querySelectorAll('.enlace-reporte').forEach(function (enlace) {
        const base = enlace.dataset.base;
        enlace.href = valor ? base + '?institucion=' + encodeURIComponent(valor) : base;
    });
});
</script>
<?php endif; ?>
