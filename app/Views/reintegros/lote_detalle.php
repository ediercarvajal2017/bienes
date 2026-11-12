<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Helpers\FechaMovimiento;
use App\Models\LoteReintegro;

$e = static fn (?string $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES);
$estado = (string) $lote['estado'];
$insignias = [
    'pendiente' => 'badge-lote badge-lote-pendiente',
    'reintegrado' => 'badge-lote badge-lote-reintegrado',
    'unificado' => 'badge-lote badge-lote-unificado',
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">
        Lote de reintegro #<?= (int) $lote['id'] ?>
        <span class="badge <?= $insignias[$estado] ?? 'text-bg-secondary' ?> align-middle fs-6"><?= $e(LoteReintegro::ESTADOS[$estado] ?? $estado) ?></span>
    </h1>
    <div class="d-flex gap-2">
        <a href="<?= Url::to('/reintegros/lotes') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
        <?php if ($estado !== 'unificado'): ?>
            <a href="<?= Url::to('/reintegros/lotes/' . $lote['id'] . '/formato.xlsx') ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-file-earmark-excel me-1"></i>Descargar comprobante (FO-ADMI-009)
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small" style="max-width: 680px;"><?= $e($mensaje) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small" style="max-width: 680px;"><?= $e($error) ?></div><?php endif; ?>

<?php if ($estado === 'pendiente'): ?>
    <div class="estado-lote estado-lote-pendiente" style="max-width: 680px;">
        <strong>Pendiente de entrega</strong> (lote generado <?= $e(FechaMovimiento::antiguedad((string) $lote['fecha'])) ?>).
        Cuando lo entregues a la Alcaldía con su comprobante, márcalo como reintegrado.
        <form method="post" action="<?= Url::to('/reintegros/lotes/marcar-reintegrados') ?>" class="row g-2 align-items-end mt-1"
              data-confirmar="¿Marcar el lote #<?= (int) $lote['id'] ?> como reintegrado (ya entregado a la Alcaldía)?">
            <?= Csrf::field() ?>
            <input type="hidden" name="lotes[]" value="<?= (int) $lote['id'] ?>">
            <input type="hidden" name="volver" value="/reintegros/lotes/<?= (int) $lote['id'] ?>">
            <div class="col-sm-4">
                <label for="campoFechaEntrega" class="form-label small mb-1">Fecha de entrega</label>
                <input id="campoFechaEntrega" type="date" name="fecha_entrega" class="form-control form-control-sm" required
                       value="<?= FechaMovimiento::hoy() ?>" max="<?= FechaMovimiento::hoy() ?>">
            </div>
            <div class="col-sm-5">
                <label for="campoNota" class="form-label small mb-1">Nota (opcional)</label>
                <input id="campoNota" type="text" name="nota" maxlength="500" class="form-control form-control-sm">
            </div>
            <div class="col-sm-3">
                <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-check2-circle me-1"></i>Marcar como reintegrado</button>
            </div>
        </form>
    </div>
<?php elseif ($estado === 'reintegrado'): ?>
    <div class="estado-lote estado-lote-reintegrado" style="max-width: 680px;">
        <strong>Reintegrado:</strong> entregado a la Alcaldía el <span class="mono"><?= $e($lote['reintegrado_fecha']) ?></span>
        <?php if (!empty($lote['reintegrado_por_nombre'])): ?>· registrado por <?= $e($lote['reintegrado_por_nombre']) ?><?php endif; ?>.
        <?php if (!empty($lote['reintegrado_nota'])): ?><div class="mt-1"><span class="text-muted">Nota:</span> <?= $e($lote['reintegrado_nota']) ?></div><?php endif; ?>
        <?php if ($puedeReabrir): ?>
            <details class="mt-2">
                <summary class="small">¿Se marcó por error? Reabrir el lote</summary>
                <form method="post" action="<?= Url::to('/reintegros/lotes/' . (int) $lote['id'] . '/reabrir') ?>" class="row g-2 align-items-end mt-1"
                      data-confirmar="¿Reabrir el lote #<?= (int) $lote['id'] ?>? Volverá a quedar pendiente.">
                    <?= Csrf::field() ?>
                    <div class="col-sm-9">
                        <label for="campoMotivo" class="form-label small mb-1">Motivo</label>
                        <input id="campoMotivo" type="text" name="motivo" maxlength="500" required class="form-control form-control-sm" placeholder="Ej. Se marcó el lote equivocado">
                    </div>
                    <div class="col-sm-3">
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">Reabrir</button>
                    </div>
                </form>
            </details>
        <?php endif; ?>
    </div>
<?php elseif ($estado === 'unificado'): ?>
    <div class="estado-lote estado-lote-unificado" style="max-width: 680px;">
        <strong>Unificado</strong><?php if (!empty($lote['unificado_en'])): ?> el <span class="mono"><?= $e(substr((string) $lote['unificado_en'], 0, 10)) ?></span><?php endif; ?><?php if (!empty($lote['unificado_por_nombre'])): ?> por <?= $e($lote['unificado_por_nombre']) ?><?php endif; ?>:
        sus bienes pasaron a lotes nuevos, uno por categoría.
    </div>
<?php endif; ?>

<div class="card mb-3" style="max-width: 680px;">
    <div class="card-body py-3 small">
        <div class="row g-2">
            <div class="col-6"><span class="text-muted">Institución:</span> <?= $e($lote['institucion_nombre']) ?></div>
            <div class="col-6"><span class="text-muted">Fecha del lote:</span> <span class="mono"><?= $e($lote['fecha']) ?></span></div>
            <div class="col-6"><span class="text-muted">Descripción:</span> <?= $e($lote['destino_texto'] ?? '—') ?></div>
            <div class="col-6"><span class="text-muted">Registrado por:</span> <?= $e($lote['registrado_por_nombres'] . ' ' . $lote['registrado_por_apellidos']) ?></div>
            <?php if (!empty($lote['observaciones'])): ?>
                <div class="col-12"><span class="text-muted">Observaciones:</span> <?= $e($lote['observaciones']) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (empty($bienes)): ?>
    <p class="text-muted">Este lote no tiene bienes.</p>
<?php else: ?>
    <div class="table-responsive" style="max-width: 680px;">
        <table class="table table-sm bg-white tabla-cards">
            <thead>
            <tr>
                <th>Código</th>
                <th>Descripción</th>
                <th>Categoría</th>
                <th>Fecha de reintegro</th>
                <th>Destino</th>
                <th>Espacio de origen</th>
                <th class="text-end">Valor</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($bienes as $b): ?>
                <tr>
                    <td class="mono" data-label="Código"><?= $e($b['codigo_identificacion']) ?></td>
                    <td data-label="Descripción"><?= $e($b['descripcion']) ?></td>
                    <td class="text-muted" data-label="Categoría"><?= !empty($b['categoria_nombre']) ? $e($b['categoria_nombre']) : '—' ?></td>
                    <td class="text-muted mono" data-label="Fecha de reintegro"><?= $e($b['fecha_reintegro']) ?></td>
                    <td class="text-muted" data-label="Destino"><?= !empty($b['destino_texto']) ? $e($b['destino_texto']) : '—' ?></td>
                    <td class="text-muted" data-label="Espacio de origen"><?= !empty($b['espacio_origen_nombre']) ? $e($b['espacio_origen_nombre']) : '—' ?></td>
                    <td class="text-end" data-label="Valor"><?= number_format((float) $b['valor'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
