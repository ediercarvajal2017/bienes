<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

?>
<?php ob_start(); ?>
    <a href="<?= Url::to('/espacios') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
<?= ui('encabezado_pagina', ['titulo' => 'Carga masiva de espacios', 'icono' => 'espacio', 'acciones' => (string) ob_get_clean()]) ?>

<div class="card mb-4 contenedor-md">
    <div class="card-body">
        <p class="small text-muted">
            Sube un archivo <strong>.xlsx</strong> con las columnas: Número, Nombre, Documentos_Responsables
            (uno o varios documentos separados por coma).
            <a href="<?= Url::to('/espacios/carga-masiva/plantilla.xlsx') ?>">Descargar plantilla</a>.
        </p>
        <form method="post" action="<?= Url::to('/espacios/carga-masiva/subir') ?>" enctype="multipart/form-data" class="d-flex gap-2">
            <?= Csrf::field() ?>
            <input type="file" name="archivo" accept=".xlsx" aria-label="Archivo Excel de espacios" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">Analizar archivo</button>
        </form>
    </div>
</div>

<h2 class="h6">Cargas anteriores</h2>
<?php if (empty($cargas)): ?>
    <p class="text-muted small">Todavía no se ha subido ningún archivo.</p>
<?php else: ?>
    <?php View::render('partials/paginacion', [
        'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
        'opcionesPorPagina' => $opcionesPorPagina,
        'urlBase' => Url::to('/espacios/carga-masiva'),
    ]); ?>

    <div class="table-responsive contenedor-md">
        <table class="table table-sm bg-white tabla-cards">
            <thead>
            <tr><th>Fecha</th><th>Subido por</th><th>Filas</th><th>Nuevos</th><th>Modificados</th><th>Sin cambios</th><th>Estado</th><th></th></tr>
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
                    <td><a href="<?= Url::to('/espacios/carga-masiva/' . $c['id']) ?>" class="btn btn-sm btn-outline-secondary">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php View::render('partials/paginacion', [
        'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
        'opcionesPorPagina' => $opcionesPorPagina,
        'urlBase' => Url::to('/espacios/carga-masiva'),
    ]); ?>
<?php endif; ?>
