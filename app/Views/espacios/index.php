<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$urlBasePaginacion = Url::to('/espacios') . ($busqueda !== '' ? '?q=' . urlencode($busqueda) : '');
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

<div class="mb-3" style="max-width: 420px;">
    <label class="form-label small mb-1">Buscar</label>
    <input type="search" id="buscador" class="form-control form-control-sm"
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
                        <form method="post" action="<?= Url::to('/espacios/' . $e['id'] . '/estado') ?>" class="d-inline">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-<?= (int) $e['activo'] === 1 ? 'danger' : 'success' ?>">
                                <?= (int) $e['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <form method="post" action="<?= Url::to('/espacios/' . $e['id'] . '/eliminar') ?>" class="d-inline"
                              onsubmit="return confirm('¿Eliminar este espacio? Solo es posible si no tiene asignaciones ni movimientos registrados. Un superusuario podrá restaurarlo desde la papelera si fue un error.');">
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
    const input = document.getElementById('buscador');
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
            window.location = url.toString();
        }, 450);
    });
})();
</script>
