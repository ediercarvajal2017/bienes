<?php

use App\Core\Url;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Lote de reintegro #<?= (int) $lote['id'] ?></h1>
    <div class="d-flex gap-2">
        <a href="<?= Url::to('/reintegros/lotes') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
        <a href="<?= Url::to('/reintegros/lotes/' . $lote['id'] . '/formato.xlsx') ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-file-earmark-excel me-1"></i>Descargar formato
        </a>
    </div>
</div>

<div class="card mb-3" style="max-width: 680px;">
    <div class="card-body py-3 small">
        <div class="row g-2">
            <div class="col-6"><span class="text-muted">Institución:</span> <?= htmlspecialchars($lote['institucion_nombre'], ENT_QUOTES) ?></div>
            <div class="col-6"><span class="text-muted">Fecha del lote:</span> <span class="mono"><?= htmlspecialchars($lote['fecha'], ENT_QUOTES) ?></span></div>
            <div class="col-6"><span class="text-muted">Descripción:</span> <?= htmlspecialchars($lote['destino_texto'] ?? '—', ENT_QUOTES) ?></div>
            <div class="col-6"><span class="text-muted">Registrado por:</span> <?= htmlspecialchars($lote['registrado_por_nombres'] . ' ' . $lote['registrado_por_apellidos'], ENT_QUOTES) ?></div>
            <?php if (!empty($lote['observaciones'])): ?>
                <div class="col-12"><span class="text-muted">Observaciones:</span> <?= htmlspecialchars($lote['observaciones'], ENT_QUOTES) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="table-responsive" style="max-width: 680px;">
    <table class="table table-sm bg-white">
        <thead>
        <tr>
            <th>Código</th>
            <th>Descripción</th>
            <th>Fecha de reintegro</th>
            <th>Destino</th>
            <th>Espacio de origen</th>
            <th class="text-end">Valor</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($bienes as $b): ?>
            <tr>
                <td class="mono"><?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($b['descripcion'], ENT_QUOTES) ?></td>
                <td class="text-muted mono"><?= htmlspecialchars($b['fecha_reintegro'], ENT_QUOTES) ?></td>
                <td class="text-muted"><?= htmlspecialchars($b['destino_texto'], ENT_QUOTES) ?></td>
                <td class="text-muted">
                    <?= !empty($b['espacio_origen_nombre']) ? htmlspecialchars($b['espacio_origen_nombre'], ENT_QUOTES) : '—' ?>
                </td>
                <td class="text-end"><?= number_format((float) $b['valor'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
