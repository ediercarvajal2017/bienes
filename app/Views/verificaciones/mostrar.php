<?php

use App\Core\Csrf;
use App\Core\Url;

$pendientesCount = count($pendientes);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
    <h1 class="h4 mb-0"><?= htmlspecialchars($jornada['nombre'], ENT_QUOTES) ?></h1>
    <a href="<?= Url::to('/verificaciones') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>
<p class="text-muted small mb-3">
    Iniciada el <?= htmlspecialchars($jornada['fecha_inicio'], ENT_QUOTES) ?>
    <?php if ($jornada['estado'] === 'cerrada'): ?>
        · Cerrada el <?= htmlspecialchars(substr($jornada['fecha_cierre'], 0, 16), ENT_QUOTES) ?>
    <?php endif; ?>
</p>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<div class="row g-3 mb-4" style="max-width: 760px;">
    <div class="col-6 col-md-3">
        <div class="card h-100">
            <div class="card-body text-center py-3">
                <div class="fs-4 fw-semibold"><?= (int) $universo ?></div>
                <div class="small text-muted">Bienes a verificar</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <a href="#seccion-ok" class="card h-100 text-decoration-none text-body">
            <div class="card-body text-center py-3">
                <div class="fs-4 fw-semibold text-success"><i class="bi bi-check2-circle me-1"></i><?= (int) $verificadosOk ?></div>
                <div class="small text-muted">Verificados sin novedad</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="#seccion-discrepancia" class="card h-100 text-decoration-none text-body">
            <div class="card-body text-center py-3">
                <div class="fs-4 fw-semibold text-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= (int) $verificadosDiscrepancia ?></div>
                <div class="small text-muted">Con discrepancia</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="#seccion-pendientes" class="card h-100 text-decoration-none text-body">
            <div class="card-body text-center py-3">
                <div class="fs-4 fw-semibold text-muted"><i class="bi bi-hourglass-split me-1"></i><?= $pendientesCount ?></div>
                <div class="small text-muted">Pendientes</div>
            </div>
        </a>
    </div>
</div>

<?php if ($jornada['estado'] === 'en_progreso'): ?>
    <form method="post" action="<?= Url::to('/verificaciones/' . $jornada['id'] . '/cerrar') ?>" class="card mb-4" style="max-width: 480px;"
          onsubmit="return confirm('¿Cerrar la jornada de verificación? Quedan <?= $pendientesCount ?> bien(es) pendiente(s) por verificar.');">
        <div class="card-body">
            <?= Csrf::field() ?>
            <label class="form-label small">Observaciones de cierre (opcional)</label>
            <textarea name="observaciones" class="form-control form-control-sm mb-2" rows="2"></textarea>
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-lock me-1"></i>Cerrar jornada
            </button>
        </div>
    </form>
<?php elseif (!empty($jornada['observaciones_cierre'])): ?>
    <div class="alert alert-secondary py-2 small" style="max-width:560px;">
        <strong>Observaciones de cierre:</strong> <?= htmlspecialchars($jornada['observaciones_cierre'], ENT_QUOTES) ?>
    </div>
<?php endif; ?>

<h2 class="h6" id="seccion-ok">Bienes verificados sin novedad</h2>
<?php if (empty($verificadosOkDetalle)): ?>
    <p class="text-muted small">Ninguno hasta ahora.</p>
<?php else: ?>
    <div class="table-responsive mb-4">
        <table class="table table-sm bg-white tabla-cards">
            <thead>
            <tr><th>Código</th><th>Descripción</th><th>Ubicación</th><th>Responsable(s)</th><th>Verificado por</th><th>Fecha</th></tr>
            </thead>
            <tbody>
            <?php foreach ($verificadosOkDetalle as $v): ?>
                <tr>
                    <td class="mono small" data-label="Código"><?= htmlspecialchars($v['codigo_identificacion'], ENT_QUOTES) ?></td>
                    <td data-label="Descripción"><?= htmlspecialchars($v['descripcion'], ENT_QUOTES) ?></td>
                    <td class="text-muted small" data-label="Ubicación"><?= !empty($v['espacio_nombre']) ? htmlspecialchars($v['espacio_nombre'], ENT_QUOTES) : 'Sin asignar' ?></td>
                    <td class="text-muted small" data-label="Responsable(s)"><?= !empty($v['responsables_nombres']) ? htmlspecialchars($v['responsables_nombres'], ENT_QUOTES) : '—' ?></td>
                    <td class="small" data-label="Verificado por"><?= htmlspecialchars($v['nombres'] . ' ' . $v['apellidos'], ENT_QUOTES) ?></td>
                    <td class="mono small text-muted" data-label="Fecha"><?= htmlspecialchars(substr($v['updated_at'], 0, 16), ENT_QUOTES) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h2 class="h6" id="seccion-discrepancia">Bienes con discrepancia</h2>
<?php if (empty($discrepancias)): ?>
    <p class="text-muted small">Ninguna hasta ahora.</p>
<?php else: ?>
    <div class="table-responsive mb-4">
        <table class="table table-sm bg-white tabla-cards">
            <thead>
            <tr><th>Código</th><th>Descripción</th><th>Ubicación</th><th>Responsable(s)</th><th>Observación</th><th>Reportado por</th><th>Fecha</th></tr>
            </thead>
            <tbody>
            <?php foreach ($discrepancias as $d): ?>
                <tr>
                    <td class="mono small" data-label="Código"><?= htmlspecialchars($d['codigo_identificacion'], ENT_QUOTES) ?></td>
                    <td data-label="Descripción"><?= htmlspecialchars($d['descripcion'], ENT_QUOTES) ?></td>
                    <td class="text-muted small" data-label="Ubicación"><?= !empty($d['espacio_nombre']) ? htmlspecialchars($d['espacio_nombre'], ENT_QUOTES) : 'Sin asignar' ?></td>
                    <td class="text-muted small" data-label="Responsable(s)"><?= !empty($d['responsables_nombres']) ? htmlspecialchars($d['responsables_nombres'], ENT_QUOTES) : '—' ?></td>
                    <td class="small" data-label="Observación"><?= htmlspecialchars($d['observaciones'] ?? '', ENT_QUOTES) ?></td>
                    <td class="text-muted small" data-label="Reportado por"><?= htmlspecialchars($d['nombres'] . ' ' . $d['apellidos'], ENT_QUOTES) ?></td>
                    <td class="mono small text-muted" data-label="Fecha"><?= htmlspecialchars(substr($d['updated_at'], 0, 16), ENT_QUOTES) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h2 class="h6" id="seccion-pendientes">Bienes pendientes de verificar</h2>
<?php if (empty($pendientes)): ?>
    <p class="text-muted small">No hay bienes pendientes.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm bg-white tabla-cards">
            <thead>
            <tr><th>Código</th><th>Descripción</th><th>Ubicación</th></tr>
            </thead>
            <tbody>
            <?php foreach ($pendientes as $p): ?>
                <tr>
                    <td class="mono small" data-label="Código"><?= htmlspecialchars($p['codigo_identificacion'], ENT_QUOTES) ?></td>
                    <td data-label="Descripción"><?= htmlspecialchars($p['descripcion'], ENT_QUOTES) ?></td>
                    <td class="text-muted small" data-label="Ubicación"><?= !empty($p['espacio_nombre']) ? htmlspecialchars($p['espacio_nombre'], ENT_QUOTES) : 'Sin asignar' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
