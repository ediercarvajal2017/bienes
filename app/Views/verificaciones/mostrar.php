<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$urlBasePendientes = Url::to('/verificaciones/' . $jornada['id']) . ($busquedaPendientes !== '' ? '?' . http_build_query(['q' => $busquedaPendientes]) : '');
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
                <div class="fs-4 fw-semibold text-muted"><i class="bi bi-hourglass-split me-1"></i><?= (int) $totalPendientes ?></div>
                <div class="small text-muted">Pendientes</div>
            </div>
        </a>
    </div>
</div>

<?php if ($jornada['estado'] === 'en_progreso'): ?>
    <form method="post" action="<?= Url::to('/verificaciones/' . $jornada['id'] . '/cerrar') ?>" class="card mb-4" style="max-width: 480px;"
          onsubmit="return confirm('¿Cerrar la jornada de verificación? Quedan <?= (int) $totalPendientes ?> bien(es) pendiente(s) por verificar.');">
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
            <tr><th>Código</th><th>Descripción</th><th>Ubicación</th><th>Responsable(s)</th><th>Observación</th><th>Reportado por</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr>
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
                    <td data-label="Estado">
                        <?php if (!empty($d['revisada'])): ?>
                            <span class="badge text-bg-secondary d-block mb-1">Revisada</span>
                            <?php if (!empty($d['revisor_nombres'])): ?>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    por <?= htmlspecialchars($d['revisor_nombres'] . ' ' . $d['revisor_apellidos'], ENT_QUOTES) ?>
                                    <?php if (!empty($d['revisada_en'])): ?>
                                        · <?= htmlspecialchars(substr($d['revisada_en'], 0, 16), ENT_QUOTES) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge text-bg-warning">Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Acciones">
                        <?php $panelUbicacion = !empty($d['espacio_nombre']) ? 'panelTrasladar' : 'panelAsignar'; ?>
                        <div class="d-flex flex-column gap-1">
                            <a href="<?= Url::to('/bienes/' . $d['bien_id'] . '/editar') ?>?verificacion_id=<?= (int) $d['id'] ?>#<?= $panelUbicacion ?>"
                               class="btn btn-sm btn-outline-primary">Corregir ubicación</a>
                            <a href="<?= Url::to('/qr/' . $d['qr_token'] . '/baja') ?>?verificacion_id=<?= (int) $d['id'] ?>"
                               class="btn btn-sm btn-outline-danger">Dar de baja</a>
                            <?php if (empty($d['revisada'])): ?>
                                <form method="post" action="<?= Url::to('/verificaciones/' . $jornada['id'] . '/discrepancias/' . $d['id'] . '/revisada') ?>">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Marcar revisada</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h2 class="h6" id="seccion-pendientes">Bienes pendientes de verificar</h2>

<div class="mb-2" style="max-width: 420px;">
    <input type="search" id="buscadorPendientes" class="form-control form-control-sm"
           placeholder="Buscar por código, descripción o ubicación..."
           value="<?= htmlspecialchars($busquedaPendientes, ENT_QUOTES) ?>">
</div>

<?php View::render('partials/paginacion', [
    'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $totalPendientes, 'totalPaginas' => $totalPaginasPendientes,
    'opcionesPorPagina' => $opcionesPorPagina,
    'urlBase' => $urlBasePendientes,
]); ?>

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

<?php View::render('partials/paginacion', [
    'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $totalPendientes, 'totalPaginas' => $totalPaginasPendientes,
    'opcionesPorPagina' => $opcionesPorPagina,
    'urlBase' => $urlBasePendientes,
]); ?>

<script>
(function () {
    const input = document.getElementById('buscadorPendientes');
    let temporizador = null;

    input.addEventListener('input', function () {
        clearTimeout(temporizador);
        temporizador = setTimeout(function () {
            const url = new URL(window.location.href);
            const valor = input.value.trim();
            if (valor !== '') {
                url.searchParams.set('q', valor);
            } else {
                url.searchParams.delete('q');
            }
            url.searchParams.set('pagina', '1');
            url.hash = 'seccion-pendientes';
            window.location = url.toString();
        }, 450);
    });
})();
</script>
