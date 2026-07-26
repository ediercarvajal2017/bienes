<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

?>
<div class="mb-3">
    <h1 class="h4 mb-0">Bajas de bienes</h1>
    <p class="text-muted small mb-0">Reportes enviados desde la ficha de cada bien, pendientes de aprobación.</p>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div>
<?php endif; ?>

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
            <th></th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($bajas as $b): ?>
            <tr>
                <td>
                    <?php if (!empty($b['foto_path'])): ?>
                        <img src="<?= Url::to('/archivos/' . $b['foto_path']) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;">
                    <?php endif; ?>
                </td>
                <td data-label="Bien">
                    <?= htmlspecialchars($b['bien_descripcion'], ENT_QUOTES) ?>
                    <div class="small text-muted mono"><?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?></div>
                </td>
                <td data-label="Estado reportado"><?= htmlspecialchars($b['estado_reportado'], ENT_QUOTES) ?></td>
                <td class="text-muted" data-label="Ubicación"><?= htmlspecialchars($b['ubicacion'] ?? '—', ENT_QUOTES) ?></td>
                <td class="text-muted" data-label="Reportado por"><?= htmlspecialchars($b['nombres'] . ' ' . $b['apellidos'], ENT_QUOTES) ?></td>
                <td class="mono small" data-label="Fecha"><?= htmlspecialchars(substr($b['fecha_reporte'], 0, 10), ENT_QUOTES) ?></td>
                <td data-label="Estado">
                    <?php if ((int) $b['aprobada'] === 1): ?>
                        <span class="badge badge-estado-dado_de_baja">Aprobada</span>
                    <?php else: ?>
                        <span class="badge badge-estado-en_reparacion">Pendiente</span>
                    <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                    <?php if ((int) $b['aprobada'] === 0 && (Auth::esSuperusuario() || Auth::tienePermiso('bajas.aprobar'))): ?>
                        <form method="post" action="<?= Url::to('/bajas/' . $b['id'] . '/aprobar') ?>" class="d-inline">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-success">Aprobar</button>
                        </form>
                        <form method="post" action="<?= Url::to('/bajas/' . $b['id'] . '/rechazar') ?>" class="d-inline">
                            <?= Csrf::field() ?>
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
    'urlBase' => Url::to('/bajas'),
]); ?>
