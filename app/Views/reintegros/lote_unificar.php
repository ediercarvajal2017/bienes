<?php

use App\Core\Csrf;
use App\Core\Url;

$e = static fn (?string $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES);
$viejo ??= [];
$separar = count($loteIds) === 1;
$numeros = static function (array $ids): string {
    $partes = array_map(static fn (int $id): string => "#{$id}", $ids);
    $ultimo = array_pop($partes);

    return $partes === [] ? (string) $ultimo : implode(', ', $partes) . ' y ' . $ultimo;
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0"><?= $separar ? 'Separar el lote #' . (int) $loteIds[0] . ' por categoría' : 'Unificar lotes' ?></h1>
        <p class="text-muted small mb-0">
            Revisa el resultado antes de confirmar. Los bienes quedan en <strong>un lote nuevo por categoría</strong>,
            cada uno con su comprobante FO-ADMI-009 en Excel.
        </p>
    </div>
    <a href="<?= Url::to('/reintegros/lotes') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small" style="max-width: 760px;"><?= $e($error) ?></div><?php endif; ?>

<div class="row g-3" style="max-width: 760px;">
    <div class="col-md-5">
        <h2 class="h6">Lote<?= $separar ? '' : 's' ?> seleccionado<?= $separar ? '' : 's' ?></h2>
        <ul class="list-group small">
            <?php foreach ($lotes as $l): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>
                        <a href="<?= Url::to('/reintegros/lotes/' . (int) $l['id']) ?>" class="mono fw-semibold">#<?= (int) $l['id'] ?></a>
                        <span class="text-muted mono ms-1"><?= $e($l['fecha']) ?></span>
                    </span>
                    <span class="text-muted"><?= (int) ($bienesPorLote[(int) $l['id']] ?? 0) ?> bien(es)</span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="small text-muted mt-2 mb-0">
            Al confirmar, <?= $separar ? 'este lote queda' : 'estos lotes quedan' ?> como <strong>Unificado</strong>, con enlace a los lotes nuevos.
        </p>
    </div>

    <div class="col-md-7">
        <h2 class="h6">Se crearán <?= count($grupos) ?> lote<?= count($grupos) === 1 ? '' : 's' ?> nuevo<?= count($grupos) === 1 ? '' : 's' ?></h2>
        <?php foreach ($grupos as $grupo): ?>
            <div class="card mb-2">
                <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <span class="badge text-bg-light border fs-6"><?= $e($grupo['categoria']) ?></span>
                        <div class="small text-muted mt-1">Viene de <?= $e($numeros($grupo['origenes'])) ?></div>
                    </div>
                    <div class="text-end">
                        <span class="fs-5 fw-semibold"><?= count($grupo['movimientos']) ?></span>
                        <div class="small text-muted"><?= count($grupo['movimientos']) === 1 ? 'bien' : 'bienes' ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<form method="post" action="<?= Url::to('/reintegros/lotes/unificar') ?>" class="card mt-3" style="max-width: 760px;"
      data-confirmar="¿Confirmar? Se crearán <?= count($grupos) ?> lote(s) nuevo(s) y <?= $separar ? 'el lote seleccionado quedará' : 'los ' . count($loteIds) . ' lotes seleccionados quedarán' ?> como Unificado.">
    <?= Csrf::field() ?>
    <?php foreach ($loteIds as $id): ?>
        <input type="hidden" name="lotes[]" value="<?= (int) $id ?>">
    <?php endforeach; ?>
    <div class="card-body py-3">
        <h2 class="h6 mb-3">Datos de los lotes nuevos (opcional)</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="campoDescripcion" class="form-label small">Descripción</label>
                <input id="campoDescripcion" type="text" name="descripcion" maxlength="200" class="form-control form-control-sm"
                       placeholder="<?= $e($descripcionSugerida) ?>" value="<?= $e($viejo['descripcion'] ?? '') ?>">
                <div class="form-text">Si la dejas vacía: "<?= $e($descripcionSugerida) ?>".</div>
            </div>
            <div class="col-md-6">
                <label for="campoObservaciones" class="form-label small">Observaciones</label>
                <input id="campoObservaciones" type="text" name="observaciones" maxlength="500" class="form-control form-control-sm"
                       value="<?= $e($viejo['observaciones'] ?? '') ?>">
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary" id="botonConfirmarUnificar">
                <i class="bi bi-diagram-3 me-1"></i><?= $separar ? 'Confirmar separación' : 'Confirmar unificación' ?>
            </button>
            <a href="<?= Url::to('/reintegros/lotes') ?>" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </div>
</form>
