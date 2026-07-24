<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Carga masiva de espacios</h1>
    <a href="<?= Url::to('/espacios') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small" style="max-width:640px;"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small" style="max-width:640px;"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<div class="card mb-4" style="max-width: 640px;">
    <div class="card-body">
        <p class="small text-muted">
            Sube un archivo <strong>.xlsx</strong> con las columnas: Número, Nombre, Documentos_Responsables
            (uno o varios documentos separados por coma).
            <a href="<?= Url::to('/espacios/carga-masiva/plantilla.xlsx') ?>">Descargar plantilla</a>.
        </p>
        <form method="post" action="<?= Url::to('/espacios/carga-masiva/subir') ?>" enctype="multipart/form-data" class="d-flex gap-2">
            <?= Csrf::field() ?>
            <input type="file" name="archivo" accept=".xlsx" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">Analizar archivo</button>
        </form>
    </div>
</div>

<h2 class="h6">Cargas anteriores</h2>
<?php if (empty($cargas)): ?>
    <p class="text-muted small">Todavía no se ha subido ningún archivo.</p>
<?php else: ?>
    <div class="table-responsive" style="max-width: 800px;">
        <table class="table table-sm bg-white">
            <thead>
            <tr><th>Fecha</th><th>Subido por</th><th>Filas</th><th>Nuevos</th><th>Modificados</th><th>Sin cambios</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($cargas as $c): ?>
                <tr>
                    <td class="mono small"><?= htmlspecialchars(substr($c['created_at'], 0, 16), ENT_QUOTES) ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($c['nombres'] . ' ' . $c['apellidos'], ENT_QUOTES) ?></td>
                    <td><?= (int) $c['total_filas'] ?></td>
                    <td><?= (int) $c['nuevos'] ?></td>
                    <td><?= (int) $c['modificados'] ?></td>
                    <td><?= (int) $c['sin_cambios'] ?></td>
                    <td>
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
        'urlBase' => Url::to('/espacios/carga-masiva'),
    ]); ?>
<?php endif; ?>
