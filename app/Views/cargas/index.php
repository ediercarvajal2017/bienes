<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$urlBasePaginacion = Url::to('/cargas-masivas') . ($busqueda !== '' ? '?' . http_build_query(['q' => $busqueda]) : '');
?>
<h1 class="h4 mb-3">Carga masiva de bienes</h1>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small" style="max-width:640px;"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small" style="max-width:640px;"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<div class="card mb-4" style="max-width: 640px;">
    <div class="card-body">
        <p class="small text-muted">
            Sube un archivo <strong>.xlsx</strong> con las columnas: Código, Descripción, Marca, Fecha de ingreso, Valor.
            <a href="<?= Url::to('/cargas-masivas/plantilla.xlsx') ?>">Descargar plantilla</a>.
        </p>
        <form method="post" action="<?= Url::to('/cargas-masivas') ?>" enctype="multipart/form-data" class="d-flex gap-2">
            <?= Csrf::field() ?>
            <input type="file" name="archivo" accept=".xlsx" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">Analizar archivo</button>
        </form>
    </div>
</div>

<h2 class="h6">Cargas anteriores</h2>

<div class="mb-2" style="max-width: 420px;">
    <input type="search" id="buscador" class="form-control form-control-sm"
           placeholder="Buscar por quién la subió, fecha o estado..."
           value="<?= htmlspecialchars($busqueda, ENT_QUOTES) ?>">
</div>

<?php View::render('partials/paginacion', [
    'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
    'opcionesPorPagina' => $opcionesPorPagina,
    'urlBase' => $urlBasePaginacion,
]); ?>

<div class="table-responsive" style="max-width: 800px;">
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
