<?php

use App\Core\Csrf;
use App\Core\Url;

?>
<div class="mb-3">
    <h1 class="h4 mb-0">Cargos</h1>
    <p class="text-muted small mb-0">Catálogo global de cargos disponible al registrar usuarios en cualquier institución.</p>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<form method="post" action="<?= Url::to('/cargos') ?>" class="mb-4" style="max-width: 560px;">
    <?= Csrf::field() ?>
    <label class="form-label small requerido" for="nombreCargo">Nombre del cargo</label>
    <div class="d-flex gap-2">
        <input type="text" id="nombreCargo" name="nombre" class="form-control" placeholder="Ej. Docente" required>
        <button type="submit" class="btn btn-primary text-nowrap">Registrar</button>
    </div>
</form>

<div class="table-responsive" style="max-width: 640px;">
    <table class="table table-sm bg-white align-middle tabla-cards">
        <thead>
        <tr>
            <th>Nombre</th>
            <th>Estado
                <i class="bi bi-question-circle text-muted small ms-1" style="cursor: help;"
                   title="Desactivar un cargo lo oculta al registrar usuarios nuevos, pero conserva los usuarios que ya lo tienen."></i>
            </th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($cargos as $c): ?>
            <tr>
                <td data-label="Nombre">
                    <form method="post" action="<?= Url::to('/cargos/' . $c['id']) ?>" class="d-flex gap-2">
                        <?= Csrf::field() ?>
                        <input type="text" name="nombre" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>" required>
                        <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">Guardar</button>
                    </form>
                </td>
                <td data-label="Estado">
                    <?php if ((int) $c['activo'] === 1): ?>
                        <span class="badge badge-activo">Activo</span>
                    <?php else: ?>
                        <span class="badge badge-inactivo">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                    <form method="post" action="<?= Url::to('/cargos/' . $c['id'] . '/estado') ?>" class="d-inline">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-<?= (int) $c['activo'] === 1 ? 'danger' : 'success' ?>">
                            <?= (int) $c['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </form>
                    <form method="post" action="<?= Url::to('/cargos/' . $c['id'] . '/eliminar') ?>" class="d-inline"
                          onsubmit="return confirm('¿Eliminar este cargo? Solo es posible si ningún usuario lo tiene asignado. Un superusuario podrá restaurarlo desde la papelera si fue un error.');">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
