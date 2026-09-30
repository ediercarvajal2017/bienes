<?php

use App\Core\Auth;
use App\Core\Url;
use App\Core\View;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Histórico de cartera recibida</h1>
    <a href="<?= Url::to('/cartera/enviar') ?>" class="btn btn-sm btn-primary">
        <i class="bi bi-archive me-1"></i>Registrar cartera recibida
    </a>
</div>

<?php if (empty($envios)): ?>
    <p class="text-muted">Todavía no hay carteras recibidas registradas.</p>
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
                <th>Fecha en que se recibió</th>
                <?php if (Auth::esSuperusuario()): ?><th>Institución</th><?php endif; ?>
                <th>Solicitada por</th>
                <th>Correo del solicitante</th>
                <th>Llegó desde</th>
                <th>Registrado por</th>
                <th>Registrado el</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($envios as $e): ?>
                <tr>
                    <td class="mono" data-label="Fecha en que se recibió"><?= htmlspecialchars($e['fecha_envio'], ENT_QUOTES) ?></td>
                    <?php if (Auth::esSuperusuario()): ?><td class="text-muted small" data-label="Institución"><?= htmlspecialchars($e['institucion_nombre'], ENT_QUOTES) ?></td><?php endif; ?>
                    <td data-label="Solicitada por"><?= htmlspecialchars($e['nombre_funcionario'], ENT_QUOTES) ?></td>
                    <td class="small text-muted" data-label="Correo del solicitante"><?= !empty($e['correo_solicitante']) ? htmlspecialchars($e['correo_solicitante'], ENT_QUOTES) : '—' ?></td>
                    <td class="small text-muted" data-label="Llegó desde"><?= htmlspecialchars($e['correo_remitente'], ENT_QUOTES) ?></td>
                    <td class="text-muted small" data-label="Registrado por">
                        <?= !empty($e['registrado_por_nombres']) ? htmlspecialchars($e['registrado_por_nombres'] . ' ' . $e['registrado_por_apellidos'], ENT_QUOTES) : '—' ?>
                    </td>
                    <td class="text-muted small mono" data-label="Registrado el"><?= htmlspecialchars($e['fecha_registro'], ENT_QUOTES) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= \App\Helpers\EnlaceArchivo::url($e['archivo_path']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                            <i class="bi bi-download me-1"></i>Descargar
                        </a>
                        <a href="<?= Url::to('/cartera/' . $e['id'] . '/editar') ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil me-1"></i>Editar
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
