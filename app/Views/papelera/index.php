<?php

use App\Core\Csrf;
use App\Core\Url;

$etiquetasTipo = [
    'usuario' => 'Usuario',
    'espacio' => 'Espacio',
    'categoria' => 'Categoría',
    'cargo' => 'Cargo',
    'factura_administrativa' => 'Factura',
    'formato_reintegro' => 'Formato de reintegro',
    'formato_plaqueteo' => 'Formato de plaqueteo',
    'cartera_envio' => 'Cartera',
];
?>
<div class="mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-trash3 me-1"></i>Papelera de reciclaje</h1>
    <p class="text-muted small mb-0">
        Todo lo eliminado en cualquier institución, de cualquier tipo. Se conserva aquí hasta que lo restaures o
        hasta que la limpieza automática lo borre después del periodo de retención.
    </p>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<?php if (empty($elementos)): ?>
    <p class="text-muted">La papelera está vacía.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm bg-white align-middle tabla-cards">
            <thead>
            <tr>
                <th>Tipo</th>
                <th>Elemento</th>
                <th>Institución</th>
                <th>Eliminado por</th>
                <th>Eliminado el</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($elementos as $el): ?>
                <tr>
                    <td data-label="Tipo"><span class="badge text-bg-secondary"><?= htmlspecialchars($etiquetasTipo[$el['tipo']] ?? $el['tipo'], ENT_QUOTES) ?></span></td>
                    <td data-label="Elemento"><?= htmlspecialchars($el['titulo'], ENT_QUOTES) ?></td>
                    <td class="text-muted" data-label="Institución"><?= htmlspecialchars($el['institucion_nombre'] ?? '—', ENT_QUOTES) ?></td>
                    <td class="text-muted" data-label="Eliminado por"><?= htmlspecialchars($el['eliminado_por_nombre'] ?? '—', ENT_QUOTES) ?></td>
                    <td class="text-muted mono" data-label="Eliminado el"><?= htmlspecialchars($el['eliminado_en'], ENT_QUOTES) ?></td>
                    <td class="text-end">
                        <form method="post" action="<?= Url::to('/papelera/' . $el['tipo'] . '/' . $el['id'] . '/restaurar') ?>" class="d-inline">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
