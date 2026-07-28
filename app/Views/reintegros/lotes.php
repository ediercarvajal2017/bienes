<?php

use App\Core\Auth;
use App\Core\Url;
use App\Core\View;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Lotes de reintegro</h1>
        <p class="text-muted small mb-0">Cada lote agrupa los reintegros que decidas consolidar, con su formato descargable.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= Url::to('/reintegros') ?>" class="btn btn-sm btn-outline-secondary">Ir a reintegrar</a>
        <a href="<?= Url::to('/reintegros/lotes/generar') ?>" class="btn btn-sm btn-primary position-relative">
            <i class="bi bi-file-earmark-plus me-1"></i>Generar lote
            <?php if ($pendientesDeLote > 0): ?>
                <span class="badge rounded-pill text-bg-warning ms-1"><?= $pendientesDeLote ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<?php if ($pendientesDeLote > 0): ?>
    <div class="alert alert-warning py-2 small">
        Tienes <?= $pendientesDeLote ?> reintegro(s) sin agrupar todavía.
        <a href="<?= Url::to('/reintegros/lotes/generar') ?>">Generar lote ahora</a>.
    </div>
<?php endif; ?>

<?php if (empty($lotes)): ?>
    <p class="text-muted">Todavía no se ha generado ningún lote.</p>
<?php else: ?>
    <?php View::render('partials/paginacion', [
        'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
        'opcionesPorPagina' => $opcionesPorPagina,
        'urlBase' => Url::to('/reintegros/lotes'),
    ]); ?>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle bg-white tabla-cards">
            <thead>
            <tr>
                <th>Fecha del lote</th>
                <?php if (Auth::esSuperusuario()): ?><th>Institución</th><?php endif; ?>
                <th>Descripción</th>
                <th>Registrado por</th>
                <th class="text-end">Bienes</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lotes as $l): ?>
                <tr>
                    <td class="mono" data-label="Fecha del lote"><?= htmlspecialchars($l['fecha'], ENT_QUOTES) ?></td>
                    <?php if (Auth::esSuperusuario()): ?><td class="text-muted small" data-label="Institución"><?= htmlspecialchars($l['institucion_nombre'], ENT_QUOTES) ?></td><?php endif; ?>
                    <td class="text-muted" data-label="Descripción"><?= htmlspecialchars($l['destino_texto'] ?? '—', ENT_QUOTES) ?></td>
                    <td class="text-muted small" data-label="Registrado por"><?= htmlspecialchars($l['registrado_por_nombres'] . ' ' . $l['registrado_por_apellidos'], ENT_QUOTES) ?></td>
                    <td class="text-end" data-label="Bienes"><?= (int) $l['total_bienes'] ?></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= Url::to('/reintegros/lotes/' . $l['id']) ?>" class="btn btn-sm btn-outline-secondary">Ver</a>
                        <a href="<?= Url::to('/reintegros/lotes/' . $l['id'] . '/formato.xlsx') ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-file-earmark-excel me-1"></i>Formato
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
        'urlBase' => Url::to('/reintegros/lotes'),
    ]); ?>
<?php endif; ?>
