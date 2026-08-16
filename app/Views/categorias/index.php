<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Models\Categoria;

?>
<div class="mb-3">
    <h1 class="h4 mb-0">Categorías de bienes</h1>
    <p class="text-muted small mb-0">Catálogo usado al registrar bienes (sillas, mesas, tableros, proyectores...) — propio de cada institución.</p>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<?php if (Auth::esSuperusuario()): ?>
    <div class="mb-3" style="max-width: 320px;">
        <label class="form-label small">Institución</label>
        <select id="selectorInstitucion" class="form-select form-select-sm selector-buscable">
            <option value="">-- Selecciona una institución --</option>
            <?php foreach ($instituciones as $i): ?>
                <option value="<?= $i['id'] ?>" <?= $institucionId === (int) $i['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <script>
    document.getElementById('selectorInstitucion').addEventListener('change', function () {
        window.location = <?= json_encode(Url::to('/categorias')) ?> + (this.value ? '?institucion=' + encodeURIComponent(this.value) : '');
    });
    </script>
<?php endif; ?>

<?php if ($institucionId === null): ?>
    <p class="text-muted">Selecciona una institución para continuar.</p>
<?php else: ?>

    <form method="post" action="<?= Url::to('/categorias') ?>" class="d-flex gap-2 mb-4" style="max-width: 560px;">
        <?= Csrf::field() ?>
        <?php if (Auth::esSuperusuario()): ?>
            <input type="hidden" name="institucion_id" value="<?= $institucionId ?>">
        <?php endif; ?>
        <input type="text" name="nombre" class="form-control" placeholder="Nombre de la categoría" required>
        <button type="submit" class="btn btn-primary text-nowrap">Agregar</button>
    </form>

    <div class="table-responsive" style="max-width: 640px;">
        <table class="table table-sm bg-white align-middle tabla-cards">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Estado
                    <i class="bi bi-question-circle text-muted small ms-1" style="cursor: help;"
                       title="Desactivar una categoría la oculta al registrar bienes nuevos, pero conserva los bienes que ya la usan."></i>
                </th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($categorias)): ?>
                <tr><td colspan="3" class="text-muted">Esta institución todavía no tiene categorías.</td></tr>
            <?php endif; ?>
            <?php foreach ($categorias as $c): ?>
                <?php $protegida = Categoria::esProtegida($c); ?>
                <tr>
                    <td data-label="Nombre">
                        <?php if ($protegida): ?>
                            <span class="fw-semibold"><?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?></span>
                            <span class="badge text-bg-secondary ms-1" title="Esta categoría la usa el sistema para el código automático de bienes nuevos — no se puede renombrar ni eliminar.">
                                <i class="bi bi-lock-fill"></i> Protegida
                            </span>
                        <?php else: ?>
                            <form method="post" action="<?= Url::to('/categorias/' . $c['id']) ?>" class="d-flex gap-2">
                                <?= Csrf::field() ?>
                                <input type="text" name="nombre" class="form-control form-control-sm"
                                       value="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>" required>
                                <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">Guardar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td data-label="Estado">
                        <?php if ((int) $c['activo'] === 1): ?>
                            <span class="badge badge-activo">Activa</span>
                        <?php else: ?>
                            <span class="badge badge-inactivo">Inactiva</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if (!$protegida): ?>
                            <form method="post" action="<?= Url::to('/categorias/' . $c['id'] . '/estado') ?>" class="d-inline">
                                <?= Csrf::field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-<?= (int) $c['activo'] === 1 ? 'danger' : 'success' ?>">
                                    <?= (int) $c['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>
                            <form method="post" action="<?= Url::to('/categorias/' . $c['id'] . '/eliminar') ?>" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar esta categoría? Solo es posible si ningún bien la usa. Un superusuario podrá restaurarla desde la papelera si fue un error.');">
                                <?= Csrf::field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
