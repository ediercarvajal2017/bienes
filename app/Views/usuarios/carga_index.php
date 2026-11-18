<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

?>
<?php ob_start(); ?>
    <a href="<?= Url::to('/usuarios') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
<?= ui('encabezado_pagina', ['titulo' => 'Carga masiva de usuarios', 'icono' => 'usuarios', 'acciones' => (string) ob_get_clean()]) ?>

<div class="card mb-4 contenedor-md">
    <div class="card-body">
        <p class="small text-muted mb-2">
            Sube un archivo <strong>.xlsx</strong> con las columnas de la
            <a href="<?= Url::to('/usuarios/carga-masiva/plantilla.xlsx') ?>">plantilla</a>. Primero se analiza y ves qué
            cambiaría en cada fila; nada se guarda hasta que pulses «Confirmar importación».
        </p>
        <ul class="small text-muted mb-3 ps-3">
            <li><strong>Obligatorias:</strong> Documento siempre. Para un usuario nuevo, también Nombres, Apellidos, Cargo y
                Correo; si Rol va vacío, queda como «docente».</li>
            <li><strong>No se borra nada:</strong> los usuarios que no están en el archivo no se tocan, y en un usuario que ya
                existe una columna en blanco conserva lo que tiene (incluido su rol).</li>
            <li>El archivo nunca trae contraseñas: cada usuario nuevo entra la primera vez con «¿Olvidaste tu contraseña?» y su
                correo registrado.</li>
        </ul>
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
    <?php View::render('partials/paginacion', [
        'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
        'opcionesPorPagina' => $opcionesPorPagina,
        'urlBase' => Url::to('/usuarios/carga-masiva'),
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
