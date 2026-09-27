<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;
use App\Models\Categoria;

?>
<div class="mb-3">
    <h1 class="h4 mb-0">Bajas de bienes</h1>
    <p class="text-muted small mb-0">Reportes enviados desde la ficha de cada bien, pendientes de aprobación.</p>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<?php View::render('partials/paginacion', [
    'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
    'opcionesPorPagina' => $opcionesPorPagina,
    'urlBase' => Url::to('/bajas'),
]); ?>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle bg-white tabla-cards">
        <thead>
        <tr>
            <th></th>
            <th>Bien</th>
            <th>Estado reportado</th>
            <th>Ubicación</th>
            <th>Reportado por</th>
            <th>Fecha</th>
            <th>Estado</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if ($bajas === []): ?>
            <?php View::render('partials/tabla_vacia', ['colspan' => 8, 'icono' => 'check2-circle',
                'mensaje' => 'No hay reportes de baja.']); ?>
        <?php endif; ?>
        <?php foreach ($bajas as $b): ?>
            <?php
            $admiteBaja = $b['categoria_nombre'] === Categoria::NOMBRE_CATEGORIA_PROTEGIDA;
            $pendiente = $b['estado'] === 'pendiente';
            ?>
            <tr>
                <td>
                    <?php if (!empty($b['foto_path'])): ?>
                        <img src="<?= Url::to('/archivos/' . $b['foto_path']) ?>"
                             alt="Foto del reporte de baja de <?= htmlspecialchars($b['bien_descripcion'], ENT_QUOTES) ?>"
                             style="width:36px;height:36px;object-fit:cover;border-radius:4px;" loading="lazy">
                    <?php endif; ?>
                </td>
                <td data-label="Bien">
                    <?= htmlspecialchars($b['bien_descripcion'], ENT_QUOTES) ?>
                    <div class="small text-muted mono"><?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?></div>
                    <?php if ($pendiente && !$admiteBaja): ?>
                        <div class="small text-danger mt-1">
                            <i class="bi bi-exclamation-triangle me-1"></i>No admite baja (categoría "<?= htmlspecialchars($b['categoria_nombre'] ?? 'sin categoría', ENT_QUOTES) ?>") —
                            <a href="<?= Url::to('/bienes/' . $b['bien_id'] . '/editar') ?>">recategorice a "<?= htmlspecialchars(Categoria::NOMBRE_CATEGORIA_PROTEGIDA, ENT_QUOTES) ?>"</a> o rechace el reporte.
                        </div>
                    <?php endif; ?>
                </td>
                <td data-label="Estado reportado"><?= htmlspecialchars($b['estado_reportado'], ENT_QUOTES) ?></td>
                <td class="text-muted" data-label="Ubicación"><?= htmlspecialchars($b['ubicacion'] ?? '—', ENT_QUOTES) ?></td>
                <td class="text-muted" data-label="Reportado por"><?= htmlspecialchars($b['nombres'] . ' ' . $b['apellidos'], ENT_QUOTES) ?></td>
                <td class="mono small" data-label="Fecha"><?= htmlspecialchars(substr($b['fecha_reporte'], 0, 10), ENT_QUOTES) ?></td>
                <td data-label="Estado">
                    <?php if ($b['estado'] === 'aprobada'): ?>
                        <span class="badge text-bg-danger">Aprobada</span>
                    <?php elseif ($b['estado'] === 'rechazada'): ?>
                        <span class="badge text-bg-secondary">Rechazada</span>
                    <?php else: ?>
                        <span class="badge text-bg-warning">Pendiente</span>
                    <?php endif; ?>
                    <?php if (!$pendiente && !empty($b['resuelta_por_nombre'])): ?>
                        <div class="small text-muted mt-1">
                            por <?= htmlspecialchars($b['resuelta_por_nombre'], ENT_QUOTES) ?>
                            <?php if (!empty($b['resuelta_en'])): ?>· <?= htmlspecialchars(substr($b['resuelta_en'], 0, 10), ENT_QUOTES) ?><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($b['estado'] === 'rechazada' && !empty($b['motivo_rechazo'])): ?>
                        <div class="small text-muted">Motivo: <?= htmlspecialchars($b['motivo_rechazo'], ENT_QUOTES) ?></div>
                    <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                    <?php if ($pendiente && (Auth::esSuperusuario() || Auth::tienePermiso('bajas.aprobar'))): ?>
                        <?php if ($admiteBaja): ?>
                            <form method="post" action="<?= Url::to('/bajas/' . $b['id'] . '/aprobar') ?>" class="d-inline"
                                  data-confirmar="¿Aprobar esta baja? El bien pasará a estado &#x27;Dado de baja&#x27;.">
                                <?= Csrf::field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-success">Aprobar</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= Url::to('/bajas/' . $b['id'] . '/rechazar') ?>" class="d-inline"
                              onsubmit="var m = prompt('Motivo del rechazo (queda registrado en el historial):'); if (!m || !m.trim()) { return false; } this.motivo_rechazo.value = m.trim(); return true;">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="motivo_rechazo" value="">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Rechazar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php View::render('partials/paginacion', [
    'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
    'opcionesPorPagina' => $opcionesPorPagina,
    'urlBase' => Url::to('/bajas'),
]); ?>
