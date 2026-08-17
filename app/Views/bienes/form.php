<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;
use App\Models\Categoria;

$esEdicion = $bien !== null;
$puedeEditar = Auth::esSuperusuario() || Auth::tienePermiso('bienes.editar') || (!$esEdicion && Auth::tienePermiso('bienes.crear'));
// Un bien dado de baja o reintegrado ya no esta fisicamente en la institucion -- no tiene
// sentido ofrecer "Asignar" para el (ver MovimientoController::verificarAsignable()).
$bienFueraDeCirculacion = $esEdicion && in_array($bien['estado'], ['dado_de_baja', 'reintegrado'], true);
// "Sin cartera" no admite reintegro, solo baja (ver MovimientoController::reintegrar()) --
// Trasladar y Trasladar a otra sede siguen disponibles para esta categoria.
$bienEsSinCartera = $esEdicion && ($bien['categoria_nombre'] ?? null) === Categoria::NOMBRE_CATEGORIA_PROTEGIDA;
$viejo ??= [];
$verificacionId ??= null;
$hallazgo ??= null;
$v = static fn (string $campo, mixed $porDefecto = '') => $viejo[$campo] ?? $bien[$campo] ?? $porDefecto;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><?= $esEdicion ? 'Editar bien' : 'Registrar bien' ?></h1>
    <a href="<?= Url::to('/bienes') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<?php if ($esEdicion): ?>
    <nav class="nav nav-pills small mb-3">
        <a class="nav-link" href="#datosBien">Datos del bien</a>
        <a class="nav-link" href="#asignacionMovimientos">Asignación y movimientos</a>
    </nav>
<?php endif; ?>

<?php if ($esEdicion): ?>
    <div class="card mb-4" style="max-width: 680px;">
        <div class="card-body d-flex align-items-center gap-3 py-3">
            <img src="<?= Url::to('/qr/' . $bien['qr_token'] . '/imagen') ?>" alt="Código QR" style="width:80px;height:80px;">
            <div>
                <div class="fw-semibold small mb-1">Código QR de este bien</div>
                <a href="<?= Url::to('/qr/' . $bien['qr_token']) ?>" target="_blank" class="small d-block">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Ver ficha pública
                </a>
                <a href="<?= Url::to('/qr/' . $bien['qr_token'] . '/imagen') ?>" download="qr-<?= htmlspecialchars($bien['codigo_identificacion'], ENT_QUOTES) ?>.png" class="small d-block">
                    <i class="bi bi-download me-1"></i>Descargar para imprimir
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<?php if (!$esEdicion && $hallazgo !== null): ?>
    <div class="alert alert-info py-2 small" style="max-width: 680px;">
        <i class="bi bi-info-circle me-1"></i>Formalizando un hallazgo reportado en
        <strong><?= htmlspecialchars($hallazgo['espacio_nombre'], ENT_QUOTES) ?></strong>
        por <?= htmlspecialchars($hallazgo['nombres'] . ' ' . $hallazgo['apellidos'], ENT_QUOTES) ?>.
        Al guardar, el bien quedará asignado automáticamente a ese espacio.
    </div>
<?php endif; ?>

<?php if ($puedeEditar): ?>
    <p class="text-muted small mb-2">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>
<?php endif; ?>

