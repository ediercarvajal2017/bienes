<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

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

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle bg-white">
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
        <?php foreach ($espacios as $e): ?>
            <tr>
                <td class="text-muted mono"><?= htmlspecialchars($e['codigo'], ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($e['nombre'], ENT_QUOTES) ?></td>
                <td class="text-muted small">
                    <?= !empty($e['responsables_nombres']) ? htmlspecialchars($e['responsables_nombres'], ENT_QUOTES) : '—' ?>
                </td>
                <td>
                    <?php if ((int) $e['activo'] === 1): ?>
                        <span class="badge badge-estado-activo">Activo</span>
                    <?php else: ?>
                        <span class="badge badge-estado-dado_de_baja">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                    <?php if (Auth::esSuperusuario() || Auth::tienePermiso('espacios.editar')): ?>
                        <a href="<?= Url::to('/espacios/' . $e['id'] . '/editar') ?>" class="btn btn-sm btn-outline-secondary">Editar</a>
                        <form method="post" action="<?= Url::to('/espacios/' . $e['id'] . '/estado') ?>" class="d-inline">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-<?= (int) $e['activo'] === 1 ? 'danger' : 'success' ?>">
                                <?= (int) $e['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <form method="post" action="<?= Url::to('/espacios/' . $e['id'] . '/eliminar') ?>" class="d-inline"
                              onsubmit="return confirm('¿Eliminar este espacio de forma permanente? Solo es posible si no tiene asignaciones ni movimientos registrados.');">
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
    'urlBase' => Url::to('/espacios'),
]); ?>
