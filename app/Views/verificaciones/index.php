<?php

use App\Core\Auth;
use App\Core\Url;
use App\Core\View;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Verificación física de bienes</h1>
        <p class="text-muted small mb-0">Compara el inventario del sistema contra la existencia física de cada bien.</p>
    </div>
    <?php if (Auth::esSuperusuario() || Auth::tienePermiso('verificaciones.gestionar')): ?>
        <?php if (empty($jornadaActiva)): ?>
            <a href="<?= Url::to('/verificaciones/crear') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Nueva jornada
            </a>
        <?php else: ?>
            <a href="<?= Url::to('/verificaciones/' . $jornadaActiva['id']) ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-clipboard2-check me-1"></i>Ir a la jornada en progreso
            </a>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<?php if (empty($jornadas)): ?>
    <p class="text-muted">Todavía no se ha iniciado ninguna jornada de verificación.</p>
<?php else: ?>
    <?php View::render('partials/paginacion', [
        'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
        'opcionesPorPagina' => $opcionesPorPagina,
        'urlBase' => Url::to('/verificaciones'),
    ]); ?>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle bg-white tabla-cards">
            <thead>
            <tr><th>Nombre</th><th>Fecha de inicio</th><th>Creada por</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($jornadas as $j): ?>
                <tr>
                    <td data-label="Nombre"><?= htmlspecialchars($j['nombre'], ENT_QUOTES) ?></td>
                    <td class="mono small" data-label="Fecha de inicio"><?= htmlspecialchars($j['fecha_inicio'], ENT_QUOTES) ?></td>
                    <td class="text-muted small" data-label="Creada por"><?= htmlspecialchars($j['creador_nombres'] . ' ' . $j['creador_apellidos'], ENT_QUOTES) ?></td>
                    <td data-label="Estado">
                        <?php if ($j['estado'] === 'en_progreso'): ?>
                            <span class="badge badge-estado-en_reparacion"><i class="bi bi-hourglass-split me-1"></i>En progreso</span>
                        <?php else: ?>
                            <span class="badge text-bg-light border"><i class="bi bi-check2-circle me-1"></i>Cerrada</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="<?= Url::to('/verificaciones/' . $j['id']) ?>" class="btn btn-sm btn-outline-secondary">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php View::render('partials/paginacion', [
        'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
        'opcionesPorPagina' => $opcionesPorPagina,
        'urlBase' => Url::to('/verificaciones'),
    ]); ?>
<?php endif; ?>
