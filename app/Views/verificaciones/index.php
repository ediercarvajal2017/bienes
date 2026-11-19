<?php

use App\Core\Auth;
use App\Core\Url;
use App\Core\View;

?>
<?php ob_start(); ?>
    <?php if (Auth::esSuperusuario() || Auth::tienePermiso('verificaciones.gestionar')): ?>
        <?php if (empty($jornadaActiva)): ?>
            <a href="<?= Url::to('/verificaciones/crear') ?>" class="btn btn-primary btn-sm">
                <?= icono('crear', 'me-1') ?>Nueva jornada
            </a>
        <?php else: ?>
            <a href="<?= Url::to('/verificaciones/' . $jornadaActiva['id']) ?>" class="btn btn-outline-secondary btn-sm">
                <?= icono('verificacion', 'me-1') ?>Ir a la jornada en progreso
            </a>
        <?php endif; ?>
    <?php endif; ?>
<?= ui('encabezado_pagina', ['titulo' => 'Verificación física de bienes', 'icono' => 'verificacion', 'subtitulo' => 'Compara el inventario del sistema contra la existencia física de cada bien.', 'acciones' => (string) ob_get_clean()]) ?>

<?php if (empty($jornadas)): ?>
    <p class="text-muted">Todavía no se ha iniciado ninguna jornada de verificación.</p>
<?php else: ?>
    <?php View::render('partials/paginacion', ['densidad' => true,
        'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
        'opcionesPorPagina' => $opcionesPorPagina,
        'urlBase' => Url::to('/verificaciones'),
    ]); ?>

    <?php // Un clic en el título ordena todo el listado (App\Helpers\OrdenListado). ?>
    <?php $columnasOrden = [
        'nombre' => ['Nombre', ['de la A a la Z', 'de la Z a la A']],
        'fecha' => ['Fecha de inicio', ['de la más antigua a la más reciente', 'de la más reciente a la más antigua']],
        'estado' => ['Estado', ['de cerradas a en progreso', 'de en progreso a cerradas']],
    ]; ?>
    <?= ui('filtros_activos', ['filtros' => filtro_orden($columnasOrden)]) ?>
    <div data-navegable class="table-responsive">
        <table class="table table-sm table-hover align-middle bg-white tabla-cards">
            <thead>
            <tr><?= th_orden('nombre', $columnasOrden) ?><?= th_orden('fecha', $columnasOrden) ?><th>Creada por</th><?= th_orden('estado', $columnasOrden) ?><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($jornadas as $j): ?>
                <tr>
                    <td data-label="Nombre"><?= htmlspecialchars($j['nombre'], ENT_QUOTES) ?></td>
                    <td class="mono small" data-label="Fecha de inicio"><?= htmlspecialchars($j['fecha_inicio'], ENT_QUOTES) ?></td>
                    <td class="text-muted small" data-label="Creada por"><?= htmlspecialchars($j['creador_nombres'] . ' ' . $j['creador_apellidos'], ENT_QUOTES) ?></td>
                    <td data-label="Estado">
                        <?php if ($j['estado'] === 'en_progreso'): ?>
                            <span class="badge badge-estado-en_reparacion"><?= icono('pendiente', 'me-1') ?>En progreso</span>
                        <?php else: ?>
                            <span class="badge badge-neutro"><?= icono('exito', 'me-1') ?>Cerrada</span>
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
