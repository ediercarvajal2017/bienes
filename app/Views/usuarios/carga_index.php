<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

?>
<?php ob_start(); ?>
    <a href="<?= Url::to('/usuarios') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
<?= ui('encabezado_pagina', ['titulo' => 'Carga masiva de usuarios', 'icono' => 'usuarios', 'ayuda' => \App\Helpers\AyudaCampo::boton('carga_masiva_usuarios'), 'acciones' => (string) ob_get_clean()]) ?>

<div class="card mb-4 contenedor-md">
    <div class="card-body">
        <p class="small text-muted mb-3">
            Sube la plantilla diligenciada (<strong>.xlsx</strong>). La descargas en el ícono <?= icono('ayuda') ?> junto
            al título: trae las instrucciones, las listas de Cargo y Rol, y la columna obligatoria en rojo claro.
        </p>
        <form method="post" action="<?= Url::to('/usuarios/carga-masiva/subir') ?>" enctype="multipart/form-data" class="d-flex gap-2">
            <?= Csrf::field() ?>
            <input type="file" name="archivo" accept=".xlsx" aria-label="Archivo Excel de usuarios" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">Analizar archivo</button>
        </form>
    </div>
</div>

<h2 class="h6">Cargas anteriores</h2>
<?php if (empty($cargas)): ?>
    <p class="text-muted small">Todavía no se ha subido ningún archivo.</p>
<?php else: ?>
    <?php View::render('partials/paginacion', ['densidad' => true,
        'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
        'opcionesPorPagina' => $opcionesPorPagina,
        'urlBase' => Url::to('/usuarios/carga-masiva'),
    ]); ?>

    <?php // Un clic en el título ordena todo el listado (App\Helpers\OrdenListado). ?>
    <?php $columnasOrden = [
        'fecha' => ['Fecha', ['de la más antigua a la más reciente', 'de la más reciente a la más antigua']],
        'subido' => ['Subido por', ['de la A a la Z', 'de la Z a la A']],
        'filas' => ['Filas', ['de menor a mayor', 'de mayor a menor']],
        'estado' => ['Estado', ['de pendientes a aplicadas', 'de aplicadas a pendientes']],
    ]; ?>
    <?= ui('filtros_activos', ['filtros' => filtro_orden($columnasOrden)]) ?>
    <div data-navegable class="table-responsive contenedor-md">
        <table class="table table-sm bg-white tabla-cards">
            <thead>
            <tr><?= th_orden('fecha', $columnasOrden) ?><?= th_orden('subido', $columnasOrden) ?><?= th_orden('filas', $columnasOrden) ?><th>Nuevos</th><th>Modificados</th><th>Sin cambios</th><?= th_orden('estado', $columnasOrden) ?><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($cargas as $c): ?>
                <tr>
                    <td class="mono small" data-label="Fecha"><?= htmlspecialchars(substr($c['created_at'], 0, 16), ENT_QUOTES) ?></td>
                    <td class="small text-muted" data-label="Subido por"><?= htmlspecialchars($c['nombres'] . ' ' . $c['apellidos'], ENT_QUOTES) ?></td>
                    <td data-label="Filas"><?= (int) $c['total_filas'] ?></td>
                    <td data-label="Nuevos"><?= (int) $c['nuevos'] ?></td>
                    <td data-label="Modificados"><?= (int) $c['modificados'] ?></td>
                    <td data-label="Sin cambios"><?= (int) $c['sin_cambios'] ?></td>
                    <td data-label="Estado">
                        <?php if ((int) $c['aplicada'] === 1): ?>
                            <span class="badge badge-estado-activo">Aplicada</span>
                        <?php else: ?>
                            <span class="badge badge-estado-en_reparacion">Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="<?= Url::to('/usuarios/carga-masiva/' . $c['id']) ?>" class="btn btn-sm btn-outline-secondary">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php View::render('partials/paginacion', [
        'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
        'opcionesPorPagina' => $opcionesPorPagina,
        'urlBase' => Url::to('/usuarios/carga-masiva'),
    ]); ?>
<?php endif; ?>
