<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Models\Categoria;

?>
<?= ui('encabezado_pagina', ['titulo' => 'Categorías de bienes', 'icono' => 'categoria', 'subtitulo' => 'Catálogo usado al registrar bienes (sillas, mesas, tableros, proyectores...) — propio de cada institución.']) ?>

<?php if (Auth::esSuperusuario()): ?>
    <div class="mb-3 contenedor-sm">
        <label for="selectorInstitucion" class="form-label small">Institución</label>
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

    <form method="post" action="<?= Url::to('/categorias') ?>" class="mb-4 contenedor-md">
        <?= Csrf::field() ?>
        <?php if (Auth::esSuperusuario()): ?>
            <input type="hidden" name="institucion_id" value="<?= $institucionId ?>">
        <?php endif; ?>
        <label class="form-label small requerido" for="nombreCategoria">Nombre de la categoría</label>
        <div class="d-flex gap-2">
            <input type="text" id="nombreCategoria" name="nombre" class="form-control" placeholder="Ej. Sillas" required>
            <button type="submit" class="btn btn-primary text-nowrap">Registrar</button>
        </div>
    </form>

    <?= ui('herramientas_tabla', ['suelta' => true]) ?>
    <div data-navegable class="table-responsive contenedor-md">
        <table class="table table-sm bg-white align-middle tabla-cards">
            <thead>
            <tr>
                <th>Nombre</th>
                <th><span class="d-inline-flex align-items-center gap-1">Estado <?= \App\Helpers\AyudaCampo::boton('estado_categoria') ?></span></th>
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
                            <span class="badge badge-neutro ms-1" title="Esta categoría la usa el sistema para el código automático de bienes nuevos — no se puede renombrar ni eliminar.">
                                <?= icono('seguridad') ?> Protegida
                            </span>
                        <?php else: ?>
                            <form method="post" action="<?= Url::to('/categorias/' . $c['id']) ?>" class="d-flex gap-2">
                                <?= Csrf::field() ?>
                                <input type="text" name="nombre" class="form-control form-control-sm"
                                       aria-label="Nombre de la categoría <?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>"
                                       value="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>" required>
                                <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap">Guardar</button>
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
                            <form method="post" action="<?= Url::to('/categorias/' . $c['id'] . '/estado') ?>" class="d-inline"
                              data-confirmar="<?= (int) $c['activo'] === 1 ? '¿Desactivar esta categoría? No aparecerá al registrar bienes nuevos.' : '¿Activar esta categoría?' ?>">
                                <?= Csrf::field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-<?= (int) $c['activo'] === 1 ? 'danger' : 'success' ?>">
                                    <?= (int) $c['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>
                            <form method="post" action="<?= Url::to('/categorias/' . $c['id'] . '/eliminar') ?>" class="d-inline"
                                  data-confirmar="¿Eliminar esta categoría? Solo es posible si ningún bien la usa. Un superusuario podrá restaurarla desde la papelera si fue un error.">
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
