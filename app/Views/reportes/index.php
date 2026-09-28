<?php

use App\Core\Auth;
use App\Core\Url;
use App\Models\Institucion;

$instituciones = Auth::esSuperusuario() ? Institucion::listadoParaSelect() : [];
?>
<h1 class="h4 mb-3">Reportes</h1>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small" role="alert" style="max-width: 780px;"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<?php if (Auth::esSuperusuario()): ?>
    <div class="mb-3" style="max-width: 320px;">
        <label for="selectorInstitucion" class="form-label small">Institución a exportar</label>
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

    <?php if (!empty($puedeExportarTodo)): ?>
        <?php $baseExportacion = Url::to('/reportes/exportacion-completa.zip'); ?>
        <div class="col-12">
            <section class="card" aria-labelledby="tituloExportacion">
                <div class="card-body">
                    <h2 class="h6" id="tituloExportacion"><i class="bi bi-box-arrow-down me-1" aria-hidden="true"></i>Descargar toda la información</h2>
                    <p class="small text-muted mb-2">
                        Un archivo .zip con todo lo registrado en MIA<?= Auth::esSuperusuario() ? ' para la institución elegida' : '' ?> y sus sedes:
                        bienes, asignaciones, movimientos, bajas, reintegros, espacios, usuarios, verificaciones, documentos y
                        auditoría, en un libro de Excel con una hoja por tema. Sirve como copia propia de la institución.
                    </p>
                    <p class="small text-muted mb-3">
                        Contiene datos personales: guárdalo en un lugar seguro. No incluye contraseñas.
                        La opción con fotos y documentos puede tardar varios minutos y pesar bastante.
                    </p>
                    <?php if (Auth::esSuperusuario()): ?>
                        <p class="small mb-2 aviso-elegir-institucion"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Primero elige arriba la institución a exportar.</p>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-primary enlace-reporte enlace-exportacion<?= Auth::esSuperusuario() ? ' disabled" aria-disabled="true' : '' ?>" data-base="<?= $baseExportacion ?>" data-descarga="Preparando…" href="<?= $baseExportacion ?>">
                            <i class="bi bi-file-earmark-zip me-1" aria-hidden="true"></i>Solo los datos
                        </a>
                        <a class="btn btn-sm btn-outline-primary enlace-reporte enlace-exportacion<?= Auth::esSuperusuario() ? ' disabled" aria-disabled="true' : '' ?>" data-base="<?= $baseExportacion ?>?archivos=1" data-descarga="Preparando… puede tardar" href="<?= $baseExportacion ?>?archivos=1">
                            <i class="bi bi-images me-1" aria-hidden="true"></i>Datos con fotos y documentos
                        </a>
                    </div>
                </div>
            </section>
        </div>
    <?php endif; ?>
</div>

<?php if (Auth::esSuperusuario()): ?>
<script>
document.getElementById('selectorInstitucion').addEventListener('change', function () {
    const valor = this.value;
    document.querySelectorAll('.enlace-reporte').forEach(function (enlace) {
        const base = enlace.dataset.base;
        const union = base.indexOf('?') === -1 ? '?' : '&';
        enlace.href = valor ? base + union + 'institucion=' + encodeURIComponent(valor) : base;
    });
    // La descarga completa es de UNA institución: sin elegirla, sus botones quedan inactivos.
    document.querySelectorAll('.enlace-exportacion').forEach(function (enlace) {
        enlace.classList.toggle('disabled', !valor);
        enlace.setAttribute('aria-disabled', valor ? 'false' : 'true');
    });
    const aviso = document.querySelector('.aviso-elegir-institucion');
    if (aviso) {
        aviso.hidden = Boolean(valor);
    }
});
</script>
<?php endif; ?>
