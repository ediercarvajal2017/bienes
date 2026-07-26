<?php

use App\Core\Csrf;
use App\Core\Url;

?>
<div class="mb-3">
    <h1 class="h4 mb-0">Categorías de bienes</h1>
    <p class="text-muted small mb-0">Catálogo usado al registrar bienes (sillas, mesas, tableros, proyectores...).</p>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<form method="post" action="<?= Url::to('/categorias') ?>" class="d-flex gap-2 mb-4" style="max-width: 560px;">
    <?= Csrf::field() ?>
    <input type="text" name="nombre" class="form-control" placeholder="Nombre de la categoría" required>
    <button type="submit" class="btn btn-primary text-nowrap">Agregar</button>
</form>

<div class="table-responsive" style="max-width: 640px;">
    <table class="table table-sm bg-white align-middle tabla-cards">
        <thead>
        <tr><th>Nombre</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($categorias as $c): ?>
            <tr>
                <td data-label="Nombre">
                    <form method="post" action="<?= Url::to('/categorias/' . $c['id']) ?>" class="d-flex gap-2">
                        <?= Csrf::field() ?>
                        <input type="text" name="nombre" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>" required>
                        <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">Guardar</button>
                    </form>
                </td>
                <td data-label="Estado">
                    <?php if ((int) $c['activo'] === 1): ?>
                        <span class="badge badge-estado-activo">Activa</span>
                    <?php else: ?>
                        <span class="badge text-bg-secondary">Inactiva</span>
                    <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                    <form method="post" action="<?= Url::to('/categorias/' . $c['id'] . '/estado') ?>" class="d-inline">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-<?= (int) $c['activo'] === 1 ? 'danger' : 'success' ?>">
                            <?= (int) $c['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </form>
                    <form method="post" action="<?= Url::to('/categorias/' . $c['id'] . '/eliminar') ?>" class="d-inline"
                          onsubmit="return confirm('¿Eliminar esta categoría de forma permanente? Solo es posible si ningún bien la usa.');">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="text-muted small">Desactivar una categoría la oculta al registrar bienes nuevos, pero conserva los bienes que ya la usan.</p>
