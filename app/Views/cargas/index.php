<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$urlBasePaginacion = Url::to('/cargas-masivas') . ($busqueda !== '' ? '?' . http_build_query(['q' => $busqueda]) : '');
?>
<?php ob_start(); ?>
    <a href="<?= Url::to('/bienes') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
    <?= ui('menu_acciones', ['opciones' => \App\Helpers\AccionesBienes::opciones(), 'actual' => 'carga_masiva']) ?>
<?= ui('encabezado_pagina', ['titulo' => 'Carga masiva de bienes', 'icono' => 'subir', 'acciones' => (string) ob_get_clean()]) ?>

<div class="card mb-4 contenedor-md">
    <div class="card-body">
        <p class="small text-muted mb-2">
            Sube un archivo <strong>.xlsx</strong> con las columnas de la
            <a href="<?= Url::to('/cargas-masivas/plantilla.xlsx') ?>">plantilla</a>. Primero se analiza y ves qué
            cambiaría en cada fila; nada se guarda hasta que pulses «Confirmar importación».
        </p>
        <ul class="small text-muted mb-3 ps-3">
            <li><strong>Obligatorias:</strong> Código (la placa) y Descripción.</li>
            <li><strong>Opcionales:</strong> Marca, Fecha de ingreso, Valor (solo números, sin negativos, máximo 10 dígitos),
                Ubicación (código de un espacio que ya existe), Categoría (nombre de una categoría activa) y
                Responsable individual (documento de un usuario activo: el bien queda a su cargo).</li>
            <li><strong>No se borra nada:</strong> los bienes que no están en el archivo no se tocan, y una columna opcional
                en blanco conserva lo que el bien ya tiene en MIA.</li>
        </ul>
        <form method="post" action="<?= Url::to('/cargas-masivas') ?>" enctype="multipart/form-data" class="d-flex gap-2">
            <?= Csrf::field() ?>
            <input type="file" name="archivo" accept=".xlsx" aria-label="Archivo Excel de bienes" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">Analizar archivo</button>
        </form>
        <hr>
        <p class="small text-muted mb-2">¿Ya subiste los bienes y quieres emparejar sus fotos por código?</p>
        <a href="<?= Url::to('/bienes/carga-masiva-fotos') ?>" class="btn btn-sm btn-outline-secondary">
            <?= icono('subir', 'me-1') ?>Carga masiva de fotos (.zip)
        </a>
    </div>
</div>

<h2 class="h6">Cargas anteriores</h2>

<div class="mb-2 contenedor-sm">
    <input type="search" id="buscador" data-buscar="q" class="form-control form-control-sm"
           placeholder="Buscar por quién la subió, fecha o estado..."
           value="<?= htmlspecialchars($busqueda, ENT_QUOTES) ?>">
</div>

<?php View::render('partials/paginacion', [
    'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
    'opcionesPorPagina' => $opcionesPorPagina,
    'urlBase' => $urlBasePaginacion,
]); ?>

<div class="table-responsive contenedor-md">
    <table class="table table-sm bg-white tabla-cards">
        <thead>
        <tr><th>Fecha</th><th>Subido por</th><th>Filas</th><th>Nuevos</th><th>Modificados</th><th>Sin cambios</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (empty($cargas)): ?>
            <?php View::render('partials/tabla_vacia', [
                'colspan' => 8,
                'icono' => 'file-earmark-excel',
                'mensaje' => $busqueda !== ''
                    ? 'Ninguna carga coincide con la búsqueda.'
                    : 'Todavía no se ha subido ninguna carga masiva.',
                'ctaTexto' => $busqueda !== '' ? 'Quitar búsqueda' : null,
                'ctaUrl' => $busqueda !== '' ? Url::to('/cargas-masivas') : null,
            ]); ?>
        <?php endif; ?>
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
                <td><a href="<?= Url::to('/cargas-masivas/' . $c['id']) ?>" class="btn btn-sm btn-outline-secondary">Ver</a></td>
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
