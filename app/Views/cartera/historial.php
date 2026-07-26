<?php

use App\Core\Auth;
use App\Core\Url;
use App\Core\View;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Histórico de cartera enviada</h1>
    <a href="<?= Url::to('/cartera/enviar') ?>" class="btn btn-sm btn-primary">
        <i class="bi bi-archive me-1"></i>Registrar envío
    </a>
</div>

<?php if (empty($envios)): ?>
    <p class="text-muted">Todavía no hay registros de cartera enviada.</p>
<?php else: ?>
    <?php View::render('partials/paginacion', [
        'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
        'opcionesPorPagina' => $opcionesPorPagina,
        'urlBase' => Url::to('/cartera/enviados'),
    ]); ?>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle bg-white tabla-cards">
            <thead>
            <tr>
                <th>Fecha de envío</th>
                <?php if (Auth::esSuperusuario()): ?><th>Institución</th><?php endif; ?>
                <th>Funcionario</th>
                <th>Correo remitente</th>
                <th>Registrado por</th>
                <th>Registrado el</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($envios as $e): ?>
                <tr>
                    <td class="mono" data-label="Fecha de envío"><?= htmlspecialchars($e['fecha_envio'], ENT_QUOTES) ?></td>
                    <?php if (Auth::esSuperusuario()): ?><td class="text-muted small" data-label="Institución"><?= htmlspecialchars($e['institucion_nombre'], ENT_QUOTES) ?></td><?php endif; ?>
                    <td data-label="Funcionario"><?= htmlspecialchars($e['nombre_funcionario'], ENT_QUOTES) ?></td>
                    <td class="small text-muted" data-label="Correo remitente"><?= htmlspecialchars($e['correo_remitente'], ENT_QUOTES) ?></td>
                    <td class="text-muted small" data-label="Registrado por">
                        <?= !empty($e['registrado_por_nombres']) ? htmlspecialchars($e['registrado_por_nombres'] . ' ' . $e['registrado_por_apellidos'], ENT_QUOTES) : '—' ?>
                    </td>
                    <td class="text-muted small mono" data-label="Registrado el"><?= htmlspecialchars($e['fecha_registro'], ENT_QUOTES) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= Url::to('/archivos/' . $e['archivo_path']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                            <i class="bi bi-download me-1"></i>Descargar
                        </a>
                        <a href="<?= Url::to('/cartera/' . $e['id'] . '/editar') ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i>
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
        'urlBase' => Url::to('/cartera/enviados'),
    ]); ?>
<?php endif; ?>
