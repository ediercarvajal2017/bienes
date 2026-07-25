<?php

use App\Core\Auth;
use App\Core\Url;
use App\Core\View;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Histórico de formatos de reintegro</h1>
    <a href="<?= Url::to('/formatos-reintegro') ?>" class="btn btn-sm btn-primary">
        <i class="bi bi-archive me-1"></i>Registrar formato
    </a>
</div>

<?php if (empty($formatos)): ?>
    <p class="text-muted">Todavía no hay formatos de reintegro registrados.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle bg-white tabla-cards">
            <thead>
            <tr>
                <th>Fecha del reintegro</th>
                <th>Descripción</th>
                <?php if (Auth::esSuperusuario()): ?><th>Institución</th><?php endif; ?>
                <th>Registrado por</th>
                <th>Registrado el</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($formatos as $f): ?>
                <tr>
                    <td class="mono" data-label="Fecha del reintegro"><?= htmlspecialchars($f['fecha_reintegro'], ENT_QUOTES) ?></td>
                    <td class="text-muted small" data-label="Descripción"><?= !empty($f['descripcion']) ? htmlspecialchars($f['descripcion'], ENT_QUOTES) : '—' ?></td>
                    <?php if (Auth::esSuperusuario()): ?><td class="text-muted small" data-label="Institución"><?= htmlspecialchars($f['institucion_nombre'], ENT_QUOTES) ?></td><?php endif; ?>
                    <td class="text-muted small" data-label="Registrado por">
                        <?= !empty($f['registrado_por_nombres']) ? htmlspecialchars($f['registrado_por_nombres'] . ' ' . $f['registrado_por_apellidos'], ENT_QUOTES) : '—' ?>
                    </td>
                    <td class="text-muted small mono" data-label="Registrado el"><?= htmlspecialchars($f['fecha_registro'], ENT_QUOTES) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= Url::to('/archivos/' . $f['archivo_path']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                            <i class="bi bi-download me-1"></i>Descargar
                        </a>
                        <a href="<?= Url::to('/formatos-reintegro/' . $f['id'] . '/editar') ?>" class="btn btn-sm btn-outline-primary">
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
        'urlBase' => Url::to('/formatos-reintegro/historial'),
    ]); ?>
<?php endif; ?>