<form id="datosBien" method="post"
      action="<?= $esEdicion ? Url::to('/bienes/' . $bien['id']) : Url::to('/bienes') ?>"
      enctype="multipart/form-data" class="row g-3" style="max-width: 680px;">
    <?= Csrf::field() ?>
    <?php if (!$esEdicion && $hallazgo !== null): ?>
        <input type="hidden" name="hallazgo_id" value="<?= (int) $hallazgo['id'] ?>">
    <?php endif; ?>

    <?php if (!$esEdicion && Auth::esSuperusuario()): ?>
        <div class="col-12">
            <label class="form-label small requerido" for="institucionBien">Institución</label>
            <select name="institucion_id" id="institucionBien" class="form-select selector-buscable" required>
                <?php foreach ($instituciones as $i): ?>
                    <option value="<?= $i['id'] ?>" <?= (string) $v('institucion_id') === (string) $i['id'] ? 'selected' : '' ?>><?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">La categoría se llena según la institución que elijas aquí.</div>
        </div>
    <?php endif; ?>

    <div class="col-md-4">
        <label class="form-label small requerido" for="codigoIdentificacion">Código de identificación</label>
        <input type="text" name="codigo_identificacion" id="codigoIdentificacion" class="form-control" required autocomplete="off" <?= $puedeEditar ? '' : 'disabled' ?>
               value="<?= htmlspecialchars($v('codigo_identificacion'), ENT_QUOTES) ?>">
        <?php if (!$esEdicion): ?>
            <div class="form-text">Si eliges la categoría "Sin cartera", se sugiere el siguiente código automáticamente (puedes cambiarlo).</div>
        <?php endif; ?>
    </div>
    <div class="col-md-4">
        <label class="form-label small" for="categoriaBien">Categoría</label>
        <select name="categoria_id" id="categoriaBien" class="form-select selector-buscable" <?= $puedeEditar ? '' : 'disabled' ?>>
            <option value="">-- Selecciona --</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (int) $v('categoria_id', 0) === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label small">Marca (si aplica)</label>
        <input type="text" name="marca" class="form-control" <?= $puedeEditar ? '' : 'disabled' ?>
               value="<?= htmlspecialchars($v('marca'), ENT_QUOTES) ?>">
    </div>

    <div class="col-12">
        <label class="form-label small requerido">Descripción</label>
        <input type="text" name="descripcion" class="form-control" required <?= $puedeEditar ? '' : 'disabled' ?>
               placeholder="Ej. Silla plástica azul, Proyector Epson X200..."
               value="<?= htmlspecialchars($v('descripcion'), ENT_QUOTES) ?>">
    </div>

    <div class="col-md-4">
        <label class="form-label small requerido">Fecha de ingreso</label>
        <input type="date" name="fecha_ingreso" class="form-control" required <?= $puedeEditar ? '' : 'disabled' ?>
               value="<?= htmlspecialchars((string) $v('fecha_ingreso', date('Y-m-d')), ENT_QUOTES) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label small">Valor</label>
        <input type="number" step="0.01" min="0" name="valor" class="form-control" <?= $puedeEditar ? '' : 'disabled' ?>
               value="<?= htmlspecialchars((string) $v('valor', '0'), ENT_QUOTES) ?>">
    </div>
    <?php $estadosEtiquetas = ['activo' => 'Activo', 'reintegrado' => 'Reintegrado', 'en_reparacion' => 'En reparación', 'dado_de_baja' => 'Dado de baja']; ?>
    <div class="col-md-4">
        <label class="form-label small">Estado</label>
        <?php if ($esEdicion && in_array($bien['estado'], ['reintegrado', 'dado_de_baja'], true)): ?>
            <input type="text" class="form-control" value="<?= $estadosEtiquetas[$bien['estado']] ?>" disabled>
            <div class="form-text">
                Este estado se gestiona desde <?= $bien['estado'] === 'reintegrado' ? 'el panel de "Reintegrar" más abajo' : 'la aprobación de bajas (módulo Bajas)' ?>, no desde aquí.
            </div>
        <?php else: ?>
            <select name="estado" class="form-select" <?= $puedeEditar ? '' : 'disabled' ?>>
                <?php foreach (['activo' => 'Activo', 'en_reparacion' => 'En reparación'] as $valorEstado => $etiqueta): ?>
                    <option value="<?= $valorEstado ?>" <?= $v('estado', 'activo') === $valorEstado ? 'selected' : '' ?>><?= $etiqueta ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </div>

    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" name="tiene_factura" value="1" id="tieneFactura" class="form-check-input"
                   <?= !empty($v('tiene_factura', 0)) ? 'checked' : '' ?> <?= $puedeEditar ? '' : 'disabled' ?>>
            <label class="form-check-label small" for="tieneFactura">Este bien tiene factura de compra</label>
        </div>
    </div>

    <?php
    // Por defecto va marcada al crear (lo normal es querer imprimir un bien nuevo) y
    // refleja si el bien ya está en la Bodega de impresión al editar -- no "si el QR ya
    // se imprimió", sino "si sigue con una solicitud activa" (ver Bien::solicitarQr()).
    $imprimirQrPorDefecto = $esEdicion ? !empty($bien['qr_solicitado_en']) : true;
    ?>
    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" name="imprimir_qr" value="1" id="imprimirQr" class="form-check-input"
                   <?= !empty($v('imprimir_qr', $imprimirQrPorDefecto ? '1' : '0')) ? 'checked' : '' ?>
                   <?= $puedeEditar ? '' : 'disabled' ?>>
            <label class="form-check-label small" for="imprimirQr">Imprimir QR</label>
            <i class="bi bi-question-circle text-muted small ms-1"
               style="cursor: help;"
               title="Agrega este bien a la Bodega de impresión de QR (en &quot;Generar QR masivo&quot;) para imprimirlo."></i>
        </div>
    </div>

    <div class="col-md-6">
        <?php if ($puedeEditar): ?>
            <?php View::render('partials/campo_foto', [
                'nombreCampo' => 'foto',
                'etiqueta' => 'Fotografía del bien',
                'fotoActualUrl' => !empty($bien['foto_path'])
                    ? Url::to('/archivos/' . $bien['foto_path'])
                    : (!empty($hallazgo['foto_path']) ? Url::to('/archivos/' . $hallazgo['foto_path']) : null),
            ]); ?>
        <?php elseif (!empty($bien['foto_path'])): ?>
            <label class="form-label small d-block">Fotografía del bien</label>
            <img src="<?= Url::to('/archivos/' . $bien['foto_path']) ?>"
                 alt="Foto de <?= htmlspecialchars($bien['descripcion'], ENT_QUOTES) ?>"
                 class="mb-2 d-block" style="height:72px;border-radius:4px;">
        <?php endif; ?>
    </div>
    <div class="col-md-6<?= !empty($bien['tiene_factura']) ? '' : ' d-none' ?>" id="contenedorFactura">
        <label class="form-label small d-block">Factura (PDF)</label>
        <?php if (!empty($bien['factura_pdf_path'])): ?>
            <a href="<?= Url::to('/archivos/' . $bien['factura_pdf_path']) ?>" target="_blank" class="d-block mb-2 small">
                <i class="bi bi-file-earmark-pdf me-1"></i>Ver factura actual
            </a>
        <?php endif; ?>
        <?php if ($puedeEditar): ?>
            <input type="file" name="factura_pdf" accept="application/pdf" class="form-control">
        <?php endif; ?>
    </div>

    <?php if ($puedeEditar): ?>
        <div class="col-12">
            <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Registrar bien' ?></button>
        </div>
    <?php endif; ?>
