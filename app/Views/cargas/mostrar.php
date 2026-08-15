<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$etiquetas = ['nuevo' => 'Nuevo', 'modificado' => 'Modificado', 'sin_cambios' => 'Sin cambios', 'invalido' => 'Inválido'];
$clases = [
    'nuevo' => 'badge-estado-activo',
    'modificado' => 'badge-estado-en_reparacion',
    'sin_cambios' => 'text-bg-light border',
    'invalido' => 'badge-estado-dado_de_baja',
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Resultado de la carga</h1>
    <a href="<?= Url::to('/cargas-masivas') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<?php View::render('partials/aviso_qr_pendiente', [
    'mensaje' => $mensaje,
    'qrPendienteInstitucion' => $qrPendienteInstitucion ?? null,
    'qrPendienteIds' => $qrPendienteIds ?? null,
]); ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<?php $invalidas = (int) $carga['total_filas'] - (int) $carga['nuevos'] - (int) $carga['modificados'] - (int) $carga['sin_cambios']; ?>
<p class="text-muted small">
    <?= (int) $carga['total_filas'] ?> filas ·
    <?= (int) $carga['nuevos'] ?> nuevas ·
    <?= (int) $carga['modificados'] ?> modificadas ·
    <?= (int) $carga['sin_cambios'] ?> sin cambios
    <?php if ($invalidas > 0): ?>
        · <span class="text-danger"><?= $invalidas ?> con error</span>
    <?php endif; ?>
</p>

<?php if ((int) $carga['aplicada'] === 0): ?>
    <form method="post" action="<?= Url::to('/cargas-masivas/' . $carga['id'] . '/confirmar') ?>" class="mb-3"
          onsubmit="return confirm('¿Confirmar la importación? Se crearán o actualizarán los bienes indicados.');">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-primary btn-sm">Confirmar importación</button>
    </form>
<?php else: ?>
    <div class="alert alert-secondary py-2 small" style="max-width:480px;">Esta carga ya fue aplicada.</div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-sm bg-white tabla-cards">
        <thead>
        <tr><th>Fila</th><th>Estado</th><th>Código</th><th>Descripción</th><th>Cambios</th></tr>
        </thead>
        <tbody>
        <?php foreach ($filas as $f): ?>
            <tr>
                <td class="mono small" data-label="Fila"><?= (int) $f['fila'] ?></td>
                <td data-label="Estado"><span class="badge <?= $clases[$f['tipo']] ?>"><?= $etiquetas[$f['tipo']] ?></span></td>
                <td class="mono small" data-label="Código"><?= htmlspecialchars($f['datos']['codigo_identificacion'] ?? $f['codigo'] ?? '', ENT_QUOTES) ?></td>
                <td data-label="Descripción"><?= htmlspecialchars($f['datos']['descripcion'] ?? $f['descripcion'] ?? '', ENT_QUOTES) ?></td>
                <td class="small" data-label="Cambios">
                    <?php if ($f['tipo'] === 'modificado' && !empty($f['cambios'])): ?>
                        <?php foreach ($f['cambios'] as $campo => $c): ?>
                            <div><b><?= htmlspecialchars($campo, ENT_QUOTES) ?>:</b>
                                "<?= htmlspecialchars((string) $c['antes'], ENT_QUOTES) ?>" →
                                "<?= htmlspecialchars((string) $c['despues'], ENT_QUOTES) ?>"</div>
                        <?php endforeach; ?>
                    <?php elseif ($f['tipo'] === 'invalido'): ?>
                        <span class="text-danger"><?= htmlspecialchars($f['motivo'] ?? '', ENT_QUOTES) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
