<?php

use App\Core\Csrf;
use App\Core\Url;

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
    <a href="<?= Url::to('/espacios/carga-masiva') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div>
<?php endif; ?>

<p class="text-muted small">
    <?= (int) $carga['total_filas'] ?> filas ·
    <?= (int) $carga['nuevos'] ?> nuevas ·
    <?= (int) $carga['modificados'] ?> modificadas ·
    <?= (int) $carga['sin_cambios'] ?> sin cambios
</p>

<?php if ((int) $carga['aplicada'] === 0): ?>
    <form method="post" action="<?= Url::to('/espacios/carga-masiva/' . $carga['id'] . '/confirmar') ?>" class="mb-3" data-mostrar-cargando
          onsubmit="return confirm('¿Confirmar la importación? Se crearán o actualizarán los espacios indicados.');">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-primary btn-sm">Confirmar importación</button>
    </form>
<?php else: ?>
    <div class="alert alert-secondary py-2 small" style="max-width:480px;">Esta carga ya fue aplicada.</div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-sm bg-white tabla-cards">
        <thead>
        <tr><th>Fila</th><th>Estado</th><th>Número</th><th>Nombre</th><th>Cambios</th></tr>
        </thead>
        <tbody>
        <?php foreach ($filas as $f): ?>
            <tr>
                <td class="mono small" data-label="Fila"><?= (int) $f['fila'] ?></td>
                <td data-label="Estado"><span class="badge <?= $clases[$f['tipo']] ?>"><?= $etiquetas[$f['tipo']] ?></span></td>
                <td class="mono small" data-label="Número"><?= htmlspecialchars($f['datos']['codigo'] ?? $f['codigo'] ?? '', ENT_QUOTES) ?></td>
                <td data-label="Nombre"><?= htmlspecialchars($f['datos']['nombre'] ?? $f['nombre'] ?? '', ENT_QUOTES) ?></td>
                <td class="small" data-label="Cambios">
                    <?php if ($f['tipo'] === 'nuevo' && !empty($f['datos']['responsables'])): ?>
                        <div class="text-muted">Responsable(s): <?= htmlspecialchars(implode(', ', array_map(fn ($u) => $u['nombres'] . ' ' . $u['apellidos'], $f['datos']['responsables'])), ENT_QUOTES) ?></div>
                    <?php elseif ($f['tipo'] === 'modificado' && !empty($f['cambios'])): ?>
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