</form>

<?php if ($puedeEditar): ?>
<script>
(function () {
    const checkFactura = document.getElementById('tieneFactura');
    const contenedorFactura = document.getElementById('contenedorFactura');
    if (checkFactura && contenedorFactura) {
        checkFactura.addEventListener('change', function () {
            contenedorFactura.classList.toggle('d-none', !checkFactura.checked);
        });
    }
})();

// Las categorías ahora son propias de cada institución — cuando el superusuario elige
// (o cambia) la institución en este mismo formulario, el combo de categoría se llena
// por AJAX en vez de quedar fijo desde que cargó la página.
document.addEventListener('DOMContentLoaded', function () {
    const institucionSelect = document.getElementById('institucionBien');
    const categoriaSelect = document.getElementById('categoriaBien');
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

<?php if (!$esEdicion): ?>
// Al registrar un bien nuevo se le pregunta al SERVIDOR, por el id de la categoría
// elegida, si corresponde sugerir un código (solo lo hace para "Sin cartera"). A
// propósito no se compara aquí el nombre de la categoría: Tom Select guarda el texto de
// un <option> renderizado por el servidor junto con los saltos de línea e indentación
// del HTML, así que esa comparación fallaba siempre y la consulta ni se disparaba.
//
// La sugerencia solo se aplica si el usuario todavía no escribió nada en el campo — y
// eso se sigue con una bandera propia, no mirando si el campo está vacío, porque el
// navegador puede restaurar ahí un valor viejo por su cuenta (de ahí también el
// autocomplete="off" del campo).
document.addEventListener('DOMContentLoaded', function () {
    const categoriaSelect = document.getElementById('categoriaBien');
    const codigoInput = document.getElementById('codigoIdentificacion');
    if (!categoriaSelect || !codigoInput) { return; }

    let editadoPorUsuario = codigoInput.value.trim() !== '';
    codigoInput.addEventListener('input', function () {
        editadoPorUsuario = true;
    });

    categoriaSelect.addEventListener('change', function () {
        const tomCategoria = categoriaSelect.tomselect;
        const categoriaId = tomCategoria ? tomCategoria.getValue() : categoriaSelect.value;
        if (!categoriaId || editadoPorUsuario) { return; }

        fetch(<?= json_encode(Url::to('/bienes/siguiente-codigo-sin-cartera')) ?> + '?categoria_id=' + encodeURIComponent(categoriaId))
            .then(function (respuesta) { return respuesta.json(); })
            .then(function (datos) {
                if (datos.codigo && !editadoPorUsuario) {
                    codigoInput.value = datos.codigo;
                }
            });
    });
});
<?php endif; ?>
</script>
<?php endif; ?>

<?php if ($esEdicion): ?>
    <hr class="my-4" style="max-width: 680px;">

    <h2 id="asignacionMovimientos" class="h5 mb-3">Asignación y movimientos</h2>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-success py-2 small" style="max-width: 680px;"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <div class="card mb-3" style="max-width: 680px;">
        <div class="card-body py-3">
            <?php if ($asignacionActiva): ?>
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-semibold"><?= $asignacionActiva['espacio_nombre'] ? htmlspecialchars($asignacionActiva['espacio_nombre'], ENT_QUOTES) : 'Sin espacio asignado' ?></div>
                        <?php if (!empty($asignacionActiva['responsables_nombres'])): ?>
                            <div class="small text-muted">Responsable(s): <?= htmlspecialchars($asignacionActiva['responsables_nombres'], ENT_QUOTES) ?></div>
                        <?php endif; ?>
                        <div class="small text-muted">desde <?= htmlspecialchars($asignacionActiva['fecha_asignacion'], ENT_QUOTES) ?></div>
                        <?php if (!empty($asignacionActiva['observaciones'])): ?>
                            <div class="small text-muted mt-1"><?= htmlspecialchars($asignacionActiva['observaciones'], ENT_QUOTES) ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="badge badge-estado-activo">Asignado</span>
                </div>
            <?php else: ?>
                <span class="text-muted">Este bien no tiene una asignación activa.</span>
                <?php if ($bienFueraDeCirculacion): ?>
                    <div class="small text-muted mt-1">
                        <i class="bi bi-info-circle me-1"></i>Está <?= str_replace('_', ' ', $bien['estado']) ?>, ya no está físicamente en la institución — por eso no se puede asignar.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($verificacionId !== null): ?>
        <div class="alert alert-info py-2 small" style="max-width: 680px;">
            <i class="bi bi-info-circle me-1"></i>Corrigiendo la ubicación a partir de una discrepancia reportada en una jornada de verificación.
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-3 mb-4">

        <?php if (!$asignacionActiva && !$bienFueraDeCirculacion && (Auth::esSuperusuario() || Auth::tienePermiso('asignaciones.crear'))): ?>
            <details id="panelAsignar" class="border rounded p-3 bg-white panel-accion" <?= $verificacionId !== null ? 'open' : '' ?>>
                <summary class="fw-semibold" style="cursor:pointer;">Asignar</summary>
                <form method="post" action="<?= Url::to('/bienes/' . $bien['id'] . '/asignar') ?>" class="mt-3 d-flex flex-column gap-2">
                    <?= Csrf::field() ?>
                    <?php if ($verificacionId !== null): ?>
                        <input type="hidden" name="verificacion_id" value="<?= (int) $verificacionId ?>">
                    <?php endif; ?>
                    <div>
                        <label class="form-label small" for="espacioAsignar">Espacio / ubicación (define el responsable)</label>
                        <select name="espacio_id" id="espacioAsignar" class="form-select form-select-sm selector-buscable" required>
                            <option value="">-- Selecciona --</option>
                            <?php foreach ($espaciosInstitucion as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['codigo'] . ' - ' . $e['nombre'], ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small">Fecha de asignación</label>
                        <input type="date" name="fecha_asignacion" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label class="form-label small">Observaciones</label>
                        <textarea name="observaciones" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Asignar</button>
                </form>
            </details>
        <?php endif; ?>

        <?php if ($bien['estado'] === 'reintegrado' && (Auth::esSuperusuario() || Auth::rol() === 'rector')): ?>
            <details id="panelReactivar" class="border rounded p-3 bg-white panel-accion">
                <summary class="fw-semibold" style="cursor:pointer;">Reactivar</summary>
                <p class="small text-muted mt-3 mb-2">
                    Este bien está reintegrado. Úsalo solo si de verdad volvió a la institución (por ejemplo, la Alcaldía lo devolvió) o si el reintegro fue un error — queda registrado en el historial con el motivo que escribas.
                </p>
                <form method="post" action="<?= Url::to('/bienes/' . $bien['id'] . '/reactivar') ?>" class="d-flex flex-column gap-2">
                    <?= Csrf::field() ?>
                    <div>
                        <label class="form-label small">Fecha</label>
                        <input type="date" name="fecha" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label class="form-label small requerido">Motivo</label>
                        <textarea name="motivo" class="form-control form-control-sm" rows="2" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-primary">Reactivar bien</button>
                </form>
            </details>
        <?php endif; ?>

        <?php if ($bien['estado'] !== 'dado_de_baja' && $bienEsSinCartera && (Auth::esSuperusuario() || Auth::tienePermiso('bajas.crear'))): ?>
            <details class="border rounded p-3 bg-white panel-accion">
                <summary class="fw-semibold" style="cursor:pointer;">Dar de baja</summary>
                <p class="small text-muted mt-3 mb-2">
                    Reporta el estado del bien y el motivo. La baja queda pendiente hasta que alguien con autorización la apruebe.
                </p>
                <a href="<?= Url::to('/qr/' . $bien['qr_token'] . '/baja') ?>" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-exclamation-triangle me-1"></i>Reportar baja
                </a>
            </details>
        <?php endif; ?>

        <?php if ($asignacionActiva && !$bienFueraDeCirculacion && (Auth::esSuperusuario() || Auth::tienePermiso('asignaciones.crear'))): ?>
            <details id="panelTrasladar" class="border rounded p-3 bg-white panel-accion" <?= $verificacionId !== null ? 'open' : '' ?>>
                <summary class="fw-semibold" style="cursor:pointer;">Trasladar</summary>
                <form method="post" action="<?= Url::to('/bienes/' . $bien['id'] . '/trasladar') ?>" class="mt-3 d-flex flex-column gap-2">
                    <?= Csrf::field() ?>
                    <?php if ($verificacionId !== null): ?>
                        <input type="hidden" name="verificacion_id" value="<?= (int) $verificacionId ?>">
                    <?php endif; ?>
                    <div>
                        <label class="form-label small" for="espacioTrasladar">Nuevo espacio</label>
                        <select name="espacio_destino_id" id="espacioTrasladar" class="form-select form-select-sm selector-buscable" required>
                            <?php foreach ($espaciosInstitucion as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['codigo'] . ' - ' . $e['nombre'], ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small">Fecha del traslado</label>
                        <input type="date" name="fecha" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label class="form-label small">Observaciones</label>
                        <textarea name="observaciones" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Registrar traslado</button>
                </form>
            </details>

            <?php if (!empty($familiaSedesDestino)): ?>
                <details id="panelTrasladarSede" class="border rounded p-3 bg-white panel-accion">
                    <summary class="fw-semibold" style="cursor:pointer;">Trasladar a otra sede</summary>
                    <form method="post" action="<?= Url::to('/bienes/' . $bien['id'] . '/trasladar-sede') ?>" class="mt-3 d-flex flex-column gap-2">
                        <?= Csrf::field() ?>
                        <div>
                            <label class="form-label small" for="sedeDestinoTraslado">Sede destino</label>
                            <select id="sedeDestinoTraslado" name="institucion_destino_id" class="form-select form-select-sm" required>
                                <option value="">-- Selecciona una sede --</option>
                                <?php foreach ($familiaSedesDestino as $sede): ?>
                                    <option value="<?= (int) $sede['id'] ?>"><?= htmlspecialchars($sede['nombre'], ENT_QUOTES) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small" for="espacioDestinoTraslado">Espacio en la sede destino</label>
                            <select id="espacioDestinoTraslado" name="espacio_destino_id" class="form-select form-select-sm" required disabled>
                                <option value="">-- Primero selecciona una sede --</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small">Fecha del traslado</label>
                            <input type="date" name="fecha" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div>
                            <label class="form-label small">Observaciones</label>
                            <textarea name="observaciones" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-primary">Trasladar a otra sede</button>
                    </form>
                </details>
                <script>
                (function () {
                    var espaciosPorSede = <?= json_encode($espaciosPorSedeDestino) ?>;
                    var selectSede = document.getElementById('sedeDestinoTraslado');
                    var selectEspacio = document.getElementById('espacioDestinoTraslado');

                    selectSede.addEventListener('change', function () {
                        var espacios = espaciosPorSede[this.value] || [];
                        selectEspacio.innerHTML = '';

                        if (!this.value || espacios.length === 0) {
                            var vacio = document.createElement('option');
                            vacio.value = '';
                            vacio.textContent = this.value ? 'Esa sede no tiene espacios activos' : '-- Primero selecciona una sede --';
                            selectEspacio.appendChild(vacio);
                            selectEspacio.disabled = true;
                            return;
                        }

                        espacios.forEach(function (espacio) {
                            var opcion = document.createElement('option');
                            opcion.value = espacio.id;
                            opcion.textContent = espacio.codigo + ' - ' + espacio.nombre;
                            selectEspacio.appendChild(opcion);
                        });
                        selectEspacio.disabled = false;
                    });
                })();
                </script>
            <?php endif; ?>

            <?php if (!$bienEsSinCartera): ?>
                <details id="panelReintegrar" class="border rounded p-3 bg-white panel-accion">
                    <summary class="fw-semibold" style="cursor:pointer;">Reintegrar</summary>
                    <form method="post" action="<?= Url::to('/bienes/' . $bien['id'] . '/reintegrar') ?>" class="mt-3 d-flex flex-column gap-2">
                        <?= Csrf::field() ?>
                        <div>
                            <label class="form-label small">Destino del reintegro</label>
                            <input type="text" name="destino_texto" class="form-control form-control-sm" required placeholder="Ej. Almacén institucional">
                        </div>
                        <div>
                            <label class="form-label small">Fecha del reintegro</label>
                            <input type="date" name="fecha" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div>
                            <label class="form-label small">Observaciones</label>
                            <textarea name="observaciones" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-danger">Registrar reintegro</button>
                    </form>
                </details>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($historialMovimientos)): ?>
        <h3 class="h6">Historial de movimientos</h3>
        <div class="table-responsive" style="max-width: 680px;">
            <table class="table table-sm bg-white tabla-cards">
                <thead>
                <tr><th>Fecha</th><th>Tipo</th><th>Responsable</th><th>Destino</th><th>Observaciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($historialMovimientos as $m): ?>
                    <tr>
                        <td class="mono" data-label="Fecha"><?= htmlspecialchars($m['fecha'], ENT_QUOTES) ?></td>
                        <td data-label="Tipo"><span class="badge text-bg-light border text-capitalize"><?= htmlspecialchars($m['tipo'], ENT_QUOTES) ?></span></td>
                        <td data-label="Responsable"><?= htmlspecialchars($m['nombres'] . ' ' . $m['apellidos'], ENT_QUOTES) ?></td>
                        <td class="text-muted" data-label="Destino"><?= htmlspecialchars($m['espacio_destino_nombre'] ?? $m['destino_texto'] ?? '—', ENT_QUOTES) ?></td>
                        <td class="text-muted small" data-label="Observaciones"><?= htmlspecialchars($m['observaciones'] ?? '', ENT_QUOTES) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
