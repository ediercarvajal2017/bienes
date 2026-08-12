<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;

$viejo ??= [];
$v = static fn (string $campo, mixed $porDefecto = '') => $viejo[$campo] ?? $porDefecto;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Alta masiva de bienes idénticos</h1>
    <a href="<?= Url::to('/bienes') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<p class="text-muted small" style="max-width: 680px;">
    Para artículos que llegan en cantidad y son iguales entre sí (ej. 250 sillas metálicas). Cada uno
    se registra como un bien independiente — con su propio código, QR, ubicación e historial — pero
    quedan agrupados bajo el mismo lote para que la cartera no se llene de filas casi idénticas.
</p>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small" style="max-width: 680px;"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<p class="text-muted small mb-2">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>

<form method="post" action="<?= Url::to('/bienes/alta-masiva') ?>" class="row g-3" style="max-width: 680px;">
    <?= Csrf::field() ?>

    <?php if (Auth::esSuperusuario()): ?>
        <div class="col-12">
            <label class="form-label small requerido" for="institucionLote">Institución</label>
            <select name="institucion_id" id="institucionLote" class="form-select selector-buscable" required>
                <?php foreach ($instituciones as $i): ?>
                    <option value="<?= $i['id'] ?>" <?= (string) $v('institucion_id') === (string) $i['id'] ? 'selected' : '' ?>><?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">La categoría se llena según la institución que elijas aquí.</div>
        </div>
    <?php endif; ?>

    <div class="col-md-4">
        <label class="form-label small requerido">Código de lote</label>
        <input type="text" name="lote" class="form-control" required
               placeholder="Ej. SILLAS-2026"
               value="<?= htmlspecialchars($v('lote'), ENT_QUOTES) ?>">
        <div class="form-text">Solo letras, números y guiones. Cada bien queda con el código "{lote}-001", "{lote}-002", etc.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label small requerido">Cantidad</label>
        <input type="number" name="cantidad" class="form-control" min="2" max="500" required
               value="<?= htmlspecialchars((string) $v('cantidad', '2'), ENT_QUOTES) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label small" for="categoriaLote">Categoría</label>
        <select name="categoria_id" id="categoriaLote" class="form-select selector-buscable">
            <option value="">-- Selecciona --</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (int) $v('categoria_id', 0) === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-12">
        <label class="form-label small requerido">Descripción</label>
        <input type="text" name="descripcion" class="form-control" required
               placeholder="Ej. Silla metálica con espaldar en polipropileno"
               value="<?= htmlspecialchars($v('descripcion'), ENT_QUOTES) ?>">
    </div>

    <div class="col-md-4">
        <label class="form-label small">Marca (si aplica)</label>
        <input type="text" name="marca" class="form-control" value="<?= htmlspecialchars($v('marca'), ENT_QUOTES) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label small requerido">Fecha de ingreso</label>
        <input type="date" name="fecha_ingreso" class="form-control" required value="<?= htmlspecialchars((string) $v('fecha_ingreso', date('Y-m-d')), ENT_QUOTES) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label small">Valor unitario</label>
        <input type="number" step="0.01" min="0" name="valor" class="form-control" value="<?= htmlspecialchars((string) $v('valor', '0'), ENT_QUOTES) ?>">
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">Crear bienes del lote</button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const institucionSelect = document.getElementById('institucionLote');
    const categoriaSelect = document.getElementById('categoriaLote');
    if (!institucionSelect || !categoriaSelect) { return; }

    const categoriaIdPrevio = <?= json_encode((string) $v('categoria_id', '')) ?>;

    function actualizarCategorias(institucionId) {
        const tomCategoria = categoriaSelect.tomselect;
        if (!tomCategoria) { return; }

        tomCategoria.clear();
        tomCategoria.clearOptions();

        if (!institucionId) { return; }

        fetch(<?= json_encode(Url::to('/categorias/por-institucion')) ?> + '?institucion_id=' + encodeURIComponent(institucionId))
            .then(function (respuesta) { return respuesta.json(); })
            .then(function (datos) {
                (datos.categorias || []).forEach(function (c) {
                    tomCategoria.addOption({ value: String(c.id), text: c.nombre });
                });
                tomCategoria.refreshOptions(false);

                if (categoriaIdPrevio && (datos.categorias || []).some(function (c) { return String(c.id) === categoriaIdPrevio; })) {
                    tomCategoria.setValue(categoriaIdPrevio);
                }
            });
    }

    institucionSelect.addEventListener('change', function () {
        actualizarCategorias(institucionSelect.value);
    });

    if (institucionSelect.value) {
        actualizarCategorias(institucionSelect.value);
    }
});
</script>
