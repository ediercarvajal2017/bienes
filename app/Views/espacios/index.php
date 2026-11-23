<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;
use App\Helpers\AyudaCampo;

$urlBasePaginacion = Url::to('/espacios') . ($busqueda !== '' ? '?q=' . urlencode($busqueda) : '');
$puedeCrearEspacio = Auth::esSuperusuario() || Auth::tienePermiso('espacios.crear');
?>
<?php ob_start(); ?>
    <?php if (Auth::esSuperusuario() || Auth::tienePermiso('espacios.crear')): ?>
        <div class="d-flex gap-2">
            <a href="<?= Url::to('/espacios/carga-masiva') ?>" class="btn btn-outline-secondary btn-sm">
                <?= icono('subir', 'me-1') ?>Carga masiva
            </a>
            <a href="<?= Url::to('/espacios/crear') ?>" class="btn btn-primary btn-sm">
                <?= icono('crear', 'me-1') ?>Nuevo espacio
            </a>
        
<?= ui('encabezado_pagina', ['titulo' => 'Espacios', 'icono' => 'espacio', 'ayuda' => AyudaCampo::boton('espacios'), 'acciones' => (string) ob_get_clean()]) ?>
    <?php endif; ?>
</div>

<?php if (!empty($espacioEnUso)): ?>
    <?php // Mismo diseño que los avisos (partials/ui/alerta.php), con la lista de bienes que usan el espacio. ?>
    <div class="alert alert-danger aviso contenedor-md" role="alert" tabindex="-1" data-aviso-enfocar>
        <?= icono('error', 'aviso-icono') ?>
        <div class="aviso-cuerpo">
        <p class="aviso-titulo">No se eliminó el espacio</p>
        <p class="aviso-detalle"><?= htmlspecialchars((string) ($espacioEnUso['porQue'] ?? ''), ENT_QUOTES) ?></p>
        <?php if (!empty($espacioEnUso['bienes'])): ?>
            <ul class="mb-1 mt-2 ps-3">
                <?php foreach ($espacioEnUso['bienes'] as $b): ?>
                    <li>
                        <a href="<?= Url::to('/bienes/' . (int) $b['id'] . '/editar') ?>" class="alert-link mono"><?= htmlspecialchars((string) $b['codigo'], ENT_QUOTES) ?></a>
                        · <?= htmlspecialchars((string) $b['descripcion'], ENT_QUOTES) ?>
                    </li>
                <?php endforeach; ?>
                <?php if ((int) ($espacioEnUso['mas'] ?? 0) > 0): ?>
                    <li class="list-unstyled text-body-secondary">y <?= (int) $espacioEnUso['mas'] ?> más.</li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
        <?php if (!empty($espacioEnUso['consejo'])): ?>
            <p class="aviso-solucion"><strong>Qué hacer:</strong> <?= htmlspecialchars((string) $espacioEnUso['consejo'], ENT_QUOTES) ?></p>
        <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="mb-3 contenedor-sm">
    <label for="buscador" class="form-label small mb-1">Buscar</label>
    <input type="search" id="buscador" data-buscar="q" class="form-control form-control-sm"
           placeholder="Buscar por código o nombre..."
           value="<?= htmlspecialchars($busqueda, ENT_QUOTES) ?>">
</div>

<?php View::render('partials/paginacion', ['densidad' => true,
    'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
    'opcionesPorPagina' => $opcionesPorPagina,
    'urlBase' => $urlBasePaginacion,
]); ?>

<?php // Un clic en el título ordena todo el listado (App\Helpers\OrdenListado). ?>
<?php $columnasOrden = [
    'codigo' => ['N.º', ['de menor a mayor', 'de mayor a menor']],
    'nombre' => ['Nombre', ['de la A a la Z', 'de la Z a la A']],
    'estado' => ['Estado', ['de activos a inactivos', 'de inactivos a activos']],
]; ?>
<?= ui('filtros_activos', ['filtros' => filtro_orden($columnasOrden)]) ?>
<div data-navegable class="table-responsive">
    <table class="table table-sm table-hover align-middle bg-white tabla-cards">
        <thead>
        <tr>
            <?= th_orden('codigo', $columnasOrden) ?>
            <?= th_orden('nombre', $columnasOrden) ?>
            <th>Responsable(s)</th>
            <?= th_orden('estado', $columnasOrden) ?>
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
                <td class="text-muted mono" data-label="N.º"><?= resaltar($e['codigo'], $busqueda) ?></td>
                <td data-label="Nombre"><?= resaltar($e['nombre'], $busqueda) ?></td>
                <td class="text-muted small" data-label="Responsable(s)">
                    <?= !empty($e['responsables_nombres']) ? resaltar($e['responsables_nombres'], $busqueda) : '—' ?>
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
