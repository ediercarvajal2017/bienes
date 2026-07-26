<?php

use App\Core\Auth;
use App\Core\Url;
use App\Core\View;

$urlBasePaginacion = Url::to('/bienes') . ($busqueda !== '' ? '?' . http_build_query(['q' => $busqueda]) : '');

$etiquetasEstado = [
    'activo' => 'Activo',
    'reintegrado' => 'Reintegrado',
    'en_reparacion' => 'En reparación',
    'dado_de_baja' => 'Dado de baja',
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Bienes</h1>
    <div class="d-flex gap-2">
        <?php if (Auth::esSuperusuario() || Auth::tienePermiso('bienes.ver')): ?>
            <a href="<?= Url::to('/bienes/qr-masivo') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-qr-code me-1"></i>Generar QR masivo
            </a>
        <?php endif; ?>
        <?php if (Auth::esSuperusuario() || Auth::tienePermiso('bienes.crear')): ?>
            <a href="<?= Url::to('/bienes/crear') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Registrar bien
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div>
<?php endif; ?>

<?php if (!empty($soloPropios)): ?>
    <p class="text-muted small">Mostrando solo los bienes de los espacios donde eres responsable.</p>
<?php endif; ?>

<div class="mb-3" style="max-width: 420px;">
    <input type="search" id="buscador" class="form-control form-control-sm"
           placeholder="Buscar por código, descripción, responsable, ubicación, estado o valor..."
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
            <th></th>
            <th>Descripción</th>
            <th>Código</th>
            <th>Categoría</th>
            <th>Responsable / ubicación</th>
            <th>Valor</th>
            <th>Estado</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($bienes as $b): ?>
            <tr>
                <td>
                    <?php if (!empty($b['foto_path'])): ?>
                        <img src="<?= Url::to('/archivos/' . $b['foto_path']) ?>"
                             data-lightbox-src="<?= Url::to('/archivos/' . $b['foto_path']) ?>"
                             style="width:36px;height:36px;object-fit:cover;border-radius:4px;cursor:zoom-in;"
                             title="Ver foto en grande">
                    <?php else: ?>
                        <span class="d-inline-flex align-items-center justify-content-center bg-light text-muted" style="width:36px;height:36px;border-radius:4px;">
                            <i class="bi bi-box-seam"></i>
                        </span>
                    <?php endif; ?>
                </td>
                <td data-label="Descripción">
                    <?= htmlspecialchars($b['descripcion'], ENT_QUOTES) ?>
                    <?php if (!empty($b['marca'])): ?><div class="small text-muted"><?= htmlspecialchars($b['marca'], ENT_QUOTES) ?></div><?php endif; ?>
                </td>
                <td class="text-muted mono" data-label="Código"><?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?></td>
                <td class="text-muted" data-label="Categoría"><?= htmlspecialchars($b['categoria_nombre'] ?? '—', ENT_QUOTES) ?></td>
                <td class="small" data-label="Responsable / ubicación">
                    <?php if (!empty($b['espacio_nombre'])): ?>
                        <?= htmlspecialchars($b['espacio_nombre'], ENT_QUOTES) ?>
                        <?php if (!empty($b['responsables_nombres'])): ?>
                            <div class="text-muted"><?= htmlspecialchars($b['responsables_nombres'], ENT_QUOTES) ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">Sin asignar</span>
                    <?php endif; ?>
                </td>
                <td class="mono" data-label="Valor">$<?= number_format((float) $b['valor'], 0, ',', '.') ?></td>
                <td data-label="Estado"><span class="badge badge-estado-<?= htmlspecialchars($b['estado'], ENT_QUOTES) ?>"><?= $etiquetasEstado[$b['estado']] ?? $b['estado'] ?></span></td>
                <td class="text-end">
                    <a href="<?= Url::to('/bienes/' . $b['id'] . '/editar') ?>" class="btn btn-sm btn-outline-secondary">
                        <?= Auth::esSuperusuario() || Auth::tienePermiso('bienes.editar') ? 'Editar' : 'Ver' ?>
                    </a>
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
