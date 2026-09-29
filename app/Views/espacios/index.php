<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$urlBasePaginacion = Url::to('/espacios') . ($busqueda !== '' ? '?q=' . urlencode($busqueda) : '');
$puedeCrearEspacio = Auth::esSuperusuario() || Auth::tienePermiso('espacios.crear');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Espacios</h1>
    <?php if (Auth::esSuperusuario() || Auth::tienePermiso('espacios.crear')): ?>
        <div class="d-flex gap-2">
            <a href="<?= Url::to('/espacios/carga-masiva') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Carga masiva
            </a>
            <a href="<?= Url::to('/espacios/crear') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Nuevo espacio
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small" role="alert" style="max-width: 780px;">
        <?= htmlspecialchars($error, ENT_QUOTES) ?>
        <?php if (!empty($errorDetalle['bienes'])): ?>
            <ul class="mb-1 mt-2 ps-3">
                <?php foreach ($errorDetalle['bienes'] as $b): ?>
                    <li>
                        <a href="<?= Url::to('/bienes/' . (int) $b['id'] . '/editar') ?>" class="alert-link mono"><?= htmlspecialchars((string) $b['codigo'], ENT_QUOTES) ?></a>
                        · <?= htmlspecialchars((string) $b['descripcion'], ENT_QUOTES) ?>
                    </li>
                <?php endforeach; ?>
                <?php if ((int) ($errorDetalle['mas'] ?? 0) > 0): ?>
                    <li class="list-unstyled text-body-secondary">y <?= (int) $errorDetalle['mas'] ?> más.</li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
        <?php if (!empty($errorDetalle['consejo'])): ?>
            <div class="mt-1"><?= htmlspecialchars((string) $errorDetalle['consejo'], ENT_QUOTES) ?></div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="mb-3" style="max-width: 420px;">
    <label for="buscador" class="form-label small mb-1">Buscar</label>
    <input type="search" id="buscador" data-buscar="q" class="form-control form-control-sm"
           placeholder="Buscar por código o nombre..."
           value="<?= htmlspecialchars($busqueda, ENT_QUOTES) ?>">
</div>

<?php View::render('partials/paginacion', [
    'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
    'opcionesPorPagina' => $opcionesPorPagina,
    'urlBase' => $urlBasePaginacion,
]); ?>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle bg-white tabla-cards">
        <thead>
        <tr>
            <th>N.º</th>
            <th>Nombre</th>
            <th>Responsable(s)</th>
            <th>Estado</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($espacios)): ?>
            <?php View::render('partials/tabla_vacia', [
                'colspan' => 5,
                'icono' => 'signpost-2',
                'mensaje' => $busqueda !== ''
                    ? 'Ningún espacio coincide con la búsqueda.'
                    : 'Todavía no hay espacios registrados.',
                'ctaTexto' => $busqueda !== '' ? 'Quitar búsqueda' : ($puedeCrearEspacio ? 'Nuevo espacio' : null),
                'ctaUrl' => $busqueda !== '' ? Url::to('/espacios') : ($puedeCrearEspacio ? Url::to('/espacios/crear') : null),
            ]); ?>
        <?php endif; ?>
        <?php foreach ($espacios as $e): ?>
            <tr>
                <td class="text-muted mono" data-label="N.º"><?= htmlspecialchars($e['codigo'], ENT_QUOTES) ?></td>
                <td data-label="Nombre"><?= htmlspecialchars($e['nombre'], ENT_QUOTES) ?></td>
                <td class="text-muted small" data-label="Responsable(s)">
                    <?= !empty($e['responsables_nombres']) ? htmlspecialchars($e['responsables_nombres'], ENT_QUOTES) : '—' ?>
                </td>
                <td data-label="Estado">
                    <?php if ((int) $e['activo'] === 1): ?>
                        <span class="badge badge-activo">Activo</span>
                    <?php else: ?>
                        <span class="badge badge-inactivo">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                    <?php if (Auth::esSuperusuario() || Auth::tienePermiso('espacios.editar')): ?>
                        <a href="<?= Url::to('/espacios/' . $e['id'] . '/editar') ?>" class="btn btn-sm btn-outline-secondary">Editar</a>
                        <form method="post" action="<?= Url::to('/espacios/' . $e['id'] . '/estado') ?>" class="d-inline"
                              data-confirmar="<?= (int) $e['activo'] === 1 ? '¿Desactivar este espacio? Ya no se le podrán asignar bienes.' : '¿Activar este espacio?' ?>">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-<?= (int) $e['activo'] === 1 ? 'danger' : 'success' ?>">
                                <?= (int) $e['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <form method="post" action="<?= Url::to('/espacios/' . $e['id'] . '/eliminar') ?>" class="d-inline"
                              data-confirmar="¿Eliminar este espacio? Solo es posible si no tiene asignaciones ni movimientos registrados. Un superusuario podrá restaurarlo desde la papelera si fue un error.">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
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
    'urlBase' => $urlBasePaginacion,
]); ?>

<script>
(function () {
})();
</script>
