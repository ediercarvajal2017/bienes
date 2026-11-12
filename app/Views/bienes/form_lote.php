<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Helpers\AyudaCampo;

$viejo ??= [];
$v = static fn (string $campo, mixed $porDefecto = '') => $viejo[$campo] ?? $porDefecto;
$e = static fn (mixed $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1" style="max-width: 720px;">
    <h1 class="h4 mb-0">Alta masiva de bienes idénticos</h1>
    <a href="<?= Url::to('/bienes') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>
<p class="text-muted small mb-3" style="max-width: 720px;">Registra muchas unidades iguales con un solo código (ej. 250 sillas): cada una queda como un bien con su propio código y QR.</p>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small" style="max-width: 720px;"><?= $e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= Url::to('/bienes/alta-masiva') ?>" class="formulario-secciones">
    <?= Csrf::field() ?>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="titulo-seccion">Datos del lote</h2>
            <div class="row g-3">
                <?php if (Auth::esSuperusuario()): ?>
                    <div class="col-12">
                        <?= AyudaCampo::etiqueta('institucionLote', 'Institución', 'institucion', true) ?>
                        <select name="institucion_id" id="institucionLote" class="form-select selector-buscable" required>
                            <?php foreach ($instituciones as $i): ?>
                                <option value="<?= $i['id'] ?>" <?= (string) $v('institucion_id') === (string) $i['id'] ? 'selected' : '' ?>><?= $e($i['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="col-md-4">
                    <?= AyudaCampo::etiqueta('categoriaLote', 'Categoría', 'categoria') ?>
                    <select name="categoria_id" id="categoriaLote" class="form-select selector-buscable">
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (int) $v('categoria_id', 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= $e($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <?= AyudaCampo::etiqueta('campo-lote', 'Código de lote', 'lote_codigo', true) ?>
                    <input id="campo-lote" type="text" name="lote" class="form-control" required
                           placeholder="Ej. SILLAS-2026"
                           value="<?= $e($v('lote')) ?>">
                </div>
                <div class="col-md-4">
                    <?= AyudaCampo::etiqueta('campo-cantidad', 'Cantidad', 'lote_cantidad', true) ?>
                    <input id="campo-cantidad" type="number" inputmode="numeric" name="cantidad" class="form-control" min="2" max="500" required
                           value="<?= $e($v('cantidad', '2')) ?>">
                </div>

                <div class="col-md-8">
                    <?= AyudaCampo::etiqueta('campo-descripcion', 'Descripción', 'descripcion', true) ?>
                    <input id="campo-descripcion" type="text" name="descripcion" data-descripcion-segun-categoria="categoriaLote" class="form-control" required
                           placeholder="Ej. Silla metálica con espaldar en polipropileno"
                           value="<?= $e($v('descripcion')) ?>">
                </div>
                <div class="col-md-4">
                    <?= AyudaCampo::etiqueta('campo-marca', 'Marca', 'marca') ?>
                    <input id="campo-marca" type="text" name="marca" class="form-control" value="<?= $e($v('marca')) ?>">
                </div>

                <div class="col-md-4">
                    <?= AyudaCampo::etiqueta('campo-fecha-ingreso', 'Fecha de ingreso', 'fecha_ingreso', true) ?>
                    <input id="campo-fecha-ingreso" type="date" name="fecha_ingreso" class="form-control" required value="<?= $e($v('fecha_ingreso', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-4">
                    <?= AyudaCampo::etiqueta('campo-valor', 'Valor unitario', 'lote_valor') ?>
                    <input id="campo-valor" type="number" inputmode="decimal" step="0.01" min="0" max="9999999999" name="valor" class="form-control" value="<?= $e($v('valor', '0')) ?>">
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="imprimir_qr" value="1" id="imprimirQrLote" class="form-check-input"
                               <?= !empty($v('imprimir_qr', '0')) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="imprimirQrLote">Imprimir QR de todo el lote</label><?= AyudaCampo::boton('lote_imprimir_qr') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="accion-fija-movil">
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
<script src="<?= Url::asset('/assets/js/descripcion-categoria.js') ?>"></script>
